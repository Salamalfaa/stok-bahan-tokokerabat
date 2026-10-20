@extends('layouts.portal')

@push('styles')
    @vite('resources/css/attendance.css')
@endpush

@section('title', 'Absensi karyawan')
@section('section', 'Absensi karyawan')

@section('content')
    <div class="attendance-page">
        <div class="portal-page-heading attendance-hero">
            <div>
                <p class="portal-eyebrow">{{ $isAdministrator ? 'KEPEGAWAIAN' : 'PROFIL KEHADIRAN' }}</p>
                <h1>Absensi karyawan.</h1>
                <p class="portal-heading-copy">
                    @if ($isAdministrator)
                        Kelola kehadiran, keterlambatan, dan catatan sanksi karyawan.
                    @else
                        Riwayat dan profil kehadiran pribadi Anda.
                    @endif
                </p>
            </div>
            @if ($isAdministrator)
                <a class="portal-secondary-button" href="{{ route('users.index') }}">
                    <span>Kelola akun</span><span class="attendance-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </div>

        @if ($isAdministrator)
            <section class="grid grid-cols-1 gap-x-4 gap-y-4 md:grid-cols-3 attendance-summary" aria-label="Ringkasan absensi hari ini">
                <article class="card border-0 shadow-sm attendance-stat-card attendance-stat-employees">
                    <div class="card-body">
                        <div class="attendance-card-top"><span class="attendance-card-icon" aria-hidden="true">K</span><span class="attendance-card-label">Karyawan aktif</span></div>
                        <strong class="attendance-card-value">{{ number_format($employees->where('is_active', true)->count()) }}</strong>
                        <span class="attendance-card-note">akun karyawan yang aktif</span>
                    </div>
                </article>
                <article class="card border-0 shadow-sm attendance-stat-card attendance-stat-records">
                    <div class="card-body">
                        <div class="attendance-card-top"><span class="attendance-card-icon" aria-hidden="true">A</span><span class="attendance-card-label">Absensi hari ini</span></div>
                        <strong class="attendance-card-value">{{ number_format($todayAttendanceCount) }}</strong>
                        <span class="attendance-card-note">karyawan tercatat</span>
                    </div>
                </article>
                <article class="card border-0 shadow-sm attendance-stat-card attendance-stat-late">
                    <div class="card-body">
                        <div class="attendance-card-top"><span class="attendance-card-icon" aria-hidden="true">!</span><span class="attendance-card-label">Terlambat hari ini</span></div>
                        <strong class="attendance-card-value">{{ number_format($todayLateCount) }}</strong>
                        <span class="attendance-card-note">perlu ditinjau</span>
                    </div>
                </article>
            </section>

            <section class="portal-panel attendance-list-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Bahan pertimbangan sanksi</p><h2>Profil keterlambatan karyawan</h2></div>
                    <span class="attendance-count-pill">{{ $employees->count() }} karyawan</span>
                </div>
                <div class="portal-table-wrap attendance-table-wrap">
                    <table class="portal-table table table-hover align-middle attendance-table">
                        <thead><tr><th>Karyawan</th><th>Status akun</th><th>Frekuensi terlambat</th><th>Total durasi</th><th>Profil kehadiran</th></tr></thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                @php($lateSummary = $lateSummaries->get($employee->id))
                                <tr>
                                    <td><strong>{{ $employee->name }}</strong></td>
                                    <td><span class="badge {{ $employee->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td>{{ number_format($lateSummary?->late_count ?? 0) }} kali</td>
                                    <td>{{ number_format((float) ($lateSummary?->late_hours_total ?? 0), 2, ',', '.') }} jam</td>
                                    <td><a class="attendance-table-link" href="{{ route('attendance.employees.report', $employee) }}">Lihat absensi <span aria-hidden="true">&rarr;</span></a></td>
                                </tr>
                            @empty
                                <tr><td class="portal-table-empty" colspan="5">Belum ada akun dengan role karyawan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="portal-panel portal-form-panel attendance-form-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Pencatatan manual</p><h2>Catat absensi</h2></div>
                    <span class="attendance-count-pill">Satu catatan per karyawan per hari</span>
                </div>
                <form class="attendance-form" method="post" action="{{ route('attendance.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="attendance-employee">Nama karyawan</label>
                            <select class="form-select" id="attendance-employee" name="employee_id" required>
                                <option value="">Pilih karyawan</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(old('employee_id', $selectedEmployeeId) == $employee->id)>
                                        {{ $employee->name }}{{ $employee->is_active ? '' : ' · Nonaktif' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="attendance-date">Tanggal</label>
                            <input class="form-control" id="attendance-date" type="date" name="attendance_date" value="{{ old('attendance_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="attendance-status">Status kehadiran</label>
                            <select class="form-select" id="attendance-status" name="status" required data-attendance-status>
                                @foreach ($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected(old('status', 'hadir') === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="attendance-late-hours">Lama keterlambatan (jam)</label>
                            <input class="form-control" id="attendance-late-hours" type="number" name="late_hours" min="0.01" max="24" step="0.01" value="{{ old('late_hours') }}" inputmode="decimal" aria-describedby="late-hours-help" data-attendance-late-hours>
                            <div class="form-text" id="late-hours-help">Isi hanya jika statusnya Terlambat, misalnya 1,5 jam ditulis 1.5.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="attendance-note">Catatan (opsional)</label>
                            <textarea class="form-control" id="attendance-note" name="note" rows="2" maxlength="2000" placeholder="Tambahkan keterangan bila diperlukan">{{ old('note') }}</textarea>
                        </div>
                        <div class="col-12 attendance-form-actions">
                            <button class="btn btn-primary" type="submit">Simpan absensi <span aria-hidden="true">&rarr;</span></button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="portal-panel attendance-list-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Daftar hadir</p><h2>Riwayat absensi</h2></div>
                    <span class="attendance-count-pill">{{ $attendanceRecords->total() }} catatan</span>
                </div>
                @if ($selectedEmployeeId !== null)
                    <div class="attendance-filter-context">
                        <span>Menampilkan absensi dan catatan untuk <strong>{{ $employees->firstWhere('id', $selectedEmployeeId)?->name }}</strong></span>
                        <a href="{{ route('attendance.index') }}">Hapus filter</a>
                    </div>
                @endif
                <form class="attendance-filter" method="get" action="{{ route('attendance.index') }}">
                    <label class="form-label" for="attendance-filter-employee">Filter riwayat karyawan</label>
                    <select class="form-select" id="attendance-filter-employee" name="employee_id">
                        <option value="">Semua karyawan</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) $selectedEmployeeId === (string) $employee->id)>
                                {{ $employee->name }}{{ $employee->is_active ? '' : ' · Nonaktif' }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Tampilkan</button>
                </form>
                <div class="portal-table-wrap attendance-table-wrap">
                    <table class="portal-table table table-hover align-middle attendance-table">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Status</th><th>Terlambat</th><th>Catatan</th><th>Dicatat oleh</th></tr></thead>
                        <tbody>
                            @forelse ($attendanceRecords as $record)
                                <tr>
                                    <td>{{ $record->attendance_date->format('d/m/Y') }}</td>
                                    <td><strong>{{ $record->user->name }}</strong></td>
                                    <td>
                                        <span class="badge attendance-status status-{{ $record->status }}">{{ $statusLabels[$record->status] }}</span>
                                    </td>
                                    <td>{{ $record->late_hours === null ? '—' : number_format((float) $record->late_hours, 2, ',', '.').' jam' }}</td>
                                    <td>{{ $record->note ?: '—' }}</td>
                                    <td>{{ $record->actor?->name ?? 'Administrator' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="portal-table-empty" colspan="6">
                                        <div class="attendance-empty-state">
                                            <span class="attendance-empty-icon" aria-hidden="true">A</span>
                                            <strong>Belum ada catatan absensi</strong>
                                            <span>Catatan yang dibuat administrator akan muncul di sini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="portal-pagination">{{ $attendanceRecords->links() }}</div>
            </section>

            <section class="portal-panel portal-form-panel attendance-form-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Privat per karyawan</p><h2>Catat sanksi atau surat peringatan</h2></div>
                </div>
                <p class="attendance-private-note">Catatan ini hanya dapat dilihat administrator dan karyawan yang dipilih. Surat peringatan dikirim ke email akun karyawan tersebut.</p>
                <form class="attendance-form" method="post" action="{{ route('attendance.sanctions.store') }}" id="attendance-sanction-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sanction-employee">Nama karyawan</label>
                            <select class="form-select" id="sanction-employee" name="employee_id" required>
                                <option value="">Pilih karyawan</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="sanction-kind">Jenis tindakan</label>
                            <select class="form-select" id="sanction-kind" name="kind" required>
                                @foreach ($kindLabels as $kind => $label)
                                    <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="warning-subject">Judul surat peringatan (wajib untuk surat peringatan)</label>
                            <input class="form-control" id="warning-subject" name="warning_subject" value="{{ old('warning_subject') }}" maxlength="255" placeholder="Contoh: Surat Peringatan atas keterlambatan berulang">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sanction-note">Catatan / isi surat</label>
                            <textarea class="form-control" id="sanction-note" name="note" rows="4" maxlength="5000" required placeholder="Tuliskan alasan, rincian sanksi, atau isi surat peringatan">{{ old('note') }}</textarea>
                        </div>
                        <div class="col-12 attendance-form-actions">
                            <button class="btn btn-primary" type="submit">Simpan tindakan <span aria-hidden="true">&rarr;</span></button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="portal-panel attendance-list-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Jejak kepegawaian</p><h2>Riwayat sanksi dan surat</h2></div>
                    <span class="attendance-count-pill">{{ $disciplinaryActions->total() }} catatan</span>
                </div>
                <div class="portal-table-wrap attendance-table-wrap">
                    <table class="portal-table table table-hover align-middle attendance-table">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Jenis</th><th>Catatan</th><th>Status email</th><th>Dicatat oleh</th></tr></thead>
                        <tbody>
                            @forelse ($disciplinaryActions as $action)
                                <tr>
                                    <td>{{ $action->created_at->format('d/m/Y H:i') }}</td>
                                    <td><strong>{{ $action->user->name }}</strong></td>
                                    <td>{{ $kindLabels[$action->kind] }}</td>
                                    <td>
                                        @if ($action->warning_subject)
                                            <strong>{{ $action->warning_subject }}</strong><br>
                                        @endif
                                        {{ $action->note }}
                                    </td>
                                    <td>
                                        @if ($action->kind === \App\Models\DisciplinaryAction::KIND_WARNING_LETTER)
                                            @if ($action->email_sent_at)
                                                <span class="badge bg-success">Terkirim {{ $action->email_sent_at->format('d/m/Y H:i') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Belum terkirim</span>
                                                <form class="attendance-resend-form" method="post" action="{{ route('attendance.warnings.send', $action) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Kirim ulang</button>
                                                </form>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $action->actor?->name ?? 'Administrator' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="portal-table-empty" colspan="6">
                                        <div class="attendance-empty-state">
                                            <span class="attendance-empty-icon" aria-hidden="true">✓</span>
                                            <strong>Belum ada catatan sanksi</strong>
                                            <span>Catatan sanksi dan surat peringatan akan tampil di sini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="portal-pagination">{{ $disciplinaryActions->links() }}</div>
            </section>
        @else
            @php($lateSummary = $attendanceSummary->get(\App\Models\AttendanceRecord::STATUS_LATE))
            <section class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 lg:grid-cols-4 attendance-summary" aria-label="Profil kehadiran pribadi">
                <article class="card border-0 shadow-sm"><div class="card-body"><span class="attendance-card-label">Hari tercatat</span><strong class="attendance-card-value">{{ number_format($attendanceSummary->sum('total')) }}</strong><span class="attendance-card-note">total riwayat absensi</span></div></article>
                <article class="card border-0 shadow-sm"><div class="card-body"><span class="attendance-card-label">Hadir</span><strong class="attendance-card-value">{{ number_format($attendanceSummary->get(\App\Models\AttendanceRecord::STATUS_PRESENT)?->total ?? 0) }}</strong><span class="attendance-card-note">hari kehadiran</span></div></article>
                <article class="card border-0 shadow-sm"><div class="card-body"><span class="attendance-card-label">Terlambat</span><strong class="attendance-card-value">{{ number_format($lateSummary?->total ?? 0) }}</strong><span class="attendance-card-note">{{ number_format((float) ($lateSummary?->late_hours_total ?? 0), 2, ',', '.') }} jam keterlambatan</span></div></article>
                <article class="card border-0 shadow-sm"><div class="card-body"><span class="attendance-card-label">Sakit / izin / alpha</span><strong class="attendance-card-value">{{ number_format(($attendanceSummary->get(\App\Models\AttendanceRecord::STATUS_SICK)?->total ?? 0) + ($attendanceSummary->get(\App\Models\AttendanceRecord::STATUS_PERMITTED)?->total ?? 0) + ($attendanceSummary->get(\App\Models\AttendanceRecord::STATUS_ABSENT)?->total ?? 0)) }}</strong><span class="attendance-card-note">hari tidak hadir</span></div></article>
            </section>

            <section class="portal-panel attendance-list-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Daftar hadir pribadi</p><h2>Riwayat absensi saya</h2></div>
                    <span class="attendance-count-pill">{{ $attendanceRecords->total() }} catatan</span>
                </div>
                <div class="portal-table-wrap attendance-table-wrap">
                    <table class="portal-table table table-hover align-middle attendance-table">
                        <thead><tr><th>Tanggal</th><th>Status</th><th>Terlambat</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @forelse ($attendanceRecords as $record)
                                <tr>
                                    <td>{{ $record->attendance_date->format('d/m/Y') }}</td>
                                    <td><span class="badge attendance-status status-{{ $record->status }}">{{ $statusLabels[$record->status] }}</span></td>
                                    <td>{{ $record->late_hours === null ? '—' : number_format((float) $record->late_hours, 2, ',', '.').' jam' }}</td>
                                    <td>{{ $record->note ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="portal-table-empty" colspan="4">
                                        <div class="attendance-empty-state">
                                            <span class="attendance-empty-icon" aria-hidden="true">A</span>
                                            <strong>Belum ada riwayat kehadiran</strong>
                                            <span>Riwayat absensi untuk akun Anda akan tampil di sini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="portal-pagination">{{ $attendanceRecords->links() }}</div>
            </section>

            <section class="portal-panel attendance-list-panel">
                <div class="portal-panel-heading">
                    <div><p class="portal-eyebrow">Informasi pribadi</p><h2>Sanksi dan surat peringatan saya</h2></div>
                    <span class="attendance-count-pill">{{ $disciplinaryActions->total() }} catatan</span>
                </div>
                <div class="attendance-notice-list">
                    @forelse ($disciplinaryActions as $action)
                        <article class="attendance-notice">
                            <div class="attendance-notice-heading">
                                <span class="badge {{ $action->kind === \App\Models\DisciplinaryAction::KIND_WARNING_LETTER ? 'bg-danger' : 'bg-secondary' }}">{{ $kindLabels[$action->kind] }}</span>
                                <time datetime="{{ $action->created_at->toDateString() }}">{{ $action->created_at->format('d/m/Y H:i') }}</time>
                            </div>
                            @if ($action->warning_subject)
                                <h3>{{ $action->warning_subject }}</h3>
                            @endif
                            <p>{{ $action->note }}</p>
                            @if ($action->kind === \App\Models\DisciplinaryAction::KIND_WARNING_LETTER)
                                <small class="attendance-email-state">
                                    @if ($action->email_sent_at)
                                        Surat juga dikirim ke email akun Anda pada {{ $action->email_sent_at->format('d/m/Y H:i') }}.
                                    @else
                                        Surat tersimpan di profil ini; email belum berhasil dikirim.
                                    @endif
                                </small>
                            @endif
                        </article>
                    @empty
                        <div class="portal-empty-state attendance-empty-state">
                            <span class="attendance-empty-icon" aria-hidden="true">✓</span>
                            <strong>Belum ada catatan sanksi</strong>
                            <span>Jika administrator memberikan sanksi atau surat peringatan, catatannya akan tampil di sini.</span>
                        </div>
                    @endforelse
                </div>
                <div class="portal-pagination">{{ $disciplinaryActions->links() }}</div>
            </section>
        @endif
    </div>
@endsection

@pushOnce('scripts')
    <script>
        const attendanceStatus = document.querySelector('[data-attendance-status]');
        const attendanceLateHours = document.querySelector('[data-attendance-late-hours]');
        const attendanceSanctionKind = document.querySelector('#sanction-kind');
        const attendanceWarningSubject = document.querySelector('#warning-subject');

        function updateAttendanceFormFields() {
            if (attendanceStatus && attendanceLateHours) {
                const isLate = attendanceStatus.value === 'terlambat';
                attendanceLateHours.required = isLate;
                attendanceLateHours.disabled = !isLate;

                if (!isLate) {
                    attendanceLateHours.value = '';
                }
            }

            if (attendanceSanctionKind && attendanceWarningSubject) {
                const isWarningLetter = attendanceSanctionKind.value === 'surat_peringatan';
                attendanceWarningSubject.required = isWarningLetter;
                attendanceWarningSubject.disabled = !isWarningLetter;

                if (!isWarningLetter) {
                    attendanceWarningSubject.value = '';
                }
            }
        }

        attendanceStatus?.addEventListener('change', updateAttendanceFormFields);
        attendanceSanctionKind?.addEventListener('change', updateAttendanceFormFields);
        updateAttendanceFormFields();
    </script>
@endPushOnce
