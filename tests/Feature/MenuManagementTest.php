<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_menu_to_the_public_catalog(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = Owner::factory()->create();

        $this->actingAs($admin)->post(route('menus.store'), [
            'nama_menu' => 'Sup Sayur Kerabat',
            'deskripsi' => 'Sup hangat dengan sayuran segar.',
            'harga' => '23500.50',
            'kategori' => 'Hidangan utama',
            'gambar' => 'https://images.unsplash.com/example.jpg',
            'tersedia' => '1',
        ])->assertRedirectToRoute('menus.index');

        $this->assertDatabaseHas('menus', [
            'owner_id' => $owner->id,
            'nama_menu' => 'Sup Sayur Kerabat',
            'harga' => '23500.50',
            'tersedia' => true,
        ]);
    }

    public function test_admin_can_mark_an_existing_menu_unavailable(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $menu = Menu::factory()->create(['tersedia' => true]);

        $this->actingAs($admin)->put(route('menus.update', $menu), [
            'nama_menu' => $menu->nama_menu,
            'deskripsi' => $menu->deskripsi,
            'harga' => $menu->harga,
            'kategori' => $menu->kategori,
            'gambar' => $menu->gambar,
            'tersedia' => '0',
        ])->assertRedirectToRoute('menus.index');

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'tersedia' => false,
        ]);
    }

    public function test_operator_can_view_the_menu_catalog_with_create_and_edit_controls(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $menu = Menu::factory()->create(['nama_menu' => 'Nasi Bakar Operator']);

        $this->actingAs($operator)
            ->get(route('menus.index'))
            ->assertOk()
            ->assertSee('Nasi Bakar Operator')
            ->assertSee('Tambah menu')
            ->assertSee('Edit menu')
            ->assertSee(route('menus.store'), false)
            ->assertSee(route('menus.update', $menu), false);
    }

    public function test_operator_can_create_and_update_menu_catalog_entries(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $owner = Owner::factory()->create();
        $menu = Menu::factory()->create();

        $this->actingAs($operator)->post(route('menus.store'), [
            'nama_menu' => 'Menu Operator',
            'deskripsi' => 'Menu yang ditambahkan oleh operator.',
            'harga' => '25000',
            'kategori' => 'Makanan',
        ])->assertRedirectToRoute('menus.index');

        $this->put(route('menus.update', $menu), [
            'nama_menu' => 'Menu Diperbarui Operator',
            'deskripsi' => $menu->deskripsi,
            'harga' => '25000',
            'kategori' => $menu->kategori,
        ])->assertRedirectToRoute('menus.index');

        $this->assertDatabaseHas('menus', [
            'nama_menu' => 'Menu Operator',
        ]);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'nama_menu' => 'Menu Diperbarui Operator',
        ]);
    }

    public function test_owner_cannot_open_the_menu_management_catalog(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('menus.index'))
            ->assertForbidden();
    }
}
