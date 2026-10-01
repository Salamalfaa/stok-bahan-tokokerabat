@extends('layouts.portal')

@section('title', 'Profil & keamanan')
@section('section', 'Profil & keamanan')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">Akun personal</p><h1>Profil &amp; keamanan.</h1><p class="portal-heading-copy">Perbarui identitas, foto, dan kata sandi akunmu.</p></div>
    </div>

    <div class="profile-page-grid">
        <section class="portal-panel profile-panel">
            <div class="portal-panel-heading">
                <div><p class="portal-eyebrow">Identitas akun</p><h2>Informasi profil</h2></div>
                <span class="role-label role-{{ $user->role }}">{{ ['admin' => 'Administrator', 'owner' => 'Pemilik', 'operator' => 'Operator', 'customer' => 'Pelanggan'][$user->role] ?? ucfirst($user->role) }}</span>
            </div>

            <div class="profile-avatar-preview-wrap">
                <div class="profile-avatar-preview">
                    <img
                        id="profilePhotoPreview"
                        src="{{ $user->profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_photo_path) : '' }}"
                        alt="Foto profil {{ $user->name }}"
                        @if (! $user->profile_photo_path) hidden @endif
                    >
                    <span id="profilePhotoInitial" @if ($user->profile_photo_path) hidden @endif>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                </div>
                <div class="profile-photo-copy"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span><small>Foto tampil di sudut bawah panel kerja.</small></div>
            </div>

            <form class="profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <label class="portal-field"><span>Nama pengguna</span><input name="name" type="text" autocomplete="name" value="{{ old('name', $user->name) }}" maxlength="255" required></label>
                <label class="portal-field"><span>Email akun / login</span><input name="email" type="email" autocomplete="email" value="{{ old('email', $user->email) }}" maxlength="255" required></label>
                <label class="portal-field"><span>Nomor telepon</span><input name="no_telepon" type="tel" autocomplete="tel" value="{{ old('no_telepon', $user->no_telepon) }}" maxlength="30"></label>
                <label class="portal-field profile-photo-field"><span>Foto profil</span><input id="profilePhotoInput" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, atau WEBP · Maksimal 4 MB</small></label>
                <div class="profile-form-actions">
                    <button class="portal-primary-button" type="submit">Simpan profil <span aria-hidden="true">&rarr;</span></button>
                </div>
            </form>

            @if ($user->profile_photo_path)
                <form class="profile-remove-photo" method="post" action="{{ route('profile.photo.destroy') }}" data-confirm="Hapus foto profil saat ini?">
                    @csrf
                    @method('DELETE')
                    <button class="portal-small-button button-muted" type="submit">Hapus foto profil</button>
                </form>
            @endif
        </section>

        <section class="portal-panel profile-panel password-panel">
            <div class="portal-panel-heading"><div><p class="portal-eyebrow">Perlindungan akun</p><h2>Ganti kata sandi</h2></div><span class="security-mark" aria-hidden="true">🔐</span></div>
            <p class="profile-section-copy">Konfirmasi kata sandi saat ini sebelum menyimpan kata sandi baru.</p>
            <form class="profile-form password-change-form" method="post" action="{{ route('profile.password.update') }}">
                @csrf
                @method('PATCH')
                <label class="portal-field"><span>Kata sandi saat ini</span><input name="current_password" type="password" autocomplete="current-password" required></label>
                <label class="portal-field"><span>Kata sandi baru</span><input name="password" type="password" autocomplete="new-password" minlength="10" required><small>Minimal 10 karakter, mengandung huruf dan angka.</small></label>
                <label class="portal-field"><span>Ulangi kata sandi baru</span><input name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required></label>
                <div class="profile-form-actions"><button class="portal-primary-button" type="submit">Perbarui kata sandi <span aria-hidden="true">✓</span></button></div>
            </form>
        </section>
    </div>

    <script>
        const profilePhotoInput = document.querySelector('#profilePhotoInput');
        const profilePhotoPreview = document.querySelector('#profilePhotoPreview');
        const profilePhotoInitial = document.querySelector('#profilePhotoInitial');

        profilePhotoInput.addEventListener('change', (event) => {
            const file = event.target.files?.[0];

            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            profilePhotoPreview.src = URL.createObjectURL(file);
            profilePhotoPreview.hidden = false;
            profilePhotoInitial.hidden = true;
        });
    </script>
@endsection