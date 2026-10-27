<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="refresh" content="10">
        <title>Akun dinonaktifkan | Toko Kerabat</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/voice-announcements.js'])
    </head>
    <body class="auth-body inactive-account-body">
        <main class="inactive-account-shell">
            <section class="inactive-account-panel" aria-labelledby="inactiveAccountTitle">
                <span class="inactive-account-mark" aria-hidden="true">!</span>
                <p class="portal-eyebrow">Status akun · {{ now()->translatedFormat('d M Y, H:i') }} WIB</p>
                <h1 id="inactiveAccountTitle">Akun sedang dinonaktifkan.</h1>
                <p class="inactive-account-copy">Akses ke ruang kerja dihentikan sementara. Hubungi administrator jika memerlukan informasi lebih lanjut.</p>

                @if ($dismissalNote)
                    <div class="inactive-account-note">
                        <span>Catatan dari administrator ⚠️</span>
                        <p>{{ $dismissalNote }}</p>
                    </div>
                @endif

                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="portal-primary-button inactive-account-logout" type="submit">Keluar dari akun</button>
                </form>
            </section>
        </main>
        @include('components.voice-announcement-popup')
    </body>
</html>