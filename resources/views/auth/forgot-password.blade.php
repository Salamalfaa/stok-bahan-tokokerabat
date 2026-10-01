<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Lupa kata sandi | Toko Kerabat</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css'])
    </head>
    <body class="auth-body auth-recovery">
        <main class="auth-shell">
            <section class="auth-visual" aria-label="Toko Kerabat">
                <a class="auth-brand" href="{{ route('home') }}"><span class="portal-brand-mark">TK</span><strong>Toko Kerabat</strong></a>
                <div class="auth-visual-copy">
                    <p class="portal-eyebrow">Akses akun</p>
                    <h1>Kembali ke<br><em>mejamu.</em></h1>
                    <p>Masukkan email akun untuk meminta tautan pemulihan.</p>
                </div>
                <span class="auth-visual-index">TOKO KERABAT <span>·</span> AKUN &amp; KEAMANAN</span>
            </section>
            <section class="auth-panel">
                <a class="auth-back" href="{{ route('login') }}"><span aria-hidden="true">&larr;</span> Kembali ke masuk</a>
                <div class="auth-form-wrap">
                    <p class="portal-eyebrow">Pemulihan akses</p>
                    <h2>Lupa kata sandi?</h2>
                    @unless ($requestSubmitted)
                        <p class="auth-intro">Masukkan email akun. Permintaan pemulihan pelanggan dan karyawan akan diperiksa administrator sebelum tautan reset dikirim.</p>
                    @endunless
                    @if ($errors->any())
                        <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                    @endif
                    @if (session('status'))
                            <section class="recovery-notice" role="status" aria-live="polite" @if ($hasTrackedRequest) data-status-url="{{ route('password.recovery.status') }}" @endif>
                                <span class="recovery-notice-icon" aria-hidden="true">&#10003;</span>
                                <div class="recovery-notice-content">
                                    <strong data-recovery-title>Permintaan diterima</strong>
                                    <p data-recovery-message>{{ session('status') }}</p>
                                    @if ($hasTrackedRequest)
                                        <ol>
                                            <li>Menunggu pemeriksaan administrator</li>
                                            <li>Tautan reset dikirim setelah disetujui</li>
                                        </ol>
                                        <small class="recovery-status-check" data-recovery-check>Memeriksa status persetujuan…</small>
                                    @else
                                        <small class="recovery-status-check">Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.</small>
                                    @endif
                                    <a class="recovery-restart-link" href="{{ route('password.request', ['new' => 1]) }}">Ajukan permintaan lain</a>
                                </div>
                            </section>
                    @endif
                    @if ($approvedReset !== null)
                        <form class="auth-form recovery-reset-form" method="post" action="{{ route('password.update') }}" @if ($approvedReset === null) hidden @endif>
                            @csrf
                            <input type="hidden" name="token" value="{{ $approvedReset['token'] }}">
                            <input type="hidden" name="email" value="{{ $approvedReset['email'] }}">
                            <div class="recovery-reset-form-header">
                                <strong>Kata sandi baru</strong>
                                <p>Gunakan kata sandi baru untuk masuk ke akun Anda.</p>
                            </div>
                            <label class="portal-field"><span>Kata sandi baru</span><input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
                            <label class="portal-field"><span>Ulangi kata sandi</span><input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
                            <button class="portal-primary-button auth-submit" type="submit">Simpan kata sandi <span aria-hidden="true">&rarr;</span></button>
                        </form>
                    @endif
                        @unless ($requestSubmitted)
                            <form class="auth-form" method="post" action="{{ route('password.email') }}">
                                @csrf
                                <label class="portal-field"><span>Email akun</span><input name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255"></label>
                                <button class="portal-primary-button auth-submit" type="submit">Kirim permintaan pemulihan <span aria-hidden="true">&rarr;</span></button>
                            </form>
                        @endunless
                </div>
                <p class="auth-footer">&copy; {{ now()->year }} Toko Kerabat</p>
            </section>
        </main>
        @if ($requestSubmitted && $hasTrackedRequest)
            <script>
                (() => {
                    const notice = document.querySelector('.recovery-notice[data-status-url]');
                    const title = notice?.querySelector('[data-recovery-title]');
                    const message = notice?.querySelector('[data-recovery-message]');
                    const check = notice?.querySelector('[data-recovery-check]');

                    if (!notice || !title || !message || !check) {
                        return;
                    }

                    const terminalStatuses = ['approved', 'rejected', 'completed', 'expired', 'untracked'];
                    const resetForm = document.querySelector('.recovery-reset-form');
                    const revealResetForm = () => {
                        if (!resetForm) {
                            return;
                        }

                        resetForm.hidden = false;
                        resetForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    };

                    const checkStatus = async () => {
                        try {
                            const response = await fetch(notice.dataset.statusUrl, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                throw new Error('Status check failed');
                            }

                            const result = await response.json();
                            message.textContent = result.message;

                            if (result.status === 'approved') {
                                title.textContent = 'Permintaan disetujui';
                                check.textContent = 'Masukkan kata sandi baru di bawah ini.';
                                notice.classList.add('is-approved');
                                revealResetForm();
                            } else if (result.status === 'rejected') {
                                title.textContent = 'Permintaan tidak disetujui';
                                check.textContent = 'Hubungi administrator untuk tindak lanjut.';
                                notice.classList.add('is-rejected');
                            } else if (result.status === 'expired') {
                                title.textContent = 'Permintaan kedaluwarsa';
                                check.textContent = 'Mulai permintaan baru jika masih memerlukan bantuan.';
                                notice.classList.add('is-expired');
                            } else if (result.status === 'completed') {
                                title.textContent = 'Kata sandi diperbarui';
                                check.textContent = 'Masuk menggunakan kata sandi baru.';
                                notice.classList.add('is-approved');
                            } else if (result.status === 'untracked') {
                                title.textContent = 'Permintaan diterima';
                                check.textContent = 'Jika email terdaftar, administrator akan memeriksa permintaan ini.';
                            } else {
                                title.textContent = 'Menunggu pemeriksaan administrator';
                                check.textContent = 'Status diperbarui otomatis.';
                            }

                            if (terminalStatuses.includes(result.status)) {
                                return;
                            }
                        } catch {
                            check.textContent = 'Status belum dapat diperiksa. Halaman akan mencoba kembali.';
                        }

                        window.setTimeout(checkStatus, 5000);
                    };

                    window.setTimeout(checkStatus, 1500);
                })();
            </script>
        @endif
    </body>
</html>
