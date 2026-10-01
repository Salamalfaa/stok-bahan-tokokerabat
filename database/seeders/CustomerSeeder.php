<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            ['nama' => 'Nadia Putri', 'email' => 'nadia@example.test', 'no_telepon' => '081234567801'],
            ['nama' => 'Raka Pratama', 'email' => 'raka@example.test', 'no_telepon' => '081234567802'],
            ['nama' => 'Dina Maharani', 'email' => 'dina@example.test', 'no_telepon' => '081234567803'],
            ['nama' => 'Bima Santoso', 'email' => 'bima@example.test', 'no_telepon' => '081234567804'],
            ['nama' => 'Sari Wulandari', 'email' => 'sari@example.test', 'no_telepon' => '081234567805'],
        ];

        foreach ($customers as $customer) {
            Customer::factory()->create($customer);
        }
    }
}
