@extends('layouts.portal')

@section('title', 'Menu toko')
@section('section', 'Menu toko')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">Katalog publik</p><h1>Menu toko.</h1><p class="portal-heading-copy">Atur harga, kategori, foto, dan ketersediaan menu.</p></div>
        <span class="portal-total-label">{{ $menus->total() }} menu</span>
    </div>

    <section class="portal-panel portal-form-panel">
        <div class="portal-panel-heading"><div><p class="portal-eyebrow">Master data</p><h2>Tambah menu</h2></div></div>
        <form class="menu-create-form" method="post" action="{{ route('menus.store') }}">
            @csrf
            <label class="portal-field"><span>Nama menu</span><input name="nama_menu" value="{{ old('nama_menu') }}" required maxlength="255"></label>
            <label class="portal-field"><span>Kategori</span><input name="kategori" value="{{ old('kategori') }}" maxlength="255" placeholder="Hidangan utama"></label>
            <label class="portal-field"><span>Harga (Rp)</span><input name="harga" type="number" min="0" step="0.01" value="{{ old('harga') }}" required></label>
            <label class="portal-field"><span>Foto menu (URL)</span><input name="gambar" type="url" value="{{ old('gambar') }}" maxlength="255" placeholder="https://..."></label>
            <label class="portal-field menu-description-field"><span>Deskripsi</span><textarea name="deskripsi" rows="2" maxlength="2000">{{ old('deskripsi') }}</textarea></label>
            <label class="menu-availability"><input type="checkbox" name="tersedia" value="1" checked> Tersedia untuk dipesan</label>
            <button class="portal-primary-button" type="submit">Tambah menu <span aria-hidden="true">+</span></button>
        </form>
    </section>

    <section class="portal-panel">
        <div class="portal-panel-heading"><div><p class="portal-eyebrow">Pengelolaan katalog</p><h2>Menu terdaftar</h2></div></div>
        <div class="portal-table-wrap">
            <table class="portal-table menu-admin-table">
                <thead><tr><th>Menu</th><th>Harga</th><th>Foto</th><th>Kategori</th><th>Ketersediaan</th><th>Perbarui</th></tr></thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr>
                            <td><strong>{{ $menu->nama_menu }}</strong><small class="table-subline">{{ $menu->deskripsi }}</small></td>
                            <td>Rp{{ number_format((float) $menu->harga, 0, ',', '.') }}</td>
                            <td>@if ($menu->gambar)<a class="portal-inline-link" href="{{ $menu->gambar }}" target="_blank" rel="noreferrer">Lihat foto <span aria-hidden="true">&#8599;</span></a>@else<span class="table-subline">Belum ada</span>@endif</td>
                            <td>{{ $menu->kategori ?: '—' }}</td>
                            <td><span class="portal-status {{ $menu->tersedia ? 'status-ready' : 'status-inactive' }}">{{ $menu->tersedia ? 'Tersedia' : 'Habis' }}</span></td>
                            <td>
                                <details class="menu-edit-details">
                                    <summary class="portal-small-button">Edit menu</summary>
                                    <form class="menu-edit-form" method="post" action="{{ route('menus.update', $menu) }}">
                                        @csrf
                                        @method('PUT')
                                        <label class="portal-field"><span>Nama menu</span><input name="nama_menu" value="{{ $menu->nama_menu }}" required maxlength="255"></label>
                                        <label class="portal-field"><span>Kategori</span><input name="kategori" value="{{ $menu->kategori }}" maxlength="255"></label>
                                        <label class="portal-field"><span>Harga (Rp)</span><input name="harga" type="number" min="0" step="0.01" value="{{ $menu->harga }}" required></label>
                                        <label class="portal-field"><span>Foto menu (URL)</span><input name="gambar" type="url" value="{{ $menu->gambar }}" maxlength="255"></label>
                                        <label class="portal-field menu-description-field"><span>Deskripsi</span><textarea name="deskripsi" rows="3" maxlength="2000">{{ $menu->deskripsi }}</textarea></label>
                                        <input type="hidden" name="tersedia" value="0">
                                        <label class="menu-availability"><input type="checkbox" name="tersedia" value="1" @checked($menu->tersedia)> Tersedia untuk dipesan</label>
                                        <button class="portal-primary-button" type="submit">Simpan perubahan</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="6">Belum ada menu di katalog.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-pagination">{{ $menus->links() }}</div>
    </section>
@endsection