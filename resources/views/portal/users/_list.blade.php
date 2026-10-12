<div class="account-list-results" id="accountSearchResults" data-result-count="{{ $users->total() }}" aria-busy="false">
    <div class="portal-table-wrap account-list-scroll" role="region" aria-label="Daftar akun terdaftar" tabindex="0">
        <table class="portal-table account-access-table">
            <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Bergabung</th><th>Riwayat</th><th>Tindakan</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td data-label="Nama"><strong>{{ $user->name }}</strong><small class="table-subline">{{ $user->no_telepon ?: 'Nomor telepon belum diisi' }}</small></td>
                        <td data-label="Email">{{ $user->email }}</td>
                        <td data-label="Peran"><span class="role-label role-{{ $user->role }}">{{ ['admin' => 'Administrator', 'owner' => 'Pemilik', 'operator' => 'Operator', 'customer' => 'Pelanggan'][$user->role] ?? ucfirst($user->role) }}</span></td>
                        <td data-label="Status"><span class="portal-status {{ $user->is_active ? 'status-ready' : 'status-inactive' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td data-label="Bergabung">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td data-label="Riwayat">
                            @if ($user->role === 'operator' || $user->employmentEvents->isNotEmpty())
                                <div class="employment-history-trigger">
                                    <button class="portal-small-button button-muted" type="button" data-open-employment-history data-history-name="{{ $user->name }}" aria-haspopup="dialog" aria-label="Lihat catatan kepegawaian {{ $user->name }}">Catatan ({{ $user->employmentEvents->count() }})</button>
                                    <template class="employment-history-template">
                                        @forelse ($user->employmentEvents->sortByDesc('created_at') as $event)
                                            <article class="employment-event-card">
                                                <div class="employment-event-heading">
                                                    <strong>{{ ['hired' => '🎉 Direkrut', 'dismissed' => '⚠️ Diberhentikan', 'rehired' => '🌱 Aktif kembali', 'role_changed' => '↔ Peran diubah'][$event->action] ?? ucfirst($event->action) }}</strong>
                                                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('d/m/Y H:i') }} WIB</time>
                                                </div>
                                                <p>{{ $event->note ?: 'Tidak ada catatan tambahan.' }}</p>
                                                <small>Dicatat oleh {{ $event->actor?->name ?? 'Pengguna yang sudah dihapus' }}</small>
                                            </article>
                                        @empty
                                            <p class="employment-history-empty">Belum ada catatan kepegawaian.</p>
                                        @endforelse
                                    </template>
                                </div>
                            @else
                                <span class="table-subline">—</span>
                            @endif
                        </td>
                        <td data-label="Tindakan">
                            @if ($user->is(auth()->user()))
                                <span class="table-subline">Akun Anda</span>
                            @elseif ($user->role === 'operator')
                                <div class="account-admin-actions">
                                    <button class="portal-small-button account-manage-trigger" type="button" data-open-account-management data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-user-role="{{ $user->role }}" data-user-active="{{ $user->is_active ? 'true' : 'false' }}" data-role-url="{{ route('users.role.update', $user) }}" data-access-url="{{ route('users.impersonate.start', $user) }}" data-delete-url="{{ route('users.destroy', $user) }}" data-dismiss-url="{{ route('users.dismiss', $user) }}" data-rehire-url="{{ route('users.rehire', $user) }}" aria-haspopup="dialog" aria-label="Kelola akun {{ $user->name }}">Kelola</button>
                                </div>
                            @else
                                <div class="account-admin-actions">
                                    <form method="post" action="{{ route('users.status.update', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="portal-small-button {{ $user->is_active ? 'button-muted' : '' }}" type="submit">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <details class="account-password-details">
                                        <summary class="portal-small-button button-muted">Atur sandi</summary>
                                        <form class="account-password-form" method="post" action="{{ route('users.password.update', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="portal-field"><span>Kata sandi baru</span><input name="password" type="password" autocomplete="new-password" required minlength="10"></label>
                                            <label class="portal-field"><span>Ulangi kata sandi</span><input name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></label>
                                            <button class="portal-primary-button" type="submit">Perbarui sandi</button>
                                        </form>
                                    </details>
                                    <button class="portal-small-button account-manage-trigger" type="button" data-open-account-management data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-user-role="{{ $user->role }}" data-user-active="{{ $user->is_active ? 'true' : 'false' }}" data-role-url="{{ route('users.role.update', $user) }}" data-access-url="{{ route('users.impersonate.start', $user) }}" data-delete-url="{{ route('users.destroy', $user) }}" data-dismiss-url="{{ route('users.dismiss', $user) }}" data-rehire-url="{{ route('users.rehire', $user) }}" aria-haspopup="dialog" aria-label="Kelola akun {{ $user->name }}">Kelola</button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="portal-table-empty" colspan="7">{{ request('search') ? 'Tidak ada akun yang cocok dengan pencarian “'.request('search').'”.' : 'Belum ada akun.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="portal-pagination">{{ $users->links() }}</div>
</div>