<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $isRegistration ? 'Buat akun' : 'Masuk' }} | Toko Kerabat</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/voice-announcements.js'])
    </head>
    <body class="auth-body">
        <main class="auth-shell">
            <section class="auth-visual" aria-label="Toko Kerabat">
                <a class="auth-brand" href="{{ route('home') }}"><span class="portal-brand-mark">TK</span><strong>Toko Kerabat</strong></a>
                <div class="auth-visual-copy">
                    <p class="portal-eyebrow">Dapur &amp; meja</p>
                    <h1>Rasa dekat,<br><em>cerita panjang.</em></h1>
                    <p>Tempat setiap pesanan, kunjungan, dan cerita punya ruangnya sendiri.</p>
                </div>
                <span class="auth-visual-index">TOKO KERABAT <span>·</span> SEJAK DARI DAPUR</span>
            </section>

            <section class="auth-panel">
                <a class="auth-back" href="{{ route('home') }}"><span aria-hidden="true">&larr;</span> Kembali ke toko</a>
                <div class="auth-form-wrap">
                    <p class="portal-eyebrow">{{ $isRegistration ? 'Selamat datang di meja kami' : 'Senang bertemu kembali' }}</p>
                    <h2>{{ $isRegistration ? 'Buat akun pelanggan' : 'Masuk ke akunmu' }}</h2>
                    <p class="auth-intro">{{ $isRegistration ? 'Simpan pesanan dan lihat riwayat kunjunganmu.' : 'Lanjutkan cerita bersama Toko Kerabat.' }}</p>

                    @if ($errors->any())
                        <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                    @endif
                    @if (session('status'))
                        <div class="auth-success" role="status">{{ session('status') }}</div>
                    @endif

                    <form class="auth-form" method="post" action="{{ $isRegistration ? route('register.store') : route('login.store') }}">
                        @csrf
                        @if ($isRegistration)
                            <label class="portal-field"><span>Nama lengkap</span><input name="name" type="text" autocomplete="name" value="{{ old('name') }}" required maxlength="255"></label>
                        @endif
                        <label class="portal-field"><span>Email</span><input name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255"></label>
                        @if ($isRegistration)
                            <label class="portal-field"><span>Nomor telepon</span><input name="no_telepon" type="tel" autocomplete="tel" value="{{ old('no_telepon') }}" required maxlength="30"></label>
                        @endif
                        <label class="portal-field"><span>Kata sandi</span><input name="password" type="password" autocomplete="{{ $isRegistration ? 'new-password' : 'current-password' }}" required @if ($isRegistration) minlength="10" @endif></label>
                        @if ($isRegistration)
                            <label class="portal-field"><span>Ulangi kata sandi</span><input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
                        @else
                            <div class="auth-login-options">
                                <label class="auth-remember"><input type="checkbox" name="remember" value="1"><span>Ingat saya di perangkat ini</span></label>
                                <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
                            </div>
                        @endif
                        <button class="portal-primary-button auth-submit" type="submit">{{ $isRegistration ? 'Buat akun' : 'Masuk' }} <span aria-hidden="true">&rarr;</span></button>
                    </form>

                    <p class="auth-switch">
                        {{ $isRegistration ? 'Sudah memiliki akun?' : 'Belum memiliki akun?' }}
                        <a href="{{ $isRegistration ? route('login') : route('register') }}">{{ $isRegistration ? 'Masuk' : 'Daftar sebagai pelanggan' }}</a>
                    </p>
                </div>
                <p class="auth-footer">&copy; {{ now()->year }} Toko Kerabat</p>
            </section>
        </main>
        @include('components.voice-announcement-popup')
    </body>
</html>