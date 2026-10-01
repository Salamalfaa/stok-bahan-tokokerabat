<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OwnerSeeder::class,
            KaryawanSeeder::class,
            CustomerSeeder::class,
            MenuSeeder::class,
            InventoryItemSeeder::class,
            ReservasiSeeder::class,
            PesananSeeder::class,
            DetailPesananSeeder::class,
        ]);
    }
}
