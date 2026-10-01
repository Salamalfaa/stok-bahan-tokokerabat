<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Karyawan;
use App\Models\Pesanan;
use App\Models\Reservasi;
use Illuminate\Database\Seeder;

class PesananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::query()->take(3)->get();
        $karyawan = Karyawan::query()->firstOrFail();

        $customers->each(function (Customer $customer, int $index) use ($karyawan): void {
            $reservasi = Reservasi::query()
                ->where('customer_id', $customer->id)
                ->first();

            Pesanan::factory()->for($customer)->create([
                'reservasi_id' => $reservasi?->id,
                'karyawan_id' => $karyawan->id,
                'status' => ['selesai', 'diproses', 'menunggu'][$index],
            ]);
        });
    }
}
