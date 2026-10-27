<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Pesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DebitCreditReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_sales_expenses_and_cash_balance_for_the_selected_period(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $today = now()->startOfDay();

        Pesanan::factory()->create([
            'kode_pesanan' => 'TK-SALES-001',
            'status' => 'diproses',
            'total_harga' => '50000.00',
            'created_at' => $today->copy()->addHours(9),
        ]);
        Pesanan::factory()->create([
            'kode_pesanan' => 'TK-SALES-002',
            'status' => 'selesai',
            'total_harga' => '25000.00',
            'created_at' => $today->copy()->addHours(10),
        ]);
        Pesanan::factory()->create([
            'kode_pesanan' => 'TK-PENDING-001',
            'status' => 'menunggu',
            'total_harga' => '90000.00',
            'created_at' => $today->copy()->addHours(11),
        ]);
        Expense::factory()->create([
            'category' => 'supplies',
            'description' => 'Belanja bahan baku',
            'amount' => '12000.00',
            'transaction_date' => $today->toDateString(),
        ]);
        Expense::factory()->create([
            'description' => 'Pengeluaran periode lampau',
            'transaction_date' => $today->copy()->subMonth()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('reports.debit-credit'))
            ->assertOk()
            ->assertSee('Rp75.000')
            ->assertSee('Rp12.000')
            ->assertSee('Rp63.000,00')
            ->assertSee('TK-SALES-001')
            ->assertSee('Belanja bahan baku')
            ->assertDontSee('TK-PENDING-001')
            ->assertDontSee('Pengeluaran periode lampau');
    }

    public function test_date_filter_limits_sales_and_expenses_to_the_selected_days(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $today = today();
        $yesterday = $today->copy()->subDay();

        Pesanan::factory()->create([
            'status' => 'selesai',
            'total_harga' => '40000.00',
            'created_at' => $yesterday->copy()->setTime(12, 0),
        ]);
        Pesanan::factory()->create([
            'status' => 'selesai',
            'total_harga' => '80000.00',
            'created_at' => $today->copy()->setTime(12, 0),
        ]);
        Expense::factory()->create([
            'description' => 'Biaya hari sebelumnya',
            'amount' => '5000.00',
            'transaction_date' => $yesterday->toDateString(),
        ]);
        Expense::factory()->create([
            'description' => 'Biaya hari ini',
            'amount' => '10000.00',
            'transaction_date' => $today->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('reports.debit-credit', [
                'start_date' => $yesterday->toDateString(),
                'end_date' => $yesterday->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Rp40.000')
            ->assertSee('Rp5.000')
            ->assertSee('Biaya hari sebelumnya')
            ->assertDontSee('Biaya hari ini');
    }

    public function test_report_rejects_a_period_with_the_end_before_the_start(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->from(route('reports.debit-credit'))
            ->get(route('reports.debit-credit', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->subDay()->toDateString(),
            ]))
            ->assertRedirect(route('reports.debit-credit'))
            ->assertSessionHasErrors('end_date');
    }

    public function test_admin_can_record_a_manual_expense_as_a_credit(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $today = today()->toDateString();

        $this->actingAs($admin)
            ->post(route('expenses.store', [
                'start_date' => today()->startOfMonth()->toDateString(),
                'end_date' => $today,
            ]), [
                'category' => 'utilities',
                'description' => 'Tagihan listrik dapur',
                'amount' => '125000.50',
                'transaction_date' => $today,
            ])
            ->assertRedirect(route('reports.debit-credit', [
                'start_date' => today()->startOfMonth()->toDateString(),
                'end_date' => $today,
            ]))
            ->assertSessionHas('status', 'Pengeluaran berhasil dicatat sebagai transaksi kredit.');

        $this->assertDatabaseHas('expenses', [
            'user_id' => $admin->id,
            'category' => 'utilities',
            'description' => 'Tagihan listrik dapur',
        ]);

        $expense = Expense::query()->firstOrFail();
        $this->assertSame('125000.50', $expense->amount);
        $this->assertSame($today, $expense->transaction_date->toDateString());
    }

    public function test_invalid_expenses_are_rejected_without_being_saved(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->from(route('reports.debit-credit'))
            ->post(route('expenses.store'), [
                'category' => 'unknown',
                'description' => '',
                'amount' => '-10',
                'transaction_date' => today()->addDay()->toDateString(),
            ])
            ->assertRedirect(route('reports.debit-credit'))
            ->assertSessionHasErrors(['category', 'description', 'amount', 'transaction_date']);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_csv_export_contains_transactions_and_neutralizes_spreadsheet_formulas(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $today = today()->toDateString();

        Expense::factory()->create([
            'description' => '=1+1',
            'amount' => '2500.00',
            'transaction_date' => $today,
        ]);

        $response = $this->actingAs($owner)->get(route('reports.debit-credit.export', [
            'start_date' => $today,
            'end_date' => $today,
        ]));
        $response->assertDownload('laporan-debit-kredit-'.$today.'-sampai-'.$today.'.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Tanggal,Tipe,Kategori,Keterangan,', $csv);
        $this->assertStringContainsString('"Jumlah (Rp)"', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString(',=1+1,', $csv);
    }

    #[DataProvider('rolesWithoutReportAccess')]
    public function test_unprivileged_roles_cannot_view_the_report_or_record_expenses(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('reports.debit-credit'))
            ->assertForbidden();
        $this->post(route('expenses.store'), [
            'category' => 'utilities',
            'description' => 'Tagihan',
            'amount' => '1000',
            'transaction_date' => today()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseCount('expenses', 0);
    }

    public static function rolesWithoutReportAccess(): array
    {
        return [
            'customer' => [User::ROLE_CUSTOMER],
            'operator' => [User::ROLE_OPERATOR],
        ];
    }

    public function test_guest_is_redirected_from_report_and_expense_routes(): void
    {
        $this->get(route('reports.debit-credit'))
            ->assertRedirectToRoute('login');
        $this->post(route('expenses.store'), [])
            ->assertRedirectToRoute('login');

        $this->assertDatabaseCount('expenses', 0);
    }
}
