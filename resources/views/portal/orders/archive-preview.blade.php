@extends('layouts.portal')

@section('title', 'Pratinjau rekap pesanan')
@section('section', 'Pratinjau rekap')

@section('content')
    <div class="portal-page-heading report-heading order-preview-heading">
        <div>
            <p class="portal-eyebrow">Rekap harian · {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</p>
            <h1>Rekap pesanan.</h1>
            <p class="portal-heading-copy">Ringkasan pesanan selesai dan dibatalkan · Waktu Indonesia Barat</p>
        </div>
        <div class="portal-heading-actions no-print">
            <a class="portal-secondary-button" href="{{ route('orders.archive', ['date' => $selectedDate]) }}"><span aria-hidden="true">&larr;</span> Kembali ke arsip</a>
            <button class="portal-secondary-button" type="button" onclick="window.print()">Cetak laporan <span aria-hidden="true">&#8599;</span></button>
            @if (auth()->user()->role === 'admin')
                <a class="portal-primary-button" href="{{ route('orders.archive.export', ['date' => $selectedDate]) }}">Unduh CSV <span aria-hidden="true">&#8595;</span></a>
            @endif
        </div>
    </div>

    <section class="portal-metrics report-metrics order-preview-metrics" aria-label="Ringkasan pesanan pada tanggal terpilih">
        <article class="portal-metric metric-green">
            <span class="portal-metric-label">Total omzet</span>
            <strong class="portal-metric-currency">Rp{{ number_format((float) $summary['revenue'], 0, ',', '.') }}</strong>
            <span class="portal-metric-note">Dari pesanan selesai</span>
        </article>
        <article class="portal-metric metric-yellow">
            <span class="portal-metric-label">Jumlah pesanan</span>
            <strong>{{ number_format($summary['orderCount']) }}</strong>
            <span class="portal-metric-note">Pada tanggal terpilih</span>
        </article>
        <article class="portal-metric metric-blue">
            <span class="portal-metric-label">Selesai</span>
            <strong>{{ number_format($summary['completedCount']) }}</strong>
            <span class="portal-metric-note">Pesanan terselesaikan</span>
        </article>
        <article class="portal-metric metric-coral">
            <span class="portal-metric-label">Dibatalkan</span>
            <strong>{{ number_format($summary['cancelledCount']) }}</strong>
            <span class="portal-metric-note">Pesanan dibatalkan</span>
        </article>
    </section>

    <section class="portal-panel order-preview-table-panel">
        <div class="portal-panel-heading">
            <div>
                <p class="portal-eyebrow">{{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}</p>
                <h2>Rincian pesanan</h2>
            </div>
            <span class="portal-total-label">{{ number_format($summary['orderCount']) }} pesanan</span>
        </div>
        <div class="portal-table-wrap">
            <table class="portal-table order-management-table order-preview-table">
                <thead><tr><th>Waktu</th><th>Kode</th><th>Pelanggan</th><th>Pesanan</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->created_at->format('H:i') }} WIB</td>
                            <td><strong>{{ $order->kode_pesanan }}</strong></td>
                            <td><strong>{{ $order->customer?->nama ?? 'Pelanggan tidak tersedia' }}</strong><small class="table-subline">{{ $order->customer?->no_telepon }}</small></td>
                            <td><div class="order-detail-list">@forelse ($order->detailPesanans as $detail)<span>{{ $detail->jumlah }} × {{ $detail->nama_menu }}</span>@empty<span>Detail tidak tersedia</span>@endforelse</div></td>
                            <td><strong>Rp{{ number_format((float) $order->total_harga, 0, ',', '.') }}</strong></td>
                            <td><span class="portal-status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="portal-table-empty" colspan="6">Tidak ada pesanan terarsip pada tanggal ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="report-print-footer order-preview-print-footer">Toko Kerabat · Rekap pesanan {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d F Y') }} · Dicetak {{ now()->format('d/m/Y H:i') }} WIB</p>
@endsection