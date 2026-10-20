@extends('layouts.portal')

@section('title', 'Panggilan suara')
@section('section', 'Panggilan suara')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">SUARA TOKO · SIARAN LANGSUNG</p>
            <h1>Panggilan suara.</h1>
            <p class="portal-heading-copy">Rekam panggilan pesanan dan putar untuk semua layar yang sedang membuka toko.</p>
        </div>
        @if ($activeAnnouncement)
            <button class="portal-secondary-button" type="button" data-stop-announcement>Hentikan siaran</button>
        @endif
    </div>

    <section
        class="voice-studio"
        aria-labelledby="voiceStudioTitle"
        data-voice-studio
        data-resource-url="{{ route('voice-announcements.index') }}"
        data-stop-url="{{ route('voice-announcements.stop') }}"
    >
        <div class="voice-studio-intro">
            <span class="voice-studio-mark" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></span>
            <div>
                <p class="portal-eyebrow">STUDIO PANGGILAN</p>
                <h2 id="voiceStudioTitle">Rekam suara baru</h2>
                <p>Gunakan mikrofon perangkat. Rekaman hanya disimpan setelah Anda menekan tombol simpan.</p>
            </div>
        </div>

        <form class="voice-record-form" action="{{ route('voice-announcements.store') }}" method="post" enctype="multipart/form-data" data-record-form>
            <label class="voice-field">
                <span>Judul panggilan</span>
                <input name="title" type="text" maxlength="100" placeholder="Contoh: Pesanan meja 4 sudah siap" required>
            </label>
            <label class="voice-field voice-duration-field">
                <span>Durasi maksimum</span>
                <span class="voice-duration-input"><input name="duration_seconds" type="number" min="1" max="180" value="30" required><span>detik</span></span>
            </label>
            <input name="audio" type="file" accept="audio/*" hidden data-audio-file>
            <div class="voice-recorder-controls">
                <button class="portal-primary-button" type="button" data-record-start><span aria-hidden="true">&#9679;</span> Mulai rekam</button>
                <button class="portal-secondary-button" type="button" data-record-stop disabled>Hentikan rekaman</button>
                <span class="voice-recording-status" data-recording-status role="status" aria-live="polite">Mikrofon belum digunakan</span>
                <time class="voice-recording-clock" data-recording-clock>00:00</time>
            </div>
            <audio class="voice-preview-player" controls data-recording-preview hidden></audio>
            <div class="voice-save-row">
                <span>Format tersimpan privat · maksimal 3 menit / 20 MB</span>
                <button class="portal-primary-button" type="submit" data-record-save disabled>Simpan rekaman</button>
            </div>
            <p class="voice-form-message" data-form-message role="status" aria-live="polite"></p>
        </form>
    </section>

    <section class="voice-library" aria-labelledby="voiceLibraryTitle">
        <div class="voice-library-heading">
            <div><p class="portal-eyebrow">ARSIP AUDIO</p><h2 id="voiceLibraryTitle">Rekaman tersimpan</h2></div>
            <span>{{ $announcements->count() }} rekaman</span>
        </div>
        <div class="voice-recording-list">
            @forelse ($announcements as $announcement)
                <article class="voice-recording-row" data-announcement-row="{{ $announcement->id }}">
                    <span class="voice-recording-index" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                    <div class="voice-recording-info">
                        <strong>{{ $announcement->title }}</strong>
                        <span>{{ $announcement->created_at->format('d M Y · H:i') }} WIB <span aria-hidden="true">·</span> {{ gmdate('i:s', $announcement->duration_seconds) }}</span>
                    </div>
                    <audio class="voice-library-player" controls preload="none" src="{{ route('voice-announcements.audio', $announcement) }}" aria-label="Putar {{ $announcement->title }}"></audio>
                    <div class="voice-recording-actions">
                        <button class="portal-primary-button" type="button" data-broadcast-announcement="{{ $announcement->id }}" data-broadcast-url="{{ route('voice-announcements.broadcast', $announcement) }}">Putar untuk semua</button>
                        <button class="voice-delete-button" type="button" data-delete-announcement="{{ $announcement->id }}" data-delete-url="{{ route('voice-announcements.destroy', $announcement) }}" aria-label="Hapus {{ $announcement->title }}" title="Hapus rekaman">Hapus</button>
                    </div>
                </article>
            @empty
                <div class="voice-empty-state"><span aria-hidden="true">&#9835;</span><strong>Belum ada rekaman</strong><p>Rekaman yang Anda simpan akan muncul di sini.</p></div>
            @endforelse
        </div>
    </section>
    <p class="voice-feedback" data-voice-feedback role="status" aria-live="polite"></p>
@endsection
