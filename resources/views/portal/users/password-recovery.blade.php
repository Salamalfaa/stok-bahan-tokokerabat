@extends('layouts.portal')

@section('title', 'Notifikasi reset sandi')
@section('section', 'Notifikasi reset sandi')

@section('content')
    @if (session('manualResetUrl'))
        <section class="manual-reset-link" role="status" aria-live="polite">
            <div>
                <strong>Permintaan disetujui</strong>
                <p>SMTP belum aktif. Salin tautan sekali pakai ini dan kirimkan langsung kepada pengguna melalui kanal yang aman.</p>
            </div>
            <label class="manual-reset-link-field">
                <span>Tautan reset sementara</span>
                <input type="url" value="{{ session('manualResetUrl') }}" readonly aria-label="Tautan reset sementara">
            </label>
        </section>
    @endif

    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">Persetujuan administrator</p>
            <h1>Permintaan reset kata sandi.</h1>
            <p class="portal-heading-copy">Tautan reset hanya dikirim setelah permintaan disetujui.</p>
        </div>
        <a class="portal-secondary-button" href="{{ route('users.index') }}"><span aria-hidden="true">&larr;</span> Kembali ke pengguna</a>
    </div>

    <section class="portal-panel recovery-inbox" aria-label="Daftar permintaan reset kata sandi">
        <div class="portal-panel-heading">
            <div>
                <p class="portal-eyebrow">{{ $pendingRequestCount }} menunggu tindakan</p>
                <h2>Notifikasi pemulihan</h2>
            </div>
            <span class="portal-total-label">{{ $requests->total() }} permintaan</span>
        </div>

        @forelse ($requests as $recoveryRequest)
            <article class="recovery-request-row">
                <div class="recovery-request-person">
                    <span class="recovery-request-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($recoveryRequest->user?->name ?? '?', 0, 1)) }}</span>
                    <div>
                        <strong>{{ $recoveryRequest->user?->name ?? 'Akun tidak tersedia' }}</strong>
                        <span>{{ $recoveryRequest->user?->email ?? 'Email tidak tersedia' }}</span>
                        <small>{{ ['customer' => 'Pelanggan', 'operator' => 'Karyawan', 'owner' => 'Pemilik'][$recoveryRequest->user?->role] ?? '' }}</small>
                    </div>
                </div>
                <div class="recovery-request-meta">
                    <span class="portal-status recovery-status-{{ $recoveryRequest->status }}">{{ ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'completed' => 'Selesai', 'expired' => 'Kedaluwarsa'][$recoveryRequest->status] ?? ucfirst($recoveryRequest->status) }}</span>
                    <time datetime="{{ $recoveryRequest->requested_at->toIso8601String() }}">{{ $recoveryRequest->requested_at->translatedFormat('d M Y, H:i') }} WIB</time>
                    @if ($recoveryRequest->resolvedBy)
                        <small>Diproses {{ $recoveryRequest->resolvedBy->name }} · {{ $recoveryRequest->resolved_at?->translatedFormat('d M Y, H:i') }} WIB</small>
                    @endif
                    @if ($recoveryRequest->completed_at)
                        <small>Kata sandi diperbarui {{ $recoveryRequest->completed_at->translatedFormat('d M Y, H:i') }} WIB</small>
                    @endif
                </div>
                @if ($recoveryRequest->status === 'pending' && $recoveryRequest->expires_at->isFuture())
                    <div class="recovery-request-actions">
                        <form method="post" action="{{ route('users.password-recovery.approve', $recoveryRequest) }}">
                            @csrf
                            <button class="portal-primary-button" type="submit">Setujui &amp; kirim tautan</button>
                        </form>
                        <form method="post" action="{{ route('users.password-recovery.reject', $recoveryRequest) }}" data-confirm="Tolak permintaan reset dari {{ $recoveryRequest->user?->name }}?">
                            @csrf
                            <button class="portal-small-button button-muted" type="submit">Tolak</button>
                        </form>
                    </div>
                @endif
                <div class="recovery-request-delete-wrap">
                    <button type="button" class="portal-small-button button-danger recovery-delete-single" data-delete-route="{{ route('users.password-recovery.destroy-item', $recoveryRequest) }}">Hapus</button>
                </div>
            </article>
        @empty
            <div class="portal-empty-state">
                <strong>Belum ada permintaan reset</strong>
                <span>Permintaan dari pelanggan dan karyawan akan muncul di sini.</span>
            </div>
        @endforelse

        <div class="portal-pagination">{{ $requests->links() }}</div>
    </section>

    <div id="recoveryDeleteModal" class="recovery-delete-modal" hidden>
        <div class="recovery-delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="recoveryDeleteModalTitle">
            <h3 id="recoveryDeleteModalTitle">Apakah anda yakin ingin menghapus?</h3>
            <div class="recovery-delete-modal-actions">
                <button type="button" class="portal-small-button button-danger recovery-delete-confirm">Ya</button>
                <button type="button" class="portal-small-button button-warning recovery-delete-cancel">Tidak</button>
            </div>
        </div>
    </div>
@endsection