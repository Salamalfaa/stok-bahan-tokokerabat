<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Atur kata sandi baru | Toko Kerabat</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css'])
    </head>
    <body class="auth-body">
        <main class="auth-shell">
            <section class="auth-visual" aria-label="Toko Kerabat">
                <a class="auth-brand" href="{{ route('home') }}"><span class="portal-brand-mark">TK</span><strong>Toko Kerabat</strong></a>
                <div class="auth-visual-copy">
                    <p class="portal-eyebrow">Keamanan akun</p>
                    <h1>Kunci baru,<br><em>cerita lanjut.</em></h1>
                    <p>Buat kata sandi baru yang hanya kamu ketahui.</p>
                </div>
                <span class="auth-visual-index">TOKO KERABAT <span>·</span> AKUN &amp; KEAMANAN</span>
            </section>
            <section class="auth-panel">
                <a class="auth-back" href="{{ route('login') }}"><span aria-hidden="true">&larr;</span> Kembali ke masuk</a>
                <div class="auth-form-wrap">
                    <p class="portal-eyebrow">Atur ulang akses</p>
                    <h2>Kata sandi baru</h2>
                    <p class="auth-intro">Gunakan minimal 10 karakter, dengan huruf dan angka.</p>
                    @if ($errors->any())
                        <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <form class="auth-form" method="post" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <label class="portal-field"><span>Email akun</span><input name="email" type="email" autocomplete="email" value="{{ old('email', $email) }}" required maxlength="255"></label>
                        <label class="portal-field"><span>Kata sandi baru</span><input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
                        <label class="portal-field"><span>Ulangi kata sandi</span><input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
                        <button class="portal-primary-button auth-submit" type="submit">Simpan kata sandi <span aria-hidden="true">&rarr;</span></button>
                    </form>
                </div>
                <p class="auth-footer">&copy; {{ now()->year }} Toko Kerabat</p>
            </section>
        </main>
    </body>
</html>
