@extends('layouts.portal')

@section('title', 'Panggilan suara')
@section('section', 'Panggilan suara')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">AUDIO · SIARAN TOKO</p>
            <h1>Panggilan suara.</h1>
            <p class="portal-heading-copy">Rekam pengumuman dengan jernih, lalu siarkan ke halaman toko dalam satu langkah.</p>
        </div>
        @if ($activeAnnouncement)
            <button class="portal-secondary-button" type="button" data-stop-announcement>Hentikan siaran</button>
        @endif
    </div>

    <section
        class="voice-studio"
        aria-labelledby="voiceStudioTitle"
        data-voice-studio
        data-stop-url="{{ route('voice-announcements.stop') }}"
    >
        <div class="voice-studio-intro">
            <span class="voice-studio-mark" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></span>
            <div>
                <p class="portal-eyebrow"><span class="voice-studio-live-dot" aria-hidden="true"></span> STUDIO PANGGILAN</p>
                <h2 id="voiceStudioTitle">Rekam suara baru</h2>
                <p>Rekam langsung dari mikrofon perangkat. Dengarkan pratinjau sebelum menyimpan ke arsip privat.</p>
            </div>
            <span class="voice-studio-limit">MAKS. 03:00</span>
        </div>

        <form class="voice-record-form" action="{{ route('voice-announcements.store') }}" method="post" enctype="multipart/form-data" data-record-form>
            <label class="voice-field">
                <span>Judul panggilan <small>Nama yang terlihat di arsip</small></span>
                <input name="title" type="text" maxlength="100" placeholder="Contoh: Pesanan meja 4 siap diambil" required>
            </label>
            <label class="voice-field voice-duration-field">
                <span>Batas durasi <small>Atur panjang rekaman</small></span>
                <span class="voice-duration-input"><input name="duration_seconds" type="number" min="1" max="180" value="30" aria-label="Batas rekaman dalam detik" required><span>detik</span></span>
            </label>
            <div class="voice-recorder-controls">
                <button class="portal-primary-button" type="button" data-record-start><span aria-hidden="true">&#9679;</span> Mulai rekam</button>
                <button class="portal-secondary-button" type="button" data-record-stop disabled><span aria-hidden="true">&#9632;</span> Hentikan</button>
                <span class="voice-recording-status" data-recording-status data-state="ready" role="status" aria-live="polite">Mikrofon siap digunakan</span>
                <time class="voice-recording-clock" data-recording-clock>00:00</time>
            </div>
            <audio class="voice-preview-player" controls data-recording-preview hidden></audio>
            <div class="voice-save-row">
                <span class="voice-save-note"><span aria-hidden="true">◈</span> Arsip privat · maks. 20 MB</span>
                <button class="portal-primary-button voice-save-button" type="submit" data-record-save disabled><span aria-hidden="true">＋</span> Simpan rekaman</button>
            </div>
            <p class="voice-form-message" data-form-message role="status" aria-live="polite"></p>
        </form>
    </section>

    <section class="voice-order-mode" aria-labelledby="voiceOrderModeTitle">
        <div class="voice-order-mode-icon" aria-hidden="true">✦</div>
        <div class="voice-order-mode-copy">
            <p class="portal-eyebrow">MODE OTOMATIS · TANPA REKAM</p>
            <h2 id="voiceOrderModeTitle">Panggil pesanan yang sudah selesai</h2>
            <p>Ketika status pesanan diubah menjadi <strong>Selesai</strong>, panggilan akan langsung disiarkan ke halaman utama toko dan berlaku selama 24 jam.</p>
            <p class="voice-order-sample">“Perhatian, pesanan dengan kode TK-0001 sudah selesai dan siap diambil. Terima kasih.”</p>
        </div>
        <span class="voice-order-mode-badge">AUTO · 24 JAM</span>
    </section>

    <section class="voice-library" aria-labelledby="voiceOrderCallsTitle">
        <div class="voice-library-heading">
            <div><p class="portal-eyebrow">PESANAN MASUK</p><h2 id="voiceOrderCallsTitle">Panggilan otomatis</h2></div>
            <span data-order-announcement-count>{{ $orderAnnouncements->count() }} panggilan aktif</span>
        </div>
        <div class="voice-recording-list">
            @forelse ($orderAnnouncements as $announcement)
                <article class="voice-order-call-row" data-announcement-row="{{ $announcement->id }}">
                    <span class="voice-order-live-dot" aria-hidden="true"></span>
                    <div class="voice-recording-info">
                        <strong>{{ $announcement->title }}</strong>
                        <span>Berlaku sampai {{ $announcement->expires_at->format('d M Y · H:i') }} WIB</span>
                        <span class="voice-order-message">{{ $announcement->message }}</span>
                    </div>
                    <div class="voice-recording-actions">
                        <button class="portal-primary-button" type="button" data-broadcast-announcement="{{ $announcement->id }}" data-broadcast-url="{{ route('voice-announcements.broadcast', $announcement) }}">Bunyikan ulang</button>
                        <button class="voice-delete-button" type="button" data-delete-announcement="{{ $announcement->id }}" data-delete-url="{{ route('voice-announcements.destroy', $announcement) }}" aria-label="Hapus paksa panggilan {{ $announcement->title }}" title="Hapus sebelum 24 jam">Hapus paksa</button>
                    </div>
                </article>
            @empty
                <div class="voice-empty-state"><span aria-hidden="true">✦</span><strong>Belum ada panggilan otomatis</strong><p>Panggilan akan muncul dan berbunyi saat pesanan ditandai selesai.</p></div>
            @endforelse
        </div>
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
