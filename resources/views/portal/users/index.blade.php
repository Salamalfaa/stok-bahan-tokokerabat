@extends('layouts.portal')

@section('title', 'Pengguna')
@section('section', 'Pengguna')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">Akses &amp; tanggung jawab</p><h1>Tim &amp; pengguna.</h1><p class="portal-heading-copy">Rekrut, kelola akses, dan catat riwayat kepegawaian.</p></div>
        <div class="portal-heading-actions">
            <a class="password-recovery-trigger" href="{{ route('users.password-recovery.index') }}" aria-label="Buka notifikasi reset sandi, {{ $pendingRecoveryRequestCount }} menunggu" title="Notifikasi reset sandi">
                <span class="password-recovery-bell" aria-hidden="true">&#128276;</span>
                <span>Notifikasi reset</span>
                <span class="password-recovery-count">{{ $pendingRecoveryRequestCount }}</span>
            </a>
            <span class="portal-total-label">{{ $users->total() }} akun</span>
        </div>
    </div>

    <section class="portal-panel portal-form-panel">
        <div class="portal-panel-heading"><div><p class="portal-eyebrow">Rekrutmen &amp; akses baru</p><h2>Tambah anggota tim</h2></div></div>
        <form class="user-create-form" method="post" action="{{ route('users.store') }}">
            @csrf
            <label class="portal-field"><span>Nama</span><input name="name" value="{{ old('name') }}" required maxlength="255"></label>
            <label class="portal-field"><span>Email</span><input name="email" type="email" value="{{ old('email') }}" required maxlength="255"></label>
            <label class="portal-field"><span>Nomor telepon</span><input name="no_telepon" type="tel" value="{{ old('no_telepon') }}" maxlength="30"></label>
            <label class="portal-field"><span>Peran</span><select name="role" required><option value="">Pilih peran</option><option value="operator" @selected(old('role') === 'operator')>Operator / kasir</option><option value="owner" @selected(old('role') === 'owner')>Pemilik</option><option value="admin" @selected(old('role') === 'admin')>Administrator</option></select></label>
            <label class="portal-field employment-hire-note"><span>Catatan perekrutan 📝</span><textarea id="hire-note" name="note" rows="2" maxlength="2000" placeholder="Contoh: Mulai shift pagi, kasir utama ☀️">{{ old('note') }}</textarea></label>
            <div class="note-emoji-picker" aria-label="Emoji cepat untuk catatan">
                @foreach (['🎉', '💼', '☀️', '✅', '💬'] as $emoji)
                    <button type="button" data-insert-emoji="{{ $emoji }}" data-target="hire-note" aria-label="Tambahkan emoji {{ $emoji }}" title="Tambahkan {{ $emoji }}">{{ $emoji }}</button>
                @endforeach
            </div>
            <label class="portal-field"><span>Kata sandi sementara</span><input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
            <label class="portal-field"><span>Ulangi kata sandi</span><input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
            <button class="portal-primary-button" type="submit">Buat akun <span aria-hidden="true">+</span></button>
        </form>
    </section>

    <section class="portal-panel">
        <div class="portal-panel-heading account-list-heading">
            <div><p class="portal-eyebrow">Daftar akses</p><h2>Akun terdaftar</h2></div>
            <form class="account-search-form" id="accountSearchForm" method="get" action="{{ route('users.index') }}" role="search">
                <label class="account-search-field">
                    <span class="account-search-icon" aria-hidden="true"></span>
                    <input id="accountSearchInput" type="search" name="search" value="{{ $search }}" aria-label="Cari akun berdasarkan nama, email, atau nomor telepon" placeholder="Cari nama, email, atau telepon" autocomplete="off">
                </label>
                <button class="portal-small-button" type="submit">Cari</button>
                @if ($search !== '')
                    <a class="account-search-clear" href="{{ route('users.index') }}">Reset</a>
                @endif
            </form>
        </div>
        <span class="account-search-loading" id="accountSearchLoading" hidden><span class="account-search-spinner" aria-hidden="true"></span>Mencari akun...</span>
        <span class="sr-only" id="accountSearchStatus" role="status" aria-live="polite"></span>
        @include('portal.users._list', ['users' => $users])
    </section>

    <div class="employment-history-modal" id="employmentHistoryModal" hidden>
        <section class="employment-history-dialog" role="dialog" aria-modal="true" aria-labelledby="employmentHistoryTitle">
            <header class="employment-history-dialog-heading">
                <div><p class="portal-eyebrow">Jejak kepegawaian</p><h2 id="employmentHistoryTitle">Catatan akun</h2></div>
                <button class="employment-history-close" id="employmentHistoryClose" type="button" aria-label="Tutup catatan">&times;</button>
            </header>
            <div class="employment-history-dialog-content" id="employmentHistoryContent"></div>
        </section>
    </div>

    <script>
        (() => {
            const form = document.getElementById('accountSearchForm');
            const input = document.getElementById('accountSearchInput');
            const loading = document.getElementById('accountSearchLoading');
            const searchStatus = document.getElementById('accountSearchStatus');
            const historyModal = document.getElementById('employmentHistoryModal');
            const historyTitle = document.getElementById('employmentHistoryTitle');
            const historyContent = document.getElementById('employmentHistoryContent');
            const historyClose = document.getElementById('employmentHistoryClose');
            let searchTimer;
            let activeSearch;
            let historyTrigger;

            const closeHistory = () => {
                historyModal.hidden = true;
                document.body.classList.remove('employment-history-open');
                historyContent.replaceChildren();
                historyTrigger?.focus();
                historyTrigger = null;
            };

            const openHistory = (button) => {
                const template = button.parentElement.querySelector('.employment-history-template');

                if (!template) {
                    return;
                }

                historyTrigger = button;
                historyTitle.textContent = `Catatan · ${button.dataset.historyName}`;
                historyContent.replaceChildren(template.content.cloneNode(true));
                historyModal.hidden = false;
                document.body.classList.add('employment-history-open');
                historyClose.focus();
            };

            const updateResults = async (url) => {
                activeSearch?.abort();
                activeSearch = new AbortController();
                const controller = activeSearch;
                url.searchParams.set('partial', '1');
                const searchValue = url.searchParams.get('search') || '';

                if (searchValue) {
                    url.searchParams.set('search', searchValue);
                } else {
                    url.searchParams.delete('search');
                }

                loading.hidden = false;
                searchStatus.textContent = 'Mencari akun...';
                document.getElementById('accountSearchResults').setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                        signal: controller.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Search request failed');
                    }

                    const html = await response.text();
                    const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    const nextResults = nextDocument.getElementById('accountSearchResults');
                    const currentResults = document.getElementById('accountSearchResults');

                    if (!nextResults || !currentResults) {
                        throw new Error('Search results were not found');
                    }

                    currentResults.replaceWith(nextResults);
                    input.value = searchValue;
                    const historyUrl = new URL(form.action);

                    if (searchValue) {
                        historyUrl.searchParams.set('search', searchValue);
                    }

                    window.history.replaceState({}, '', historyUrl);
                    searchStatus.textContent = `${nextResults.dataset.resultCount} akun ditemukan.`;
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        searchStatus.textContent = 'Pencarian gagal. Silakan coba lagi.';
                    }
                } finally {
                    if (activeSearch === controller) {
                        loading.hidden = true;
                        document.getElementById('accountSearchResults')?.setAttribute('aria-busy', 'false');
                    }
                }
            };

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                window.clearTimeout(searchTimer);
                const url = new URL(form.action);
                const searchValue = input.value.trim();

                if (searchValue) {
                    url.searchParams.set('search', searchValue);
                }

                updateResults(url);
            });

            input.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => form.requestSubmit(), 300);
            });

            document.addEventListener('click', (event) => {
                const historyButton = event.target.closest('[data-open-employment-history]');

                if (historyButton) {
                    openHistory(historyButton);
                    return;
                }

                const pageLink = event.target.closest('#accountSearchResults .portal-pagination a');

                if (pageLink) {
                    event.preventDefault();
                    updateResults(new URL(pageLink.href));
                }
            });

            historyClose.addEventListener('click', closeHistory);
            historyModal.addEventListener('click', (event) => {
                if (event.target === historyModal) {
                    closeHistory();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !historyModal.hidden) {
                    closeHistory();
                }
            });

            window.addEventListener('popstate', () => {
                const url = new URL(window.location.href);
                input.value = url.searchParams.get('search') || '';
                updateResults(url);
            });
        })();
    </script>
@endsection