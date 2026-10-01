<?php

namespace Database\Seeders;

use App\Models\Owner;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Owner::factory()->create([
            'nama' => 'Toko Kerabat',
            'email' => 'halo@tokokerabat.test',
            'no_telepon' => '081234567890',
        ]);
    }
}
