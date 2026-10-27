@extends('layouts.portal')

@section('title', 'Pesanan masuk')
@section('section', 'Pesanan masuk')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">Layanan harian</p><h1>Pesanan masuk.</h1><p class="portal-heading-copy">Tandai pesanan selesai untuk menyiarkan panggilan otomatis selama 24 jam.</p></div>
        <div class="portal-heading-actions order-page-actions">
            <span class="portal-total-label">{{ $orders->total() }} pesanan aktif</span>
            <a class="portal-secondary-button" href="{{ route('voice-announcements.index') }}">Panggilan suara</a>
            <form class="order-archive-picker" method="get" action="{{ route('orders.archive') }}">
                <label class="order-calendar-field" for="activeArchiveDate">
                    <span class="order-calendar-icon" aria-hidden="true"></span>
                    <input id="activeArchiveDate" name="date" type="date" value="{{ today()->subDay()->toDateString() }}" max="{{ today()->subDay()->toDateString() }}" aria-label="Pilih tanggal arsip" required>
                </label>
                <button class="portal-secondary-button" type="submit">Riwayat arsip</button>
            </form>
        </div>
    </div>

    <section class="portal-panel">
        <div class="portal-table-wrap">
            <table class="portal-table order-management-table">
                <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Detail pesanan</th><th>Total</th><th>Status</th><th>Perbarui</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <strong>{{ $order->kode_pesanan }}</strong>
                                <small class="table-subline">{{ $order->created_at->format('d/m/Y H:i') }} WIB</small>
                                <a class="order-receipt-link" href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener">Cetak struk</a>
                            </td>
                            <td><strong>{{ $order->customer->nama }}</strong><small class="table-subline">{{ $order->customer->no_telepon }}</small></td>
                            <td><div class="order-detail-list">@foreach ($order->detailPesanans as $detail)<span>{{ $detail->jumlah }} × {{ $detail->nama_menu }}</span>@endforeach</div></td>
                            <td><strong>Rp{{ number_format((float) $order->total_harga, 0, ',', '.') }}</strong></td>
                            <td><span class="portal-status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                            <td>
                                <form class="order-status-form" method="post" action="{{ route('orders.status.update', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" aria-label="Status pesanan {{ $order->kode_pesanan }}">
                                        @foreach (['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                                            <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="portal-small-button" type="submit">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="6">Belum ada pesanan masuk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-pagination">{{ $orders->links() }}</div>
    </section>
@endsection