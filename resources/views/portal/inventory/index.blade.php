@extends('layouts.portal')

@section('title', 'Persediaan')
@section('section', 'Persediaan')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">Bahan baku &amp; pergerakan</p>
            <h1>Persediaan dapur.</h1>
            <p class="portal-heading-copy">{{ $items->total() }} bahan tercatat · {{ $lowStockCount }} perlu diperiksa</p>
        </div>
        @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
            <a class="portal-primary-button" href="#catat-mutasi">Catat mutasi <span aria-hidden="true">&darr;</span></a>
        @endif
    </div>

    @if (auth()->user()->role === 'admin')
        <section class="portal-panel portal-form-panel">
            <div class="portal-panel-heading"><div><p class="portal-eyebrow">Master data</p><h2>Tambah bahan baku</h2></div></div>
            <form class="portal-inline-form" method="post" action="{{ route('inventory.store') }}">
                @csrf
                <label class="portal-field"><span>Nama bahan</span><input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Contoh: Cabai merah"></label>
                <label class="portal-field"><span>Satuan</span><input name="unit" value="{{ old('unit') }}" required maxlength="30" placeholder="kg, liter, butir"></label>
                <label class="portal-field"><span>Batas minimum</span><input name="minimum_quantity" type="number" step="0.001" min="0" value="{{ old('minimum_quantity', '0') }}" required></label>
                <button class="portal-primary-button" type="submit">Tambah bahan <span aria-hidden="true">+</span></button>
            </form>
        </section>
    @endif

    @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
        <section class="portal-panel portal-form-panel" id="catat-mutasi">
            <div class="portal-panel-heading"><div><p class="portal-eyebrow">Perubahan saldo</p><h2>Catat stok masuk / keluar</h2></div></div>
            <form class="portal-inline-form movement-form" method="post" action="{{ route('stock-movements.store') }}">
                @csrf
                <label class="portal-field"><span>Bahan baku</span>
                    <select name="inventory_item_id" required>
                        <option value="">Pilih bahan</option>
                        @foreach ($activeItems as $item)
                            <option value="{{ $item->id }}" @selected(old('inventory_item_id') == $item->id)>{{ $item->name }} · {{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->unit }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="portal-field"><span>Jenis mutasi</span><select name="type" required><option value="in" @selected(old('type') === 'in')>Stok masuk</option><option value="out" @selected(old('type') === 'out')>Stok keluar</option></select></label>
                <label class="portal-field"><span>Jumlah</span><input name="quantity" type="number" step="0.001" min="0.001" max="999999999.999" value="{{ old('quantity') }}" required placeholder="0,000"></label>
                <label class="portal-field movement-note"><span>Catatan</span><input name="note" value="{{ old('note') }}" maxlength="500" placeholder="Contoh: pembelian pagi ini"></label>
                <button class="portal-primary-button" type="submit">Simpan mutasi <span aria-hidden="true">&rarr;</span></button>
            </form>
        </section>
    @endif

    <section class="portal-panel">
        <div class="portal-panel-heading"><div><p class="portal-eyebrow">Daftar bahan</p><h2>Saldo persediaan</h2></div><span class="portal-total-label">{{ $items->total() }} bahan</span></div>
        <div class="portal-table-wrap">
            <table class="portal-table inventory-table">
                <thead><tr><th>Bahan baku</th><th>Saldo saat ini</th><th>Minimum</th><th>Kondisi</th>@if (auth()->user()->role === 'admin')<th>Pengaturan</th>@endif</tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><strong>{{ $item->name }}</strong><small class="table-subline">{{ $item->unit }}</small></td>
                            <td class="quantity-cell">{{ number_format((float) $item->quantity, 3, ',', '.') }} <small>{{ $item->unit }}</small></td>
                            <td>{{ number_format((float) $item->minimum_quantity, 3, ',', '.') }} <small>{{ $item->unit }}</small></td>
                            <td>
                                @if (! $item->is_active)
                                    <span class="portal-status status-inactive">Nonaktif</span>
                                @elseif ((float) $item->quantity <= (float) $item->minimum_quantity)
                                    <span class="portal-status status-low">Menipis</span>
                                @else
                                    <span class="portal-status status-ready">Aman</span>
                                @endif
                            </td>
                            @if (auth()->user()->role === 'admin')
                                <td>
                                    <form class="inventory-edit-form" method="post" action="{{ route('inventory.update', $item) }}">
                                        @csrf
                                        @method('PUT')
                                        <input name="name" value="{{ $item->name }}" aria-label="Nama bahan {{ $item->name }}" required maxlength="255">
                                        <input name="unit" value="{{ $item->unit }}" aria-label="Satuan bahan {{ $item->name }}" required maxlength="30">
                                        <input name="minimum_quantity" type="number" step="0.001" min="0" value="{{ $item->minimum_quantity }}" aria-label="Batas minimum {{ $item->name }}" required>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="active-toggle"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Aktif</label>
                                        <button class="portal-small-button" type="submit">Simpan</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="{{ auth()->user()->role === 'admin' ? 5 : 4 }}">Belum ada bahan baku yang dicatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-pagination">{{ $items->links() }}</div>
    </section>

    <section class="portal-panel portal-history-panel">
        <div class="portal-panel-heading"><div><p class="portal-eyebrow">Jejak audit</p><h2>Riwayat mutasi</h2></div></div>
        <div class="portal-table-wrap inventory-audit-scroll" role="region" aria-label="Riwayat mutasi persediaan" tabindex="0">
            <table class="portal-table">
                <thead><tr><th>Waktu</th><th>Bahan</th><th>Jenis</th><th>Jumlah</th><th>Saldo akhir</th><th>Petugas</th><th>Catatan</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('d/m/Y H:i') }} WIB</td>
                            <td><strong>{{ $movement->inventoryItem->name }}</strong></td>
                            <td><span class="portal-status {{ $movement->type === 'in' ? 'status-ready' : 'status-low' }}">{{ $movement->type === 'in' ? 'Masuk' : 'Keluar' }}</span></td>
                            <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }} {{ $movement->inventoryItem->unit }}</td>
                            <td>{{ number_format((float) $movement->quantity_after, 3, ',', '.') }} {{ $movement->inventoryItem->unit }}</td>
                            <td>{{ $movement->user?->name ?? 'Pengguna dihapus' }}</td>
                            <td>{{ $movement->note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="7">Belum ada mutasi stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-pagination">{{ $movements->links() }}</div>
    </section>
@endsection