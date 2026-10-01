<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => Owner::factory(),
            'nama_menu' => fake()->randomElement(['Nasi Goreng Kampung', 'Ayam Bakar Madu', 'Mie Goreng Jawa']),
            'deskripsi' => fake()->sentence(),
            'harga' => fake()->numberBetween(150, 450) * 100,
            'kategori' => fake()->randomElement(['Hidangan Utama', 'Camilan', 'Minuman']),
            'gambar' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=85',
            'tersedia' => true,
        ];
    }
}
