<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DetailPesanan;
use App\Models\Karyawan;
use App\Models\Menu;
use App\Models\Owner;
use App\Models\Pesanan;
use App\Models\Reservasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_sample_data_in_foreign_key_order(): void
    {
        $this->seed();

        $this->assertDatabaseCount('owners', 1);
        $this->assertDatabaseCount('karyawans', 3);
        $this->assertDatabaseCount('customers', 5);
        $this->assertDatabaseCount('menus', 6);
        $this->assertDatabaseCount('reservasis', 3);
        $this->assertDatabaseCount('pesanans', 3);
        $this->assertDatabaseCount('detail_pesanans', 6);
    }

    public function test_factories_create_models_with_their_related_parents(): void
    {
        $employee = Karyawan::factory()->create();
        $reservation = Reservasi::factory()->create();
        $order = Pesanan::factory()->create();
        $detail = DetailPesanan::factory()->create();

        $this->assertInstanceOf(Owner::class, $employee->owner);
        $this->assertInstanceOf(Customer::class, $reservation->customer);
        $this->assertInstanceOf(Customer::class, $order->customer);
        $this->assertInstanceOf(Pesanan::class, $detail->pesanan);
        $this->assertInstanceOf(Menu::class, $detail->menu);
    }

    public function test_homepage_displays_available_menus_from_the_database(): void
    {
        Menu::factory()->create(['nama_menu' => 'Nasi Uduk Pagi']);
        Menu::factory()->create([
            'nama_menu' => 'Menu Tidak Tersedia',
            'tersedia' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Nasi Uduk Pagi')
            ->assertDontSee('Menu Tidak Tersedia');
    }

    public function test_guest_can_submit_a_reservation(): void
    {
        $this->post(route('reservasis.store'), [
            'nama' => 'Alya Putri',
            'email' => 'alya@example.test',
            'no_telepon' => '081234567899',
            'tanggal_reservasi' => now()->addDay()->toDateString(),
            'waktu_reservasi' => '18:30',
            'jumlah_orang' => 4,
            'catatan' => 'Meja dekat jendela',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'nama' => 'Alya Putri',
            'no_telepon' => '081234567899',
        ]);
        $reservation = Reservasi::query()->firstOrFail();

        $this->assertSame(now()->addDay()->toDateString(), $reservation->tanggal_reservasi->toDateString());
        $this->assertStringStartsWith('18:30', $reservation->waktu_reservasi);
        $this->assertSame(4, $reservation->jumlah_orang);
        $this->assertSame('menunggu', $reservation->status);
    }

    public function test_returning_customer_can_make_a_reservation_and_order(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'returning@example.test',
        ]);
        $menu = Menu::factory()->create();

        $this->post(route('reservasis.store'), [
            'nama' => 'Pelanggan Lama',
            'email' => 'returning@example.test',
            'no_telepon' => '081234567800',
            'tanggal_reservasi' => now()->addDay()->toDateString(),
            'waktu_reservasi' => '18:30',
            'jumlah_orang' => 2,
        ])->assertRedirect();

        $orderResponse = $this->postJson(route('pesanans.store'), [
            'nama' => 'Pelanggan Lama',
            'email' => 'returning@example.test',
            'no_telepon' => '081234567800',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ]);

        $orderResponse->assertCreated();
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('reservasis', [
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('pesanans', [
            'customer_id' => $customer->id,
            'kode_pesanan' => $orderResponse->json('code'),
        ]);
    }

    public function test_guest_can_submit_an_order_using_database_menu_prices(): void
    {
        $menu = Menu::factory()->create(['harga' => 22500]);

        $response = $this->postJson(route('pesanans.store'), [
            'nama' => 'Alya Putri',
            'no_telepon' => '081234567898',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'code']);

        $this->assertDatabaseHas('pesanans', [
            'kode_pesanan' => $response->json('code'),
            'total_harga' => '45000.00',
            'status' => 'menunggu',
        ]);
        $this->assertDatabaseHas('detail_pesanans', [
            'menu_id' => $menu->id,
            'nama_menu' => $menu->nama_menu,
            'jumlah' => 2,
            'harga_satuan' => '22500.00',
            'subtotal' => '45000.00',
        ]);
    }

    public function test_guest_cannot_order_a_menu_that_is_not_available(): void
    {
        $menu = Menu::factory()->create(['tersedia' => false]);

        $this->postJson(route('pesanans.store'), [
            'nama' => 'Alya Putri',
            'no_telepon' => '081234567897',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.menu_id');

        $this->assertDatabaseCount('pesanans', 0);
    }
}
