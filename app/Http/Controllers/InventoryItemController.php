<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(): View
    {
        $items = InventoryItem::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20);
        $activeItems = InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $movements = StockMovement::query()
            ->with(['inventoryItem', 'user'])
            ->latest()
            ->paginate(12, ['*'], 'movements_page');

        $lowStockCount = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->count();

        return view('portal.inventory.index', compact('items', 'activeItems', 'movements', 'lowStockCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:inventory_items,name'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
        ]);

        InventoryItem::create([
            ...$validated,
            'quantity' => 0,
            'is_active' => true,
        ]);

        return redirect()->route('inventory.index')->with('status', 'Bahan baku berhasil ditambahkan.');
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_items', 'name')->ignore($inventoryItem)],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $inventoryItem->update($validated);

        return redirect()->route('inventory.index')->with('status', 'Data bahan baku berhasil diperbarui.');
    }
}
