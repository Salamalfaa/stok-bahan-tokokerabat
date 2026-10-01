<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Pesanan;
use App\Models\Reservasi;
use App\Models\User;
use Database\Seeders\InventoryItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stock_in_and_out_are_recorded_with_exact_balances(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $item = InventoryItem::query()->create([
            'name' => 'Tepung',
            'unit' => 'kg',
            'quantity' => '10.000',
            'minimum_quantity' => '2.000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('stock-movements.store'), [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => '2.375',
            'note' => 'Pembelian pagi',
        ])->assertRedirectToRoute('inventory.index');

        $this->post(route('stock-movements.store'), [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => '1.500',
            'note' => 'Persiapan dapur',
        ])->assertRedirectToRoute('inventory.index');

        $this->assertSame('10.875', $item->refresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'user_id' => $admin->id,
            'type' => 'out',
            'quantity' => '1.500',
            'quantity_before' => '12.375',
            'quantity_after' => '10.875',
        ]);
    }

    public function test_operator_cannot_remove_more_stock_than_is_available(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $item = InventoryItem::query()->create([
            'name' => 'Susu',
            'unit' => 'liter',
            'quantity' => '1.000',
            'minimum_quantity' => '0.500',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('inventory.index'))
            ->post(route('stock-movements.store'), [
                'inventory_item_id' => $item->id,
                'type' => 'out',
                'quantity' => '1.001',
            ])
            ->assertRedirectToRoute('inventory.index')
            ->assertSessionHasErrors('quantity');

        $this->assertSame('1.000', $item->refresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_owner_can_read_inventory_but_cannot_record_a_movement(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $item = InventoryItem::query()->create([
            'name' => 'Gula',
            'unit' => 'kg',
            'quantity' => '4.000',
            'minimum_quantity' => '1.000',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('aria-label="Riwayat mutasi persediaan"', false);
        $this->post(route('stock-movements.store'), [
            'inventory_item_id' => $item->id,
            'type' => 'in',
            'quantity' => '1',
        ])->assertForbidden();

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_customer_cannot_access_internal_inventory(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('inventory.index'))->assertForbidden();
    }

    public function test_operator_cannot_access_inventory_or_record_stock_movements(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $item = InventoryItem::query()->create([
            'name' => 'Beras terbatas',
            'unit' => 'kg',
            'quantity' => '3.000',
            'minimum_quantity' => '1.000',
            'is_active' => true,
        ]);

        $this->actingAs($operator)->get(route('inventory.index'))->assertForbidden();
        $this->post(route('stock-movements.store'), [
            'inventory_item_id' => $item->id,
            'type' => 'out',
            'quantity' => '1.000',
        ])->assertForbidden();

        $this->assertSame('3.000', $item->refresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_guest_is_redirected_to_login_from_internal_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirectToRoute('login');
    }

    public function test_owner_can_view_the_inventory_dashboard_and_analytics_report(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        InventoryItem::query()->create([
            'name' => 'Kopi untuk laporan',
            'unit' => 'kg',
            'quantity' => '1.000',
            'minimum_quantity' => '2.000',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kopi untuk laporan')
            ->assertSee('aria-label="Daftar bahan dengan stok menipis"', false)
            ->assertSee('aria-label="Daftar mutasi persediaan terbaru"', false);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan usaha.');
    }

    public function test_operator_can_review_and_update_order_status(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $order = Pesanan::factory()->create(['kode_pesanan' => 'TK-QUEUE-TEST']);

        $this->actingAs($operator)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('TK-QUEUE-TEST');

        $this->patch(route('orders.status.update', $order), ['status' => 'diproses'])
            ->assertRedirectToRoute('orders.index');

        $this->assertDatabaseHas('pesanans', [
            'id' => $order->id,
            'status' => 'diproses',
        ]);
    }

    public function test_operator_dashboard_hides_inventory_data_and_widgets(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        InventoryItem::query()->create([
            'name' => 'Bahan yang tidak boleh terlihat',
            'unit' => 'kg',
            'quantity' => '1.000',
            'minimum_quantity' => '2.000',
            'is_active' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pesanan menunggu')
            ->assertDontSee('Bahan aktif')
            ->assertDontSee('Stok menipis')
            ->assertDontSee('Mutasi persediaan')
            ->assertDontSee('Bahan yang tidak boleh terlihat');
    }

    public function test_dashboard_header_hides_reservation_alert_and_reservations_are_on_own_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = Customer::factory()->create([
            'nama' => 'M. Jamil Salam',
            'email' => 'salamsalamalfa@gmail.com',
        ]);
        Reservasi::query()->create([
            'customer_id' => $customer->id,
            'tanggal_reservasi' => now()->addDay()->toDateString(),
            'waktu_reservasi' => '19:00',
            'jumlah_orang' => 2,
            'status' => 'menunggu',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('portal-live-clock', false)
            ->assertSee('aria-label="Daftar pesanan terbaru"', false)
            ->assertDontSee('Reservasi menunggu', false)
            ->assertSee('|', false)
            ->assertDontSee('Reservasi terbaru', false);

        $this->actingAs($admin)
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertSee('Reservasi terbaru', false)
            ->assertSee('Jadwal reservasi', false)
            ->assertSee('M. Jamil Salam', false)
            ->assertSee('salamsalamalfa@gmail.com', false);
    }

    public function test_inventory_seeder_records_opening_stock_once_without_resetting_balances(): void
    {
        $seeder = app(InventoryItemSeeder::class);

        $seeder->run();
        InventoryItem::query()->where('name', 'Beras')->update(['quantity' => '17.250']);
        $seeder->run();

        $this->assertDatabaseCount('inventory_items', 7);
        $this->assertDatabaseCount('stock_movements', 7);
        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Beras',
            'quantity' => '17.250',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'in',
            'quantity_before' => '0.000',
            'note' => 'Saldo awal persediaan',
        ]);
    }
}
