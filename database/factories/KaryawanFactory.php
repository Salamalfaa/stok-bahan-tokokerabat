<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Karyawan>
 */
class KaryawanFactory extends Factory
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
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'no_telepon' => fake()->phoneNumber(),
            'jabatan' => fake()->randomElement(['Pelayan', 'Kasir', 'Koki']),
            'aktif' => true,
        ];
    }
}
