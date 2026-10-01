<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pesanan>
 */
class PesananFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_pesanan' => 'TK-'.Str::upper((string) Str::ulid()),
            'customer_id' => Customer::factory(),
            'reservasi_id' => null,
            'karyawan_id' => null,
            'total_harga' => 0,
            'status' => 'menunggu',
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
