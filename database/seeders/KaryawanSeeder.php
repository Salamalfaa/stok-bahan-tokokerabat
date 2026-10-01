<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\Owner;
use Illuminate\Database\Seeder;

class KaryawanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = Owner::query()->firstOrFail();

        Karyawan::factory()->count(3)->create([
            'owner_id' => $owner->id,
        ]);
    }
}
