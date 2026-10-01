<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuManagementController extends Controller
{
    public function index(): View
    {
        $menus = Menu::query()
            ->orderBy('nama_menu')
            ->paginate(15);

        return view('portal.menus.index', compact('menus'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $ownerId = Owner::query()->value('id');

        if ($ownerId === null) {
            return back()->withErrors(['menu' => 'Data pemilik belum tersedia untuk menyimpan menu.']);
        }

        Menu::create([
            ...$validated,
            'owner_id' => $ownerId,
            'tersedia' => $request->boolean('tersedia'),
        ]);

        return redirect()->route('menus.index')->with('status', 'Menu berhasil ditambahkan.');
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $menu->update([
            ...$validated,
            'tersedia' => $request->boolean('tersedia'),
        ]);

        return redirect()->route('menus.index')->with('status', 'Menu berhasil diperbarui.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'nama_menu' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'harga' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'gambar' => ['nullable', 'url', 'max:255'],
            'tersedia' => ['sometimes', 'boolean'],
        ];
    }
}
