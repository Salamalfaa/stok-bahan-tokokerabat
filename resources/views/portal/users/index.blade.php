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

    <div class="account-management-modal" id="accountManagementModal" hidden>
        <section class="account-management-dialog" role="dialog" aria-modal="true" aria-labelledby="accountManagementTitle" aria-describedby="accountManagementDescription">
            <header class="account-management-heading">
                <div><p class="portal-eyebrow">Pengaturan akses</p><h2 id="accountManagementTitle">Kelola akun</h2><p id="accountManagementDescription"></p></div>
                <button class="employment-history-close" id="accountManagementClose" type="button" aria-label="Tutup pengelolaan akun">&times;</button>
            </header>
            <div class="account-management-content">
                <form class="account-management-role" id="accountRoleForm" method="post">
                    @csrf
                    @method('PATCH')
                    <div><strong>Ubah peran</strong><span>Perbarui hak akses akun ini tanpa membuat akun baru.</span></div>
                    <label class="portal-field"><span>Peran baru</span><select name="role" id="accountRoleSelect" required><option value="customer">Pelanggan</option><option value="operator">Operator / kasir</option><option value="owner">Pemilik</option><option value="admin">Administrator</option></select></label>
                    <label class="portal-field" id="accountRoleNoteField" hidden><span>Catatan riwayat operator</span><textarea name="note" rows="2" maxlength="2000" placeholder="Catatan opsional perubahan peran"></textarea></label>
                    <button class="portal-primary-button" type="submit">Simpan peran</button>
                </form>
                <section class="account-management-employment" id="accountManagementEmployment" hidden>
                    <div><strong>Aksi kepegawaian</strong><span>Pemberhentian atau pengaktifan kembali operator memerlukan catatan wajib.</span></div>
                    <form id="accountEmploymentForm" method="post">
                        @csrf
                        @method('PATCH')
                        <label class="portal-field"><span id="accountEmploymentNoteLabel">Catatan wajib</span><textarea name="note" rows="2" maxlength="2000" required placeholder="Tuliskan alasan atau catatan dengan jelas"></textarea></label>
                        <button class="portal-small-button" id="accountEmploymentButton" type="submit">Perbarui status</button>
                    </form>
                </section>
                <form class="account-management-access" id="accountAccessForm" method="post">
                    @csrf
                    <div><strong>Akses akun</strong><span>Masuk sementara sebagai pengguna untuk memeriksa pengalaman mereka.</span></div>
                    <button class="portal-small-button" id="accountAccessButton" type="submit">Masuk sebagai pengguna</button>
                </form>
                <div class="account-management-danger">
                    <div><strong>Hapus akun</strong><span>Tindakan ini permanen. Riwayat pesanan pelanggan tetap disimpan.</span></div>
                    <button class="account-delete-trigger" id="accountDeleteTrigger" type="button">Hapus akun</button>
                </div>
            </div>
        </section>
    </div>

    <div class="account-delete-modal" id="accountDeleteModal" hidden>
        <section class="account-delete-dialog" role="alertdialog" aria-modal="true" aria-labelledby="accountDeleteTitle" aria-describedby="accountDeleteMessage">
            <p class="portal-eyebrow">Konfirmasi tindakan permanen</p>
            <h2 id="accountDeleteTitle">Hapus akun ini?</h2>
            <p id="accountDeleteMessage">Akun akan dihapus. Riwayat transaksi pelanggan tetap dipertahankan.</p>
            <div class="account-delete-actions">
                <button class="portal-small-button" id="accountDeleteCancel" type="button">Batal</button>
                <form id="accountDeleteForm" method="post">
                    @csrf
                    @method('DELETE')
                    <button class="account-delete-trigger" type="submit">Hapus permanen</button>
                </form>
            </div>
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
            const accountManagementModal = document.getElementById('accountManagementModal');
            const accountManagementClose = document.getElementById('accountManagementClose');
            const accountManagementTitle = document.getElementById('accountManagementTitle');
            const accountManagementDescription = document.getElementById('accountManagementDescription');
            const accountRoleForm = document.getElementById('accountRoleForm');
            const accountRoleSelect = document.getElementById('accountRoleSelect');
            const accountRoleNoteField = document.getElementById('accountRoleNoteField');
            const accountManagementEmployment = document.getElementById('accountManagementEmployment');
            const accountEmploymentForm = document.getElementById('accountEmploymentForm');
            const accountEmploymentNote = accountEmploymentForm.querySelector('textarea');
            const accountEmploymentNoteLabel = document.getElementById('accountEmploymentNoteLabel');
            const accountEmploymentButton = document.getElementById('accountEmploymentButton');
            const accountAccessForm = document.getElementById('accountAccessForm');
            const accountAccessButton = document.getElementById('accountAccessButton');
            const accountDeleteModal = document.getElementById('accountDeleteModal');
            const accountDeleteForm = document.getElementById('accountDeleteForm');
            const accountDeleteMessage = document.getElementById('accountDeleteMessage');
            const accountDeleteTrigger = document.getElementById('accountDeleteTrigger');
            const accountDeleteCancel = document.getElementById('accountDeleteCancel');
            let searchTimer;
            let activeSearch;
            let historyTrigger;
            let accountManagementTrigger;

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

            const closeAccountManagement = () => {
                accountManagementModal.hidden = true;
                document.body.classList.remove('employment-history-open');
                accountManagementTrigger?.focus();
            };

            const openAccountManagement = (button) => {
                accountManagementTrigger = button;
                accountManagementTitle.textContent = `Kelola · ${button.dataset.userName}`;
                accountManagementDescription.textContent = `${button.dataset.userEmail} · ${button.dataset.userActive === 'true' ? 'Aktif' : 'Nonaktif'}`;
                accountRoleForm.action = button.dataset.roleUrl;
                accountRoleSelect.value = button.dataset.userRole;
                accountRoleNoteField.hidden = button.dataset.userRole !== 'operator';
                accountRoleNoteField.querySelector('textarea').value = '';
                accountManagementEmployment.hidden = button.dataset.userRole !== 'operator';
                accountEmploymentForm.action = button.dataset.userActive === 'true'
                    ? button.dataset.dismissUrl
                    : button.dataset.rehireUrl;
                accountEmploymentNote.value = '';
                accountEmploymentNoteLabel.textContent = button.dataset.userActive === 'true'
                    ? 'Alasan pemberhentian wajib'
                    : 'Catatan pengaktifan kembali wajib';
                accountEmploymentButton.textContent = button.dataset.userActive === 'true'
                    ? 'Simpan pemberhentian'
                    : 'Aktifkan kembali';
                accountAccessForm.action = button.dataset.accessUrl;
                accountAccessButton.disabled = button.dataset.userRole === 'admin' || button.dataset.userActive !== 'true';
                accountAccessButton.title = accountAccessButton.disabled
                    ? 'Hanya akun aktif non-administrator yang dapat diakses.'
                    : '';
                accountDeleteForm.action = button.dataset.deleteUrl;
                accountDeleteMessage.textContent = `Akun ${button.dataset.userName} akan dihapus. Riwayat transaksi pelanggan tetap dipertahankan.`;
                accountManagementModal.hidden = false;
                document.body.classList.add('employment-history-open');
                accountManagementClose.focus();
            };

            const closeDeleteConfirmation = () => {
                accountDeleteModal.hidden = true;
                accountManagementModal.hidden = false;
                accountDeleteTrigger.focus();
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
                const managementButton = event.target.closest('[data-open-account-management]');

                if (managementButton) {
                    openAccountManagement(managementButton);
                    return;
                }

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
            accountManagementClose.addEventListener('click', closeAccountManagement);
            accountManagementModal.addEventListener('click', (event) => {
                if (event.target === accountManagementModal) {
                    closeAccountManagement();
                }
            });
            accountRoleSelect.addEventListener('change', () => {
                accountRoleNoteField.hidden = accountRoleSelect.value !== 'operator';
            });
            accountDeleteTrigger.addEventListener('click', () => {
                accountManagementModal.hidden = true;
                accountDeleteModal.hidden = false;
                accountDeleteCancel.focus();
            });
            accountDeleteCancel.addEventListener('click', closeDeleteConfirmation);
            accountDeleteModal.addEventListener('click', (event) => {
                if (event.target === accountDeleteModal) {
                    closeDeleteConfirmation();
                }
            });
            historyModal.addEventListener('click', (event) => {
                if (event.target === historyModal) {
                    closeHistory();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !historyModal.hidden) {
                    closeHistory();
                }

                if (event.key === 'Escape' && !accountDeleteModal.hidden) {
                    closeDeleteConfirmation();
                } else if (event.key === 'Escape' && !accountManagementModal.hidden) {
                    closeAccountManagement();
                }
            });

            accountRoleSelect.dispatchEvent(new Event('change'));

            window.addEventListener('popstate', () => {
                const url = new URL(window.location.href);
                input.value = url.searchParams.get('search') || '';
                updateResults(url);
            });
        })();
    </script>
@endsection