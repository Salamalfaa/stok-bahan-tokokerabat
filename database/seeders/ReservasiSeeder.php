<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Reservasi;
use Illuminate\Database\Seeder;

class ReservasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::query()->take(3)->get()->each(function (Customer $customer, int $index): void {
            Reservasi::factory()->for($customer)->create([
                'tanggal_reservasi' => now()->addDays($index + 1)->toDateString(),
                'waktu_reservasi' => ['18:00:00', '18:30:00', '19:00:00'][$index],
                'jumlah_orang' => $index + 2,
                'status' => $index === 0 ? 'dikonfirmasi' : 'menunggu',
            ]);
        });
    }
}
