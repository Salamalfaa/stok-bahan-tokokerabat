<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Ruang kerja') | Toko Kerabat</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-body" @if (auth()->user()->role === 'operator') data-employment-status-url="{{ route('account.employment-status') }}" data-inactive-account-url="{{ route('account.inactive') }}" @endif>
        @if (session()->has('admin_impersonator_id'))
            <div class="impersonation-banner">
                <span>Anda sedang mengakses akun pengguna.</span>
                <form method="post" action="{{ route('users.impersonate.stop') }}">
                    @csrf
                    <button type="submit">Kembali ke administrator</button>
                </form>
            </div>
        @endif
        <div class="portal-shell">
            <aside class="portal-sidebar" id="portalSidebar">
                <a class="portal-brand" href="{{ route('home') }}">
                    <span class="portal-brand-mark">TK</span>
                    <span><strong>Toko Kerabat</strong><small>RUANG KERJA</small></span>
                </a>

                <p class="portal-nav-label">OPERASIONAL</p>
                <nav class="portal-nav" aria-label="Navigasi ruang kerja">
                    <a href="{{ route('dashboard') }}" @class(['is-current' => request()->routeIs('dashboard')])>Ringkasan</a>
                    @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
                        <a href="{{ route('reservations.index') }}" @class(['is-current' => request()->routeIs('reservations.*')])>Reservasi</a>
                    @endif
                    @if (in_array(auth()->user()->role, ['owner', 'admin'], true))
                        <a href="{{ route('inventory.index') }}" @class(['is-current' => request()->routeIs('inventory.*', 'stock-movements.*')])>Persediaan</a>
                    @endif
                    @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
                        <a href="{{ route('orders.index') }}" @class(['is-current' => request()->routeIs('orders.*')])>Pesanan masuk</a>
                    @endif
                    @if (in_array(auth()->user()->role, ['owner', 'admin'], true))
                        <a href="{{ route('reports.index') }}" @class(['is-current' => request()->routeIs('reports.*')])>Laporan</a>
                    @endif
                    @if (in_array(auth()->user()->role, ['operator', 'admin'], true))
                        <a href="{{ route('menus.index') }}" @class(['is-current' => request()->routeIs('menus.*')])>Menu toko</a>
                    @endif
                    @if (auth()->user()->role === 'admin')
                        <a href="{{ route('users.index') }}" @class(['is-current' => request()->routeIs('users.*')])>Pengguna</a>
                    @endif
                </nav>

                <div class="portal-sidebar-bottom">
                    <a class="portal-store-link" href="{{ route('home') }}">Lihat halaman toko <span aria-hidden="true">&rarr;</span></a>
                    <div class="portal-profile">
                        <a class="portal-profile-link" href="{{ route('profile.edit') }}" aria-label="Buka profil dan keamanan akun">
                            @if (auth()->user()->profile_photo_path)
                                <img class="portal-avatar" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url(auth()->user()->profile_photo_path) }}" alt="">
                            @else
                                <span class="portal-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                            <span class="portal-profile-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }} · Profil</small></span>
                        </a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="portal-logout" type="submit" aria-label="Keluar" title="Keluar">Keluar</button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="portal-content">
                <header class="portal-topbar">
                    <button class="portal-menu-toggle" type="button" aria-controls="portalSidebar" aria-expanded="false">Menu</button>
                    <div class="portal-breadcrumb"><span>Toko Kerabat</span><span aria-hidden="true">/</span><strong>@yield('section', 'Ruang kerja')</strong></div>
                    <div class="portal-topbar-meta">
                        <div class="portal-live-clock" aria-live="polite" aria-atomic="true">
                            <span class="portal-live-clock-label">Jam</span>
                            <strong id="portalLiveClock">00:00:00</strong>
                        </div>
                        <span class="portal-time-divider" aria-hidden="true">|</span>
                        <time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, d F Y') }}</time>
                    </div>
                </header>

                <main class="portal-main">
                    @if (session('status'))
                        <div class="portal-flash" role="status">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="portal-errors" role="alert">
                            <strong>Tindakan tidak berhasil.</strong>
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="portal-errors" role="alert">
                            <strong>Periksa kembali data yang dimasukkan.</strong>
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
        @php($employmentNotice = session('employment_notice'))
        <div class="employment-notice-backdrop" id="employmentNotice" @if (! $employmentNotice) hidden @endif>
            <section class="employment-notice-dialog" role="dialog" aria-modal="true" aria-labelledby="employmentNoticeTitle" aria-describedby="employmentNoticeMessage">
                <span class="employment-notice-mark" aria-hidden="true">!</span>
                <p class="portal-eyebrow">Pemberitahuan akun</p>
                <h2 id="employmentNoticeTitle">{{ $employmentNotice['title'] ?? 'Akun dinonaktifkan' }}</h2>
                <p class="employment-notice-message" id="employmentNoticeMessage">{{ $employmentNotice['message'] ?? 'Akses ke ruang kerja dihentikan sementara.' }}</p>
                <div class="employment-notice-note" id="employmentNoticeNote" @if (! ($employmentNotice['note'] ?? null)) hidden @endif>
                    <span>Catatan dari administrator</span>
                    <p>{{ $employmentNotice['note'] ?? '' }}</p>
                </div>
                <button class="portal-primary-button" id="employmentNoticeContinue" type="button">Lanjutkan</button>
            </section>
        </div>
        <script>
            const portalToggle = document.querySelector('.portal-menu-toggle');
            const portalSidebar = document.querySelector('#portalSidebar');
            const portalLiveClock = document.getElementById('portalLiveClock');
            const employmentNotice = document.getElementById('employmentNotice');
            const employmentStatusUrl = document.body.dataset.employmentStatusUrl;
            const inactiveAccountUrl = document.body.dataset.inactiveAccountUrl;

            const openEmploymentNotice = ({ title, message, note, blocking = false }) => {
                document.getElementById('employmentNoticeTitle').textContent = title;
                document.getElementById('employmentNoticeMessage').textContent = message;

                const noteContainer = document.getElementById('employmentNoticeNote');
                noteContainer.hidden = !note;
                noteContainer.querySelector('p').textContent = note || '';
                employmentNotice.dataset.blocking = String(blocking);
                employmentNotice.hidden = false;
                document.getElementById('employmentNoticeContinue').focus();
            };

            document.getElementById('employmentNoticeContinue').addEventListener('click', () => {
                if (employmentNotice.dataset.blocking === 'true') {
                    window.location.assign(inactiveAccountUrl);
                    return;
                }

                employmentNotice.hidden = true;
            });

            if (employmentStatusUrl) {
                const checkEmploymentStatus = async () => {
                    if (document.hidden || !employmentNotice.hidden) {
                        return;
                    }

                    try {
                        const response = await fetch(employmentStatusUrl, {
                            headers: { Accept: 'application/json' },
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            return;
                        }

                        const status = await response.json();

                        if (!status.is_active) {
                            openEmploymentNotice({
                                title: 'Akun dinonaktifkan',
                                message: 'Administrator telah menonaktifkan akun Anda. Akses ke ruang kerja dihentikan sementara.',
                                note: status.event?.note,
                                blocking: true,
                            });
                        }
                    } catch {
                        return;
                    }
                };

                checkEmploymentStatus();
                window.setInterval(checkEmploymentStatus, 5000);
            }

            const updateLiveClock = () => {
                if (!portalLiveClock) {
                    return;
                }

                const now = new Date();
                const formatter = new Intl.DateTimeFormat('id-ID', {
                    timeZone: 'Asia/Jakarta',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                });

                const parts = formatter.formatToParts(now);
                const values = {};
                parts.forEach((part) => {
                    if (part.type !== 'literal') {
                        values[part.type] = part.value;
                    }
                });

                portalLiveClock.textContent = `${values.hour || '00'}:${values.minute || '00'}:${values.second || '00'}`;
            };

            if (portalToggle && portalSidebar) {
                portalToggle.addEventListener('click', () => {
                    const isOpen = portalToggle.getAttribute('aria-expanded') !== 'true';
                    portalToggle.setAttribute('aria-expanded', String(isOpen));
                    portalSidebar.classList.toggle('is-open', isOpen);
                });
            }

            updateLiveClock();
            setInterval(updateLiveClock, 1000);

            document.querySelectorAll('[data-insert-emoji]').forEach((button) => {
                button.addEventListener('click', () => {
                    const field = document.getElementById(button.dataset.target);

                    if (!field) {
                        return;
                    }

                    const start = field.selectionStart ?? field.value.length;
                    const end = field.selectionEnd ?? field.value.length;
                    field.setRangeText(button.dataset.insertEmoji, start, end, 'end');
                    field.focus();
                });
            });
            document.querySelectorAll('form[data-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm(form.dataset.confirm)) {
                        event.preventDefault();
                    }
                });
            });
        </script>
        <div class="site-toast" id="siteToast" role="status" aria-live="polite"></div>
    </body>
</html>