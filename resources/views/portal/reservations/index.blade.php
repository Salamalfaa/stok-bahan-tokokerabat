@extends('layouts.portal')

@section('title', 'Reservasi')
@section('section', 'Reservasi')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">Jadwal reservasi</p>
            <h1>Reservasi terbaru.</h1>
            <p class="portal-heading-copy">Pantau semua permintaan reservasi masuk dan status meja yang menunggu konfirmasi.</p>
        </div>
        <div class="portal-heading-actions">
            <span class="portal-inline-badge">{{ $pendingReservationCount }} menunggu</span>
        </div>
    </div>

    <section class="portal-panel">
        <div class="portal-table-wrap">
            <table class="portal-table reservation-management-table">
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Perbarui</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td>
                                <strong>{{ $reservation->customer->nama ?? 'Pelanggan' }}</strong>
                                <small class="table-subline">{{ $reservation->customer->email ?? $reservation->customer->no_telepon ?? 'Tanpa kontak' }}</small>
                            </td>
                            <td>{{ $reservation->tanggal_reservasi->translatedFormat('d M Y') }}</td>
                            <td>{{ $reservation->waktu_reservasi }}</td>
                            <td>{{ $reservation->jumlah_orang }} orang</td>
                            <td><span class="portal-status status-{{ $reservation->status }}">{{ ucfirst($reservation->status) }}</span></td>
                            <td>
                                <div class="reservation-management-actions">
                                    <form class="order-status-form reservation-status-form" method="post" action="{{ route('reservations.status.update', $reservation) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" aria-label="Status reservasi {{ $reservation->customer->nama ?? 'Pelanggan' }}">
                                            @foreach (['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                                                <option value="{{ $value }}" @selected($reservation->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="portal-small-button" type="submit">Simpan</button>
                                    </form>
                                    <form method="post" action="{{ route('reservations.destroy', $reservation) }}" onsubmit="return confirm('Hapus reservasi ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="portal-small-button button-muted reservation-delete-button" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="portal-table-empty">Belum ada reservasi masuk.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
