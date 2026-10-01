<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $item = InventoryItem::query()
                ->lockForUpdate()
                ->findOrFail($validated['inventory_item_id']);

            if (! $item->is_active) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Bahan baku nonaktif tidak dapat menerima mutasi stok.',
                ]);
            }

            $movementMilliunits = $this->toMilliunits((string) $validated['quantity']);
            $beforeMilliunits = $this->toMilliunits((string) $item->quantity);
            $afterMilliunits = $validated['type'] === 'in'
                ? $beforeMilliunits + $movementMilliunits
                : $beforeMilliunits - $movementMilliunits;

            if ($afterMilliunits < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok keluar melebihi persediaan yang tersedia.',
                ]);
            }

            if ($afterMilliunits > 999999999999) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah stok melampaui batas penyimpanan.',
                ]);
            }

            $item->update(['quantity' => $this->fromMilliunits($afterMilliunits)]);

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $this->fromMilliunits($movementMilliunits),
                'quantity_before' => $this->fromMilliunits($beforeMilliunits),
                'quantity_after' => $this->fromMilliunits($afterMilliunits),
                'note' => $validated['note'] ?? null,
            ]);
        }, attempts: 3);

        return redirect()->route('inventory.index')->with('status', 'Mutasi stok berhasil dicatat.');
    }

    private function toMilliunits(string $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function fromMilliunits(int $quantity): string
    {
        return number_format($quantity / 1000, 3, '.', '');
    }
}
