@extends('layouts.portal')

@section('title', 'Riwayat pesanan')
@section('section', 'Pesanan saya')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">Halo, {{ auth()->user()->name }}</p><h1>Cerita di meja.</h1><p class="portal-heading-copy">Riwayat pesanan dari akunmu.</p></div>
        <a class="portal-primary-button" href="{{ route('home') }}#menu">Pesan lagi <span aria-hidden="true">&rarr;</span></a>
    </div>

    @forelse ($orders as $order)
        <article class="portal-panel customer-order-card">
            <div class="customer-order-heading">
                <div><p class="portal-eyebrow">{{ $order->created_at->translatedFormat('d F Y · H:i') }} WIB</p><h2>{{ $order->kode_pesanan }}</h2></div>
                <span class="portal-status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
            </div>
            <div class="customer-order-lines">
                @foreach ($order->detailPesanans as $detail)
                    <div><span>{{ $detail->jumlah }} × {{ $detail->nama_menu }}</span><strong>Rp{{ number_format((float) $detail->subtotal, 0, ',', '.') }}</strong></div>
                @endforeach
            </div>
            <div class="customer-order-total"><span>Total pesanan</span><strong>Rp{{ number_format((float) $order->total_harga, 0, ',', '.') }}</strong></div>
        </article>
    @empty
        <section class="portal-panel customer-empty-orders">
            <span class="empty-mark" aria-hidden="true">TK</span>
            <h2>Meja masih menunggu.</h2>
            <p>Pesanan yang kamu buat akan tersimpan di sini.</p>
            <a class="portal-primary-button" href="{{ route('home') }}#menu">Jelajahi menu <span aria-hidden="true">&rarr;</span></a>
        </section>
    @endforelse
    <div class="portal-pagination">{{ $orders->links() }}</div>
@endsection