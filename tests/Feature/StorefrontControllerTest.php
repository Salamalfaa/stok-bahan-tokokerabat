<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Menu;
use App\Models\Pesanan;
use App\Models\Reservasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_order_uses_database_price_and_persists_order_details(): void
    {
        $menu = Menu::factory()->create([
            'nama_menu' => 'Nasi Goreng Kerabat',
            'harga' => '12500.50',
        ]);

        $response = $this->postJson(route('pesanans.store'), [
            'nama' => 'Pelanggan Tes',
            'email' => 'pelanggan@example.test',
            'no_telepon' => '081234567890',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonPath('message', 'Pesanan Anda berhasil diterima.');

        $this->assertDatabaseHas('pesanans', [
            'customer_id' => 1,
            'total_harga' => '25001.00',
            'status' => 'menunggu',
        ]);
        $this->assertDatabaseHas('detail_pesanans', [
            'nama_menu' => 'Nasi Goreng Kerabat',
            'jumlah' => 2,
            'harga_satuan' => '12500.50',
            'subtotal' => '25001.00',
        ]);
    }

    public function test_order_with_unavailable_menu_is_rejected_without_creating_records(): void
    {
        $menu = Menu::factory()->create(['tersedia' => false]);

        $response = $this->postJson(route('pesanans.store'), [
            'nama' => 'Pelanggan Tes',
            'no_telepon' => '081234567890',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertJsonValidationErrors('items.0.menu_id');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('pesanans', 0);
        $this->assertDatabaseCount('detail_pesanans', 0);
    }

    public function test_guest_order_does_not_attach_to_a_registered_customer_account(): void
    {
        $user = User::factory()->create([
            'email' => 'registered@example.test',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $accountCustomer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $menu = Menu::factory()->create();

        $response = $this->postJson(route('pesanans.store'), [
            'nama' => 'Guest Buyer',
            'email' => $user->email,
            'no_telepon' => '081234567891',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();

        $guestOrder = Pesanan::query()->firstOrFail();

        $this->assertNotSame($accountCustomer->id, $guestOrder->customer_id);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_guest_order_does_not_copy_a_registered_email_to_an_unlinked_phone_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'registered@example.test',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $accountCustomer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $guestCustomer = Customer::factory()->create([
            'user_id' => null,
            'email' => null,
            'no_telepon' => '081234567891',
        ]);
        $menu = Menu::factory()->create();

        $response = $this->postJson(route('pesanans.store'), [
            'nama' => 'Guest Buyer',
            'email' => $user->email,
            'no_telepon' => $guestCustomer->no_telepon,
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();

        $this->assertSame($guestCustomer->id, Pesanan::query()->firstOrFail()->customer_id);
        $this->assertNull($guestCustomer->refresh()->email);
        $this->assertSame($user->email, $accountCustomer->refresh()->email);
    }

    public function test_valid_reservation_is_saved_and_returns_confirmation(): void
    {
        $response = $this->from(route('home'))->post(route('reservasis.store'), [
            'nama' => 'Pelanggan Tes',
            'email' => 'reservasi@example.test',
            'no_telepon' => '081234567890',
            'tanggal_reservasi' => now()->addDay()->toDateString(),
            'waktu_reservasi' => '18:30',
            'jumlah_orang' => 4,
            'catatan' => 'Kursi dekat jendela',
        ]);

        $response->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Permintaan reservasi diterima. Kami akan segera menghubungi Anda.');

        $this->assertDatabaseHas('reservasis', [
            'customer_id' => 1,
            'jumlah_orang' => 4,
            'status' => 'menunggu',
            'catatan' => 'Kursi dekat jendela',
        ]);

        $reservation = Reservasi::query()->firstOrFail();

        $this->assertSame(now()->addDay()->toDateString(), $reservation->tanggal_reservasi->toDateString());
        $this->assertSame('18:30', $reservation->getRawOriginal('waktu_reservasi'));
    }

    public function test_reservation_rejects_yesterday_in_jakarta_before_utc_midnight(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 27)->setTime(18, 0));

        $this->get(route('home'))->assertSee('min="2026-09-28"', false);

        $this->from(route('home'))->post(route('reservasis.store'), [
            'nama' => 'Pelanggan Tes',
            'no_telepon' => '081234567890',
            'tanggal_reservasi' => now('UTC')->toDateString(),
            'waktu_reservasi' => '18:30',
            'jumlah_orang' => 4,
        ])->assertRedirectToRoute('home')->assertSessionHasErrors('tanggal_reservasi');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('reservasis', 0);
    }
}
