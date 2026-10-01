<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Reservasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservasi>
 */
class ReservasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'tanggal_reservasi' => fake()->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'waktu_reservasi' => fake()->time('H:i:s'),
            'jumlah_orang' => fake()->numberBetween(1, 8),
            'status' => 'menunggu',
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
