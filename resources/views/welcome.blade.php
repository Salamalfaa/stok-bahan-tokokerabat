<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Masakan rumahan dan meja hangat untuk dibagi bersama di Toko Kerabat.">
        <title>Toko Kerabat | Rasa dekat, cerita panjang</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        @if (session()->has('admin_impersonator_id'))
            <div class="impersonation-banner">
                <span>Anda sedang mengakses akun pengguna.</span>
                <form method="post" action="{{ route('users.impersonate.stop') }}">
                    @csrf
                    <button type="submit">Kembali ke administrator</button>
                </form>
            </div>
        @endif
        <div class="announcement-bar">
            <span>Masak sepenuh hati, disajikan untuk orang tersayang.</span>
            <a href="#menu">Lihat menu hari ini <span aria-hidden="true">&rarr;</span></a>
        </div>

        <header class="site-header">
            <div class="page-width navigation-shell">
                <a class="brand" href="#top" aria-label="Toko Kerabat, ke halaman utama">
                    <span class="brand-mark" aria-hidden="true">TK</span>
                    <span class="brand-copy">
                        <strong>Toko Kerabat</strong>
                        <small>Dapur &amp; meja</small>
                    </span>
                </a>

                <nav class="primary-navigation" id="primaryNavigation" aria-label="Navigasi utama">
                    <a href="#menu">Menu</a>
                    <a href="#cerita">Cerita kami</a>
                    <a href="#reservasi">Reservasi</a>
                </nav>

                <div class="navigation-actions">
                    @auth
                        @if (auth()->user()->role === 'customer')
                            <a class="cart-trigger" href="{{ route('account.orders.index') }}">Pesanan saya</a>
                        @else
                            <a class="cart-trigger" href="{{ route('dashboard') }}">Ruang kerja</a>
                        @endif
                    @else
                        <a class="cart-trigger" href="{{ route('login') }}">Masuk</a>
                    @endauth
                    <button class="cart-trigger" id="openCart" type="button" aria-controls="cartDrawer" aria-expanded="false">
                        Tas pesanan <span class="cart-count" id="cartCount">0</span>
                    </button>
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-controls="primaryNavigation" aria-expanded="false" aria-label="Buka navigasi">
                        <span></span><span></span>
                    </button>
                </div>
            </div>
        </header>

        <main>
            @if (session('password_reset_success'))
                <div class="page-width password-reset-success" role="status" aria-live="polite">
                    <span class="password-reset-success-icon" aria-hidden="true">&#10003;</span>
                    <div><strong>Kata sandi diperbarui</strong><p>{{ session('password_reset_success') }}</p></div>
                </div>
            @endif

            <section class="hero" id="top" aria-labelledby="heroTitle">
                <div class="hero-image" role="img" aria-label="Hidangan hangat tersaji di meja makan bersama"></div>
                <div class="page-width hero-inner">
                    <div class="hero-copy">
                        <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span>Dari dapur kami, untuk meja kalian</p>
                        <h1 id="heroTitle">Toko Kerabat.<br><span>Rasa dekat, cerita panjang.</span></h1>
                        <p class="hero-description">Ada rasa yang selalu mengingatkan kita untuk pulang. Temukan masakan rumahan, dibuat segar dan enak dibagi bersama.</p>
                        <div class="hero-actions">
                            <a class="button button-citrus" href="#menu">Jelajahi menu <span aria-hidden="true">&rarr;</span></a>
                            <a class="text-link text-link-light" href="#reservasi">Pesan meja <span aria-hidden="true">&darr;</span></a>
                        </div>
                    </div>
                    <div class="hero-caption">
                        <span class="caption-index">01 / 03</span>
                        <span>Yang sederhana, selalu punya tempat.</span>
                    </div>
                </div>
                <a class="hero-scroll" href="#menu" aria-label="Gulir ke menu"><span></span></a>
            </section>

            <section class="welcome-ribbon" aria-label="Keistimewaan Toko Kerabat">
                <div class="page-width ribbon-inner">
                    <p><span class="ribbon-symbol" aria-hidden="true">01</span> Bahan segar setiap hari</p>
                    <p><span class="ribbon-symbol" aria-hidden="true">02</span> Resep rumahan pilihan</p>
                    <p><span class="ribbon-symbol" aria-hidden="true">03</span> Paling nikmat dinikmati bersama</p>
                </div>
            </section>

            <section class="menu-section section-space" id="menu" aria-labelledby="menuTitle">
                <div class="page-width">
                    <div class="section-heading menu-heading">
                        <div>
                            <p class="eyebrow"><span class="eyebrow-rule"></span>Menu pilihan</p>
                            <h2 id="menuTitle">Dari dapur,<br><em>dengan rasa sayang.</em></h2>
                        </div>
                        <p class="section-intro">Semua dibuat segar saat dipesan. Pilih yang jadi favoritmu, nanti kami siapkan dengan sepenuh hati.</p>
                    </div>

                    <div class="menu-toolbar" aria-label="Filter menu">
                        <div class="category-filters" id="categoryFilters">
                            <button class="filter-button is-active" type="button" data-filter="all" aria-pressed="true">Semua menu</button>
                            @foreach ($categories as $category)
                                <button class="filter-button" type="button" data-filter="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                            @endforeach
                        </div>
                        <span class="menu-count">{{ $menus->count() }} menu tersedia</span>
                    </div>

                    <div class="menu-grid" id="menuGrid">
                        @forelse ($menus as $menu)
                            <article class="menu-card" data-category="{{ $menu->kategori }}">
                                <div class="menu-photo-wrap">
                                    <img class="menu-photo" src="{{ $menu->gambar ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=85' }}" alt="{{ $menu->nama_menu }}" loading="lazy" width="720" height="540">
                                    @if ($loop->first)
                                        <span class="menu-ribbon">Pilihan kerabat</span>
                                    @endif
                                </div>
                                <div class="menu-card-content">
                                    <div class="menu-card-topline">
                                        <span class="menu-category">{{ $menu->kategori }}</span>
                                        <span class="availability-dot" aria-label="Tersedia"></span>
                                    </div>
                                    <h3>{{ $menu->nama_menu }}</h3>
                                    <p class="menu-description">{{ $menu->deskripsi }}</p>
                                    <div class="menu-card-footer">
                                        <strong class="menu-price">Rp{{ number_format((float) $menu->harga, fmod((float) $menu->harga, 1) === 0.0 ? 0 : 2, ',', '.') }}</strong>
                                            <button
                                                class="add-to-cart"
                                                type="button"
                                                data-add-to-cart
                                                data-menu-id="{{ $menu->id }}"
                                                data-menu-name="{{ $menu->nama_menu }}"
                                                data-menu-price="{{ (int) round((float) $menu->harga * 100) }}"
                                                data-menu-image="{{ $menu->gambar ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=900&q=85' }}"
                                                aria-label="Tambah {{ $menu->nama_menu }} ke tas pesanan"
                                        >Tambah <span aria-hidden="true">+</span></button>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <p class="empty-menu">Menu pilihan kami sedang disiapkan. Silakan kembali sebentar lagi.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="story-section" id="cerita" aria-labelledby="storyTitle">
                <div class="story-photo" role="img" aria-label="Bahan segar untuk masakan rumahan"></div>
                <div class="story-copy">
                    <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span>Cerita dari meja kami</p>
                    <h2 id="storyTitle">Tempat makan.<br><em>Tempat merasa dekat.</em></h2>
                    <p>Kerabat bukan cuma soal siapa yang duduk di meja. Ini tentang menyambut, menyajikan, dan membuat semua orang merasa punya tempat.</p>
                    <a class="text-link text-link-light" href="#reservasi">Mari duduk bersama <span aria-hidden="true">&rarr;</span></a>
                </div>
            </section>

            <section class="reservation-section section-space" id="reservasi" aria-labelledby="reservationTitle">
                <div class="page-width reservation-layout">
                    <div class="reservation-copy">
                        <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span>Untuk momen berikutnya</p>
                        <h2 id="reservationTitle">Sediakan satu<br><em>kursi untukmu.</em></h2>
                        <p>Isi detail kunjunganmu. Tim kami akan menghubungi untuk memastikan meja dan waktunya.</p>
                        <div class="reservation-note"><span class="note-mark" aria-hidden="true">TK</span><span>Datang dengan lapar.<br>Pulang dengan cerita.</span></div>
                    </div>

                    <div class="reservation-form-wrap">
                        @if (session('status'))
                            <div class="form-success" role="status">{{ session('status') }}</div>
                        @endif

                        <form class="reservation-form" action="{{ route('reservasis.store') }}" method="post">
                            @csrf
                            <div class="form-field">
                                <label for="reservationName">Nama lengkap</label>
                                <input id="reservationName" name="nama" type="text" value="{{ old('nama') }}" autocomplete="name" required>
                                @error('nama')<span class="field-error">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="reservationPhone">Nomor telepon</label>
                                    <input id="reservationPhone" name="no_telepon" type="tel" value="{{ old('no_telepon') }}" autocomplete="tel" required>
                                    @error('no_telepon')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-field">
                                    <label for="reservationEmail">Email <span>(opsional)</span></label>
                                    <input id="reservationEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="reservationDate">Tanggal</label>
                                    <input id="reservationDate" name="tanggal_reservasi" type="date" min="{{ now('Asia/Jakarta')->toDateString() }}" value="{{ old('tanggal_reservasi') }}" required>
                                    @error('tanggal_reservasi')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-field">
                                    <label for="reservationTime">Waktu</label>
                                    <input id="reservationTime" name="waktu_reservasi" type="time" value="{{ old('waktu_reservasi') }}" required>
                                    @error('waktu_reservasi')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="form-row form-row-bottom">
                                <div class="form-field">
                                    <label for="reservationGuests">Jumlah orang</label>
                                    <input id="reservationGuests" name="jumlah_orang" type="number" min="1" max="99" step="1" value="{{ old('jumlah_orang') }}" placeholder="Tulis berapa orang" required>
                                    @error('jumlah_orang')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-field">
                                    <label for="reservationNote">Catatan <span>(opsional)</span></label>
                                    <input id="reservationNote" name="catatan" type="text" value="{{ old('catatan') }}" placeholder="Contoh: kursi anak">
                                    @error('catatan')<span class="field-error">{{ $message }}</span>@enderror
                                </div>
                            </div>

                            <div class="reservation-actions">
                                <button class="button button-citrus reservation-submit" type="submit">Minta reservasi <span aria-hidden="true">&rarr;</span></button>
                                <button class="button button-secondary reservation-view" type="button" data-open-reservation-modal>Lihat Reservasi <span aria-hidden="true">&rarr;</span></button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <div class="reservation-modal-backdrop" id="reservationModalBackdrop" hidden>
                <div class="reservation-modal" role="dialog" aria-modal="true" aria-labelledby="reservationModalTitle">
                    <div class="reservation-modal-header">
                        <div>
                            <p class="eyebrow eyebrow-light"><span class="eyebrow-rule"></span>Reservasi</p>
                            <h2 id="reservationModalTitle">Reservasi terbaru</h2>
                        </div>
                        <button class="drawer-close reservation-modal-close" type="button" aria-label="Tutup daftar reservasi">&times;</button>
                    </div>

                    <div class="reservation-modal-body">
                        <table class="reservation-modal-table">
                            <thead>
                                <tr>
                                    <th>Pelanggan</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentReservations as $reservation)
                                    <tr>
                                        <td>
                                            <strong>{{ $reservation->customer->nama ?? 'Pelanggan' }}</strong>
                                            <small>{{ $reservation->customer->email ?? $reservation->customer->no_telepon ?? 'Tanpa kontak' }}</small>
                                        </td>
                                        <td>{{ $reservation->tanggal_reservasi->translatedFormat('d M Y') }}</td>
                                        <td>{{ $reservation->waktu_reservasi }}</td>
                                        <td>{{ $reservation->jumlah_orang }} orang</td>
                                        <td><span class="reservation-pill reservation-status-{{ $reservation->status }}">{{ ucfirst($reservation->status) }}</span></td>
                                        @if (Auth::check() && in_array(Auth::user()->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_OPERATOR], true))
                                            <td>
                                                <div class="reservation-modal-actions">
                                                    <form class="reservation-status-form" method="post" action="{{ route('reservations.status.update', $reservation) }}" data-reservation-status-form>
                                                        @csrf
                                                        @method('PATCH')
                                                        <select name="status" aria-label="Status reservasi {{ $reservation->customer->nama ?? 'Pelanggan' }}">
                                                            @foreach (['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $value => $label)
                                                                <option value="{{ $value }}" @selected($reservation->status === $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button class="portal-small-button reservation-status-save" type="submit">Simpan</button>
                                                    </form>
                                                    <form method="post" action="{{ route('reservations.destroy', $reservation) }}" data-reservation-delete-form>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="portal-small-button button-muted reservation-delete-button" type="submit">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        @else
                                            <td></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="reservation-empty-state">Belum ada reservasi masuk.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <footer class="site-footer">
            <div class="page-width footer-main">
                <a class="brand brand-footer" href="#top">
                    <span class="brand-mark" aria-hidden="true">TK</span>
                    <span class="brand-copy"><strong>Toko Kerabat</strong><small>Dapur &amp; meja</small></span>
                </a>
                <p>Resep rumahan, hangatnya kebersamaan.</p>
                <a class="text-link" href="#top">Kembali ke atas <span aria-hidden="true">&uarr;</span></a>
            </div>
            <div class="page-width footer-bottom">
                <span>&copy; {{ now()->year }} Toko Kerabat</span>
                <span>Dibuat untuk dinikmati bersama.</span>
            </div>
        </footer>

        <button class="drawer-backdrop" id="closeCartBackdrop" type="button" aria-label="Tutup tas pesanan" hidden></button>
        <aside class="cart-drawer" id="cartDrawer" aria-label="Tas pesanan" aria-hidden="true" inert>
            <div class="drawer-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-rule"></span>Siap dinikmati</p>
                    <h2>Tas pesanan</h2>
                </div>
                <button class="drawer-close" id="closeCart" type="button" aria-label="Tutup tas pesanan">&times;</button>
            </div>
            <div class="cart-items" id="cartItems" aria-live="polite"></div>
            <p class="cart-empty" id="cartEmpty">Belum ada yang dipilih. Temukan menu favoritmu.</p>
            <div class="drawer-checkout" id="drawerCheckout" hidden>
                <div class="cart-total"><span>Total pesanan</span><strong id="cartTotal">Rp0</strong></div>
                <form id="checkoutForm" action="{{ route('pesanans.store') }}">
                    <div class="form-field">
                        <label for="orderName">Nama lengkap</label>
                        <input id="orderName" name="nama" type="text" autocomplete="name" required>
                    </div>
                    <div class="form-field">
                        <label for="orderPhone">Nomor telepon</label>
                        <input id="orderPhone" name="no_telepon" type="tel" autocomplete="tel" required>
                    </div>
                    <div class="form-field">
                        <label for="orderEmail">Email <span>(opsional)</span></label>
                        <input id="orderEmail" name="email" type="email" autocomplete="email">
                    </div>
                    <p class="checkout-message" id="checkoutMessage" role="status"></p>
                    <button class="button button-primary checkout-submit" id="checkoutSubmit" type="submit">Kirim pesanan <span aria-hidden="true">&rarr;</span></button>
                </form>
            </div>
        </aside>

        <div class="site-toast" id="siteToast" role="status" aria-live="polite"></div>
    </body>
</html>