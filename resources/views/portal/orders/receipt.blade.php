@extends('layouts.portal')

@section('title', 'Struk pesanan')
@section('section', 'Struk pesanan')

@section('content')
    <div class="portal-page-heading no-print">
        <div>
            <p class="portal-eyebrow">Layanan harian</p>
            <h1>Struk pembelian.</h1>
            <p class="portal-heading-copy">Siap dicetak atau disimpan sebagai PDF.</p>
        </div>
        <div class="portal-heading-actions">
            <a class="portal-secondary-button" href="{{ route('orders.index') }}"><span aria-hidden="true">&larr;</span> Kembali ke pesanan</a>
            <button class="portal-primary-button" type="button" onclick="window.print()">Cetak struk <span aria-hidden="true">&#8599;</span></button>
        </div>
    </div>

    <main class="order-receipt-page">
        <article class="order-receipt" aria-label="Struk pesanan {{ $pesanan->kode_pesanan }}">
            <header class="order-receipt-header">
                <span class="order-receipt-brand">Toko Kerabat</span>
                <span class="order-receipt-kicker">Bukti pembelian</span>
                <h1>Terima kasih telah memesan</h1>
            </header>

            <section class="order-receipt-customer" aria-label="Informasi pelanggan">
                <strong>{{ $pesanan->customer?->nama ?? 'Pelanggan tidak tersedia' }}</strong>
                @if ($pesanan->customer?->no_telepon)
                    <span>{{ $pesanan->customer->no_telepon }}</span>
                @endif
            </section>

            <ul class="order-receipt-items" aria-label="Rincian pembelian">
                @forelse ($pesanan->detailPesanans as $detail)
                    <li class="order-receipt-item">
                        <div>
                            <strong>{{ $detail->nama_menu }}</strong>
                            <small>{{ $detail->jumlah }} × Rp{{ number_format((float) $detail->harga_satuan, 0, ',', '.') }}</small>
                        </div>
                        <span>Rp{{ number_format((float) $detail->subtotal, 0, ',', '.') }}</span>
                    </li>
                @empty
                    <li class="order-receipt-item"><span>Detail pesanan tidak tersedia</span></li>
                @endforelse
            </ul>

            <div class="order-receipt-total">
                <span>Total pembayaran</span>
                <strong>Rp{{ number_format((float) $pesanan->total_harga, 0, ',', '.') }}</strong>
            </div>

            <div class="order-receipt-status">
                <span>Status pesanan</span>
                <strong>{{ ucfirst($pesanan->status) }}</strong>
            </div>

            <footer class="order-receipt-footer">
                <span>Kode pesanan</span>
                <strong>{{ $pesanan->kode_pesanan }}</strong>
                <span>{{ $pesanan->created_at->format('d/m/Y H:i') }} WIB</span>
            </footer>
        </article>
    </main>
@endsection