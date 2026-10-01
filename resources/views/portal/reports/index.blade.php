@extends('layouts.portal')

@section('title', 'Laporan')
@section('section', 'Laporan')

@section('content')
    @php($maxDailyRevenue = max((float) $ordersByDay->max('revenue'), 1))
    <div class="portal-page-heading report-heading">
        <div><p class="portal-eyebrow">Analitik toko</p><h1>Laporan usaha.</h1><p class="portal-heading-copy">{{ $startDate->translatedFormat('d M Y') }} – {{ now()->translatedFormat('d M Y') }}</p></div>
        <div class="portal-heading-actions no-print">
            <div class="report-period" aria-label="Periode laporan">
                @foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $period => $label)
                    <a href="{{ route('reports.index', ['days' => $period]) }}" @class(['is-selected' => $days === $period])>{{ $label }}</a>
                @endforeach
            </div>
            <button class="portal-secondary-button" type="button" onclick="window.print()">Cetak laporan <span aria-hidden="true">&#8599;</span></button>
        </div>
    </div>

    <section class="portal-metrics report-metrics" aria-label="Ringkasan laporan">
        <article class="portal-metric metric-green"><span class="portal-metric-label">Omzet bersih</span><strong class="portal-metric-currency">Rp{{ number_format((float) $totalRevenue, 0, ',', '.') }}</strong><span class="portal-metric-note">Pesanan diproses dan selesai</span></article>
        <article class="portal-metric metric-yellow"><span class="portal-metric-label">Total pesanan</span><strong>{{ number_format((int) $totalOrders) }}</strong><span class="portal-metric-note">Pada periode terpilih</span></article>
        <article class="portal-metric metric-coral"><span class="portal-metric-label">Stok menipis</span><strong>{{ number_format($lowStockCount) }}</strong><span class="portal-metric-note">Bahan di bawah batas minimum</span></article>
    </section>

    <div class="portal-dashboard-grid report-grid">
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><p class="portal-eyebrow">Performa harian</p><h2>Pesanan &amp; omzet</h2></div></div>
            @forelse ($ordersByDay as $day)
                <div class="report-bar-row">
                    <span class="report-date">{{ \Illuminate\Support\Carbon::parse($day->report_date)->translatedFormat('d M') }}</span>
                    <div class="report-bar-track"><span style="width: {{ min(100, (int) round((float) $day->revenue / $maxDailyRevenue * 100)) }}%"></span></div>
                    <span class="report-bar-value">Rp{{ number_format((float) $day->revenue, 0, ',', '.') }}</span>
                    <small>{{ $day->order_count }} pesanan</small>
                </div>
            @empty
                <div class="portal-empty-state"><strong>Belum ada aktivitas pada periode ini</strong><span>Data laporan akan muncul setelah pesanan tercatat.</span></div>
            @endforelse
        </section>

        <section class="portal-panel">
            <div class="portal-panel-heading"><div><p class="portal-eyebrow">Pilihan pelanggan</p><h2>Menu terlaris</h2></div></div>
            @forelse ($popularItems as $index => $item)
                <div class="popular-item-row"><span class="popular-item-rank">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span class="popular-item-copy"><strong>{{ $item->nama_menu }}</strong><small>{{ number_format((int) $item->quantity_sold) }} terjual</small></span><strong class="popular-item-revenue">Rp{{ number_format((float) $item->revenue, 0, ',', '.') }}</strong></div>
            @empty
                <div class="portal-empty-state"><strong>Belum ada menu terjual</strong><span>Data penjualan akan muncul di sini.</span></div>
            @endforelse
        </section>
    </div>

    <p class="report-print-footer">Toko Kerabat · Laporan {{ $days }} hari · Dicetak {{ now()->format('d/m/Y H:i') }} WIB</p>
@endsection