@extends('layouts.portal')

@section('title', 'Arsip pesanan')
@section('section', 'Arsip pesanan')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">Riwayat harian · {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</p>
            <h1>Arsip pesanan.</h1>
            <p class="portal-heading-copy">Pesanan selesai atau dibatalkan, minimal 24 jam sejak dibuat.</p>
        </div>
        <div class="portal-heading-actions order-page-actions">
            <a class="portal-secondary-button" href="{{ route('orders.index') }}"><span aria-hidden="true">&larr;</span> Pesanan masuk</a>
            <a class="portal-primary-button" href="{{ route('orders.archive.preview', ['date' => $selectedDate]) }}">Lihat rekap <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>

    <section class="portal-panel order-archive-panel">
        <div class="portal-panel-heading order-archive-heading">
            <div>
                <p class="portal-eyebrow">Pilih tanggal</p>
                <h2>Riwayat harian</h2>
            </div>
            <form class="order-archive-picker" method="get" action="{{ route('orders.archive') }}">
                <label class="order-calendar-field" for="archiveDate">
                    <span class="order-calendar-icon" aria-hidden="true"></span>
                    <input id="archiveDate" name="date" type="date" value="{{ $selectedDate }}" max="{{ today()->subDay()->toDateString() }}" aria-label="Pilih tanggal arsip" required>
                </label>
                <button class="portal-primary-button" type="submit">Tampilkan</button>
            </form>
        </div>

        <div class="portal-table-wrap">
            <table class="portal-table order-management-table">
                <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Detail pesanan</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><strong>{{ $order->kode_pesanan }}</strong><small class="table-subline">{{ $order->created_at->format('d/m/Y H:i') }} WIB</small></td>
                            <td><strong>{{ $order->customer?->nama ?? 'Pelanggan tidak tersedia' }}</strong><small class="table-subline">{{ $order->customer?->no_telepon }}</small></td>
                            <td><div class="order-detail-list">@foreach ($order->detailPesanans as $detail)<span>{{ $detail->jumlah }} × {{ $detail->nama_menu }}</span>@endforeach</div></td>
                            <td><strong>Rp{{ number_format((float) $order->total_harga, 0, ',', '.') }}</strong></td>
                            <td><span class="portal-status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="5">Tidak ada pesanan terarsip pada tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="portal-pagination">{{ $orders->links() }}</div>
    </section>
@endsection