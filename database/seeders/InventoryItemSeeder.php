<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Beras', 'unit' => 'kg', 'quantity' => 24, 'minimum_quantity' => 10],
            ['name' => 'Ayam', 'unit' => 'kg', 'quantity' => 8.5, 'minimum_quantity' => 5],
            ['name' => 'Minyak goreng', 'unit' => 'liter', 'quantity' => 6, 'minimum_quantity' => 8],
            ['name' => 'Telur', 'unit' => 'butir', 'quantity' => 90, 'minimum_quantity' => 30],
            ['name' => 'Tepung terigu', 'unit' => 'kg', 'quantity' => 12, 'minimum_quantity' => 4],
            ['name' => 'Kopi arabika', 'unit' => 'kg', 'quantity' => 2, 'minimum_quantity' => 3],
            ['name' => 'Gula aren', 'unit' => 'kg', 'quantity' => 5, 'minimum_quantity' => 2],
        ];

        foreach ($items as $item) {
            $inventoryItem = InventoryItem::query()->firstOrCreate(
                ['name' => $item['name']],
                [...$item, 'is_active' => true],
            );

            if (! $inventoryItem->stockMovements()->exists() && (float) $inventoryItem->quantity > 0) {
                StockMovement::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'type' => 'in',
                    'quantity' => $inventoryItem->quantity,
                    'quantity_before' => '0.000',
                    'quantity_after' => $inventoryItem->quantity,
                    'note' => 'Saldo awal persediaan',
                ]);
            }
        }
    }
}
