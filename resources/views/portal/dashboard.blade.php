@extends('layouts.portal')

@section('title', 'Ringkasan')
@section('section', 'Ringkasan')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1>Selamat datang, {{ auth()->user()->name }}.</h1>
            <p class="portal-heading-copy">Aktivitas Toko Kerabat hari ini.</p>
        </div>
        <div class="portal-heading-actions">
            @if (in_array(auth()->user()->role, ['owner', 'admin'], true))
                <a class="portal-secondary-button" href="{{ route('reports.index') }}">Buka laporan <span aria-hidden="true">&rarr;</span></a>
            @endif
            @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
                <a class="portal-primary-button" href="{{ route('orders.index') }}">Lihat pesanan <span aria-hidden="true">&rarr;</span></a>
            @endif
        </div>
    </div>

    <section class="portal-metrics" aria-label="Ringkasan hari ini">
        @if ($canViewInventory)
            <article class="portal-metric metric-green">
                <span class="portal-metric-label">Bahan aktif</span>
                <strong>{{ number_format($activeItemCount) }}</strong>
                <span class="portal-metric-note">jenis bahan baku</span>
            </article>
            <article class="portal-metric metric-coral">
                <span class="portal-metric-label">Stok menipis</span>
                <strong>{{ number_format($lowStockCount) }}</strong>
                <a class="portal-metric-note" href="{{ route('inventory.index') }}">Periksa persediaan <span aria-hidden="true">&rarr;</span></a>
            </article>
        @endif
        <article class="portal-metric metric-yellow">
            <span class="portal-metric-label">Pesanan menunggu</span>
            <strong>{{ number_format($pendingOrderCount) }}</strong>
            <span class="portal-metric-note">perlu ditinjau</span>
        </article>
        @if (in_array(auth()->user()->role, ['owner', 'admin'], true))
            <article class="portal-metric metric-blue">
                <span class="portal-metric-label">Omzet hari ini</span>
                <strong class="portal-metric-currency">Rp{{ number_format((float) $todayRevenue, 0, ',', '.') }}</strong>
                <span class="portal-metric-note">{{ $todayOrderCount }} pesanan tercatat</span>
            </article>
        @else
            <article class="portal-metric metric-blue">
                <span class="portal-metric-label">Pesanan hari ini</span>
                <strong>{{ number_format($todayOrderCount) }}</strong>
                <span class="portal-metric-note">pesanan tercatat</span>
            </article>
        @endif
    </section>

    @if ($canViewInventory)
        <div class="portal-dashboard-grid">
            <section class="portal-panel portal-stock-alerts">
            <div class="portal-panel-heading">
                <div><p class="portal-eyebrow">Perlu perhatian</p><h2>Stok menipis</h2></div>
                <a class="portal-inline-link" href="{{ route('inventory.index') }}">Semua bahan <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="portal-dashboard-scroll" role="region" aria-label="Daftar bahan dengan stok menipis" tabindex="0">
            @forelse ($lowStockItems as $item)
                <div class="portal-stock-row">
                    <span class="stock-indicator" aria-hidden="true"></span>
                    <span class="portal-stock-name"><strong>{{ $item->name }}</strong><small>Minimum {{ number_format((float) $item->minimum_quantity, 3, ',', '.') }} {{ $item->unit }}</small></span>
                    <strong class="portal-stock-quantity">{{ number_format((float) $item->quantity, 3, ',', '.') }} <small>{{ $item->unit }}</small></strong>
                </div>
            @empty
                <div class="portal-empty-state"><span class="empty-mark" aria-hidden="true">&#10003;</span><strong>Semua stok aman</strong><span>Tidak ada bahan yang berada di bawah batas minimum.</span></div>
            @endforelse
            </div>
            </section>

            <section class="portal-panel portal-activity-panel">
            <div class="portal-panel-heading">
                <div><p class="portal-eyebrow">Catatan terbaru</p><h2>Mutasi persediaan</h2></div>
                <a class="portal-inline-link" href="{{ route('inventory.index') }}">Buka persediaan <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="portal-dashboard-scroll" role="region" aria-label="Daftar mutasi persediaan terbaru" tabindex="0">
            @forelse ($recentMovements as $movement)
                <div class="portal-activity-row">
                    <span class="movement-sign {{ $movement->type === 'in' ? 'is-in' : 'is-out' }}" aria-hidden="true">{{ $movement->type === 'in' ? '+' : '-' }}</span>
                    <span class="portal-activity-copy"><strong>{{ $movement->inventoryItem->name }}</strong><small>{{ $movement->user?->name ?? 'Pengguna dihapus' }} · {{ $movement->created_at->format('d/m H:i') }} WIB</small></span>
                    <strong class="portal-activity-amount">{{ $movement->type === 'in' ? '+' : '-' }}{{ number_format((float) $movement->quantity, 3, ',', '.') }} {{ $movement->inventoryItem->unit }}</strong>
                </div>
            @empty
                <div class="portal-empty-state"><strong>Belum ada mutasi</strong><span>Catatan masuk dan keluar stok akan muncul di sini.</span></div>
            @endforelse
            </div>
            </section>
        </div>
    @endif

    @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
        <section class="portal-panel portal-orders-panel">
            <div class="portal-panel-heading">
                <div><p class="portal-eyebrow">Dapur &amp; meja</p><h2>Pesanan terbaru</h2></div>
                <a class="portal-inline-link" href="{{ route('orders.index') }}">Semua pesanan <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="portal-table-wrap portal-dashboard-orders-scroll" role="region" aria-label="Daftar pesanan terbaru" tabindex="0">
                <table class="portal-table">
                    <thead><tr><th>Kode</th><th>Pelanggan</th><th>Waktu</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><strong>{{ $order->kode_pesanan }}</strong></td>
                                <td>{{ $order->customer->nama }}</td>
                                <td>{{ $order->created_at->format('d/m/Y H:i') }} WIB</td>
                                <td>Rp{{ number_format((float) $order->total_harga, 0, ',', '.') }}</td>
                                <td><span class="portal-status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="portal-table-empty">Belum ada pesanan masuk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection