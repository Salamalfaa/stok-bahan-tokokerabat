<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pesanan;
use App\Models\Reservasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_orders_archive_after_24_hours_while_unfinished_orders_stay_active(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $recentCompletedOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-COMPLETED-23H',
            'status' => 'selesai',
            'created_at' => now()->subHours(23),
        ]);
        $archivedOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-COMPLETED-24H',
            'status' => 'selesai',
            'created_at' => now()->subHours(24),
        ]);
        $unfinishedOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-UNFINISHED-OLD',
            'status' => 'diproses',
            'created_at' => now()->subDays(3),
        ]);

        $this->actingAs($operator)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee($recentCompletedOrder->kode_pesanan)
            ->assertSee($unfinishedOrder->kode_pesanan)
            ->assertDontSee($archivedOrder->kode_pesanan);
    }

    public function test_archive_lists_only_terminal_orders_on_the_selected_calendar_date(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $selectedDateOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-ARCHIVE-SELECTED-DATE',
            'status' => 'dibatalkan',
            'created_at' => now()->subHours(24),
        ]);
        $otherDateOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-ARCHIVE-OTHER-DATE',
            'status' => 'selesai',
            'created_at' => now()->subDays(2),
        ]);
        $recentOrder = Pesanan::factory()->create([
            'kode_pesanan' => 'TK-ARCHIVE-TOO-RECENT',
            'status' => 'selesai',
            'created_at' => now()->subHours(23),
        ]);

        $this->actingAs($operator)
            ->get(route('orders.archive', ['date' => '2026-09-27']))
            ->assertOk()
            ->assertSee('Arsip pesanan')
            ->assertSee('2026-09-27')
            ->assertSee($selectedDateOrder->kode_pesanan)
            ->assertDontSee($otherDateOrder->kode_pesanan)
            ->assertDontSee($recentOrder->kode_pesanan)
            ->assertDontSee('Unduh rekap');
        $this->assertStringContainsString(
            route('orders.archive.preview', ['date' => '2026-09-27']),
            $this->get(route('orders.archive', ['date' => '2026-09-27']))->getContent(),
        );
    }

    public function test_archive_requires_a_valid_date_not_in_the_future(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get(route('orders.archive', ['date' => '2026-09-29']))
            ->assertSessionHasErrors('date');
    }

    public function test_administrator_can_download_csv_for_the_selected_archived_date(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = Customer::factory()->create([
            'nama' => 'Pelanggan Rekap',
            'no_telepon' => '081234567890',
        ]);
        Pesanan::factory()->for($customer)->create([
            'kode_pesanan' => 'TK-CSV-ARCHIVE',
            'status' => 'selesai',
            'total_harga' => '45000.00',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('orders.archive', ['date' => '2026-09-27']))
            ->assertOk()
            ->assertSee('Lihat rekap')
            ->assertDontSee('Unduh rekap');

        $this->get(route('orders.archive.preview', ['date' => '2026-09-27']))
            ->assertOk()
            ->assertSee('Rekap pesanan')
            ->assertSee('Cetak laporan')
            ->assertSee('Unduh CSV')
            ->assertSee(route('orders.archive.export', ['date' => '2026-09-27']), false);

        $response = $this->actingAs($admin)
            ->get(route('orders.archive.export', ['date' => '2026-09-27']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=rekap-pesanan-2026-09-27.csv');

        $this->assertStringContainsString('TK-CSV-ARCHIVE', $response->streamedContent());
        $this->assertStringContainsString('Pelanggan Rekap', $response->streamedContent());
        $this->assertStringContainsString('45000.00', $response->streamedContent());
    }

    public function test_operator_cannot_download_archived_order_csv(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get(route('orders.archive.export', ['date' => '2026-09-27']))
            ->assertForbidden();
    }

    public function test_operator_can_preview_archived_order_report_without_download_control(): void
    {
        $this->travelTo('2026-09-28 12:00:00');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $customer = Customer::factory()->create(['nama' => 'Pelanggan Pratinjau']);
        Pesanan::factory()->for($customer)->create([
            'kode_pesanan' => 'TK-ORDER-PREVIEW',
            'status' => 'selesai',
            'total_harga' => '35000.00',
            'created_at' => now()->subDay(),
        ]);
        Pesanan::factory()->for($customer)->create([
            'kode_pesanan' => 'TK-CANCELLED-PREVIEW',
            'status' => 'dibatalkan',
            'total_harga' => '20000.00',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($operator)
            ->get(route('orders.archive.preview', ['date' => '2026-09-27']))
            ->assertOk()
            ->assertSee('Rekap pesanan')
            ->assertSee('TK-ORDER-PREVIEW')
            ->assertSee('TK-CANCELLED-PREVIEW')
            ->assertSee('Pelanggan Pratinjau')
            ->assertSee('Rp35.000')
            ->assertSee('Cetak laporan')
            ->assertDontSee('Unduh CSV');
    }

    public function test_active_order_page_has_calendar_archive_control(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Riwayat arsip')
            ->assertSee('type="date"', false)
            ->assertSee(route('orders.archive'), false);
    }

    public function test_order_list_links_to_a_printable_receipt_with_order_details_and_timestamp(): void
    {
        $this->travelTo('2026-09-29 11:05:00');
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $customer = Customer::factory()->create([
            'nama' => 'Bagas Satria',
            'no_telepon' => '081234567890',
        ]);
        $order = Pesanan::factory()->for($customer)->create([
            'kode_pesanan' => 'TK-01M3NNDP0P7H3W2N4N0EA406TJ',
            'total_harga' => '50000.00',
            'status' => 'diproses',
            'created_at' => now(),
        ]);
        $order->detailPesanans()->create([
            'nama_menu' => 'Pisang Goreng Kerabat',
            'jumlah' => 1,
            'harga_satuan' => '15000.00',
            'subtotal' => '15000.00',
        ]);
        $order->detailPesanans()->create([
            'nama_menu' => 'Nasi Goreng Kampung',
            'jumlah' => 1,
            'harga_satuan' => '35000.00',
            'subtotal' => '35000.00',
        ]);

        $this->actingAs($operator)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee(route('orders.receipt', $order), false)
            ->assertSee('Cetak struk');

        $this->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('Bagas Satria')
            ->assertSee('081234567890')
            ->assertSee('Pisang Goreng Kerabat')
            ->assertSee('Nasi Goreng Kampung')
            ->assertSee('Rp15.000')
            ->assertSee('Rp35.000')
            ->assertSee('Rp50.000')
            ->assertSee($order->kode_pesanan)
            ->assertSee('29/09/2026 11:05 WIB')
            ->assertSee('window.print()');
    }

    public function test_order_receipt_requires_an_internal_staff_role(): void
    {
        $order = Pesanan::factory()->create();

        $this->get(route('orders.receipt', $order))
            ->assertRedirectToRoute('login');

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('orders.receipt', $order))
            ->assertForbidden();
    }

    public function test_operator_can_update_reservation_status_from_the_management_view(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $reservation = Reservasi::factory()->create([
            'status' => 'menunggu',
        ]);

        $this->actingAs($operator)
            ->patch(route('reservations.status.update', $reservation), ['status' => 'selesai'])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('status', 'Status reservasi berhasil diperbarui.');

        $this->assertDatabaseHas('reservasis', [
            'id' => $reservation->id,
            'status' => 'selesai',
        ]);
    }

    public function test_operator_can_delete_a_reservation_from_the_management_view(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $reservation = Reservasi::factory()->create();

        $this->actingAs($operator)
            ->delete(route('reservations.destroy', $reservation))
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('status', 'Reservasi berhasil dihapus.');

        $this->assertDatabaseMissing('reservasis', ['id' => $reservation->id]);
    }
}
