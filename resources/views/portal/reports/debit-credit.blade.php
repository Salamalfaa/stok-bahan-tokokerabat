@extends('layouts.portal')

@section('title', 'Laporan Debit & Kredit')
@section('section', 'Debit & Kredit')

@section('content')
    <div class="portal-page-heading report-heading">
        <div>
            <p class="portal-eyebrow">Arus kas operasional</p>
            <h1>Laporan debit &amp; kredit.</h1>
            <p class="portal-heading-copy">{{ $startDate->translatedFormat('d M Y') }} – {{ $endDate->translatedFormat('d M Y') }}</p>
        </div>
        <div class="portal-heading-actions no-print">
            <a class="portal-secondary-button" href="{{ route('reports.debit-credit.export', ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}">Unduh CSV <span aria-hidden="true">&#8595;</span></a>
            <button class="portal-secondary-button" type="button" onclick="window.print()">Cetak laporan <span aria-hidden="true">&#8599;</span></button>
        </div>
    </div>

    <form class="portal-panel debit-credit-filter no-print" method="get" action="{{ route('reports.debit-credit') }}">
        <label>
            <span>Tanggal mulai</span>
            <input type="date" name="start_date" value="{{ $startDate->toDateString() }}" required>
        </label>
        <label>
            <span>Tanggal akhir</span>
            <input type="date" name="end_date" value="{{ $endDate->toDateString() }}" required>
        </label>
        <button class="portal-primary-button" type="submit">Terapkan periode</button>
    </form>

    <section class="portal-metrics debit-credit-metrics" aria-label="Ringkasan debit dan kredit">
        <article class="portal-metric metric-green">
            <span class="portal-metric-label">Total debit · Kas masuk</span>
            <strong class="portal-metric-currency">Rp{{ number_format((float) $debitTotal, 0, ',', '.') }}</strong>
            <span class="portal-metric-note">Penjualan pesanan diproses dan selesai</span>
        </article>
        <article class="portal-metric metric-coral">
            <span class="portal-metric-label">Total kredit · Kas keluar</span>
            <strong class="portal-metric-currency">Rp{{ number_format((float) $creditTotal, 0, ',', '.') }}</strong>
            <span class="portal-metric-note">Pengeluaran yang dicatat manual</span>
        </article>
        <article class="portal-metric metric-yellow">
            <span class="portal-metric-label">Selisih arus kas</span>
            <strong class="portal-metric-currency">Rp{{ number_format($netBalanceCents / 100, 2, ',', '.') }}</strong>
            <span class="portal-metric-note">Total debit dikurangi total kredit</span>
        </article>
    </section>

    <section class="portal-panel debit-credit-entry no-print" aria-labelledby="expenseFormTitle">
        <div class="portal-panel-heading">
            <div>
                <p class="portal-eyebrow">Catat transaksi keluar</p>
                <h2 id="expenseFormTitle">Tambah pengeluaran</h2>
            </div>
        </div>
        <form class="debit-credit-expense-form" method="post" action="{{ route('expenses.store', ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()]) }}">
            @csrf
            <label>
                <span>Tanggal transaksi</span>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            </label>
            <label>
                <span>Kategori</span>
                <select name="category" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="debit-credit-description">
                <span>Keterangan</span>
                <input type="text" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Contoh: Pembelian bahan baku" required>
            </label>
            <label>
                <span>Jumlah (Rp)</span>
                <input type="number" name="amount" value="{{ old('amount') }}" min="0.01" max="9999999999.99" step="0.01" placeholder="0" required>
            </label>
            <button class="portal-primary-button" type="submit">Simpan pengeluaran</button>
        </form>
    </section>

    <section class="portal-panel debit-credit-transactions" aria-label="Daftar transaksi debit dan kredit">
        <div class="portal-panel-heading">
            <div>
                <p class="portal-eyebrow">Buku transaksi</p>
                <h2>Semua transaksi</h2>
            </div>
            <span class="debit-credit-count">{{ $transactions->total() }} transaksi</span>
        </div>
        <div class="portal-table-wrap debit-credit-table-wrap">
            <table class="portal-table debit-credit-table">
                <thead>
                    <tr>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Tipe</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Keterangan / referensi</th>
                        <th scope="col" class="debit-credit-amount">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($transaction->transaction_date)->translatedFormat('d M Y') }}</td>
                            <td>
                                <span @class(['debit-credit-badge', 'is-debit' => $transaction->transaction_type === 'debit', 'is-credit' => $transaction->transaction_type === 'credit'])>
                                    {{ $transaction->transaction_type === 'debit' ? 'Debit' : 'Kredit' }}
                                </span>
                            </td>
                            <td>{{ $categories[$transaction->category] ?? $transaction->category }}</td>
                            <td>{{ $transaction->description }}</td>
                            <td class="debit-credit-amount">Rp{{ number_format((float) $transaction->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="portal-table-empty" colspan="5">Belum ada transaksi pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="portal-pagination no-print">{{ $transactions->links() }}</div>
        @endif
    </section>

    <p class="debit-credit-disclaimer">Laporan ini merangkum kas masuk dari pesanan berstatus diproses/selesai dan kas keluar dari pengeluaran yang dicatat manual. Laporan ini bukan jurnal double-entry.</p>
    <p class="report-print-footer">Toko Kerabat · Laporan debit &amp; kredit · Dicetak {{ now()->format('d/m/Y H:i') }} WIB</p>
@endsection
