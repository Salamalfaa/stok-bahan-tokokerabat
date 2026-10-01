<?php

namespace Database\Factories;

use App\Models\DetailPesanan;
use App\Models\Menu;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetailPesanan>
 */
class DetailPesananFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jumlah = fake()->numberBetween(1, 4);
        $hargaSatuan = fake()->numberBetween(150, 450) * 100;

        return [
            'pesanan_id' => Pesanan::factory(),
            'menu_id' => Menu::factory(),
            'nama_menu' => fake()->words(2, true),
            'jumlah' => $jumlah,
            'harga_satuan' => $hargaSatuan,
            'subtotal' => $jumlah * $hargaSatuan,
        ];
    }
}
