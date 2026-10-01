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
                            @if ($user->role === 'operator')
                                <div class="employment-history-trigger">
                                    <button class="portal-small-button button-muted" type="button" data-open-employment-history data-history-name="{{ $user->name }}" aria-haspopup="dialog" aria-label="Lihat catatan kepegawaian {{ $user->name }}">Catatan ({{ $user->employmentEvents->count() }})</button>
                                    <template class="employment-history-template">
                                        @forelse ($user->employmentEvents->sortByDesc('created_at') as $event)
                                            <article class="employment-event-card">
                                                <div class="employment-event-heading">
                                                    <strong>{{ ['hired' => '🎉 Direkrut', 'dismissed' => '⚠️ Diberhentikan', 'rehired' => '🌱 Aktif kembali'][$event->action] ?? ucfirst($event->action) }}</strong>
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
                                <details class="employment-action-details">
                                    <summary class="portal-small-button {{ $user->is_active ? 'button-muted' : '' }}">{{ $user->is_active ? 'Pemberhentian ⚠️' : 'Aktifkan kembali 🌱' }}</summary>
                                    @if ($user->is_active)
                                        <form class="employment-action-form" method="post" action="{{ route('users.dismiss', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="portal-field"><span>Alasan / catatan wajib 📝</span><textarea id="employee-note-dismiss-{{ $user->id }}" name="note" rows="3" maxlength="2000" required placeholder="Tuliskan alasan dan catatan dengan jelas"></textarea></label>
                                            <div class="note-emoji-picker" aria-label="Emoji cepat untuk catatan">
                                                @foreach (['⚠️', '📋', '💬', '✅', '🤝'] as $emoji)
                                                    <button type="button" data-insert-emoji="{{ $emoji }}" data-target="employee-note-dismiss-{{ $user->id }}" aria-label="Tambahkan emoji {{ $emoji }}" title="Tambahkan {{ $emoji }}">{{ $emoji }}</button>
                                                @endforeach
                                            </div>
                                            <button class="portal-small-button employment-dismiss-button" type="submit">Simpan pemberhentian</button>
                                        </form>
                                    @else
                                        <form class="employment-action-form" method="post" action="{{ route('users.rehire', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="portal-field"><span>Catatan pengaktifan kembali wajib 📝</span><textarea id="employee-note-rehire-{{ $user->id }}" name="note" rows="3" maxlength="2000" required placeholder="Contoh: Masa sanksi selesai, kembali bertugas"></textarea></label>
                                            <div class="note-emoji-picker" aria-label="Emoji cepat untuk catatan">
                                                @foreach (['🌱', '✅', '🎉', '💼', '🤝'] as $emoji)
                                                    <button type="button" data-insert-emoji="{{ $emoji }}" data-target="employee-note-rehire-{{ $user->id }}" aria-label="Tambahkan emoji {{ $emoji }}" title="Tambahkan {{ $emoji }}">{{ $emoji }}</button>
                                                @endforeach
                                            </div>
                                            <button class="portal-primary-button" type="submit">Aktifkan kembali</button>
                                        </form>
                                    @endif
                                </details>
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