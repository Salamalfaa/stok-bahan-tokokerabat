<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Owner;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = Owner::query()->firstOrFail();

        $menus = [
            ['nama_menu' => 'Nasi Goreng Kampung', 'deskripsi' => 'Nasi goreng wangi dengan telur mata sapi dan acar segar.', 'harga' => 32000, 'kategori' => 'Favorit', 'gambar' => 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=900&q=85'],
            ['nama_menu' => 'Ayam Bakar Madu', 'deskripsi' => 'Ayam bakar berbumbu rempah, manis gurih meresap sampai ke dalam.', 'harga' => 38000, 'kategori' => 'Hidangan Utama', 'gambar' => 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=900&q=85'],
            ['nama_menu' => 'Mie Goreng Jawa', 'deskripsi' => 'Mie kenyal, sayuran renyah, dan bumbu Jawa yang akrab di lidah.', 'harga' => 28000, 'kategori' => 'Hidangan Utama', 'gambar' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=900&q=85'],
            ['nama_menu' => 'Sate Ayam Bumbu Kacang', 'deskripsi' => 'Sate ayam empuk dengan saus kacang racikan dapur kami.', 'harga' => 34000, 'kategori' => 'Hidangan Utama', 'gambar' => 'https://images.unsplash.com/photo-1529563021893-cc83c992d75d?auto=format&fit=crop&w=900&q=85'],
            ['nama_menu' => 'Pisang Goreng Kerabat', 'deskripsi' => 'Pisang hangat berbalut tepung renyah dengan madu lokal.', 'harga' => 18000, 'kategori' => 'Camilan', 'gambar' => 'https://images.unsplash.com/photo-1587132137056-bfbf0166836e?auto=format&fit=crop&w=900&q=85'],
            ['nama_menu' => 'Es Kopi Gula Aren', 'deskripsi' => 'Kopi susu dingin dengan gula aren yang harum dan lembut.', 'harga' => 22000, 'kategori' => 'Minuman', 'gambar' => 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?auto=format&fit=crop&w=900&q=85'],
        ];

        foreach ($menus as $menu) {
            Menu::factory()->create([
                ...$menu,
                'owner_id' => $owner->id,
            ]);
        }
    }
}
