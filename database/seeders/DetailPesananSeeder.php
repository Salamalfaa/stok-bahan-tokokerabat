<?php

namespace Database\Seeders;

use App\Models\DetailPesanan;
use App\Models\Menu;
use App\Models\Pesanan;
use Illuminate\Database\Seeder;

class DetailPesananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = Menu::query()->get();

        Pesanan::query()->get()->each(function (Pesanan $pesanan, int $orderIndex) use ($menus): void {
            $total = 0;

            foreach ([0, 1] as $lineIndex) {
                $menu = $menus[($orderIndex + $lineIndex) % $menus->count()];
                $quantity = $lineIndex + 1;
                $unitPriceInCents = (int) round((float) $menu->harga * 100);
                $subtotalInCents = $unitPriceInCents * $quantity;
                $total += $subtotalInCents;

                DetailPesanan::factory()->for($pesanan, 'pesanan')->create([
                    'menu_id' => $menu->id,
                    'nama_menu' => $menu->nama_menu,
                    'jumlah' => $quantity,
                    'harga_satuan' => number_format($unitPriceInCents / 100, 2, '.', ''),
                    'subtotal' => number_format($subtotalInCents / 100, 2, '.', ''),
                ]);
            }

            $pesanan->update([
                'total_harga' => number_format($total / 100, 2, '.', ''),
            ]);
        });
    }
}
