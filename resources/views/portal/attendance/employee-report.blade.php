@extends('layouts.portal')

@push('styles')
    @vite('resources/css/attendance.css')
@endpush

@section('title', 'Rekap absensi '.$employee->name)
@section('section', 'Rekap absensi')

@section('content')
    <div class="attendance-page attendance-report-page">
        <div class="portal-page-heading attendance-hero report-hero">
            <div>
                <p class="portal-eyebrow">LEMBAR KERJA KEPEGAWAIAN</p>
                <h1>Rekap absensi.</h1>
                <p class="portal-heading-copy">Riwayat kehadiran {{ $employee->name }} selama 30 hari terakhir.</p>
            </div>
            <div class="report-actions" aria-label="Aksi rekap absensi">
                <a class="btn btn-light report-action-back" href="{{ route('attendance.index') }}">Kembali</a>
                <button class="btn btn-outline-primary report-action-print" type="button" onclick="window.print()">Cetak</button>
                <a class="btn btn-primary report-action-export" href="{{ route('attendance.employees.report.export', $employee) }}">Unduh Excel (.csv)</a>
            </div>
        </div>

        <section class="report-summary-grid" aria-label="Ringkasan kehadiran {{ $employee->name }}">
            <article class="report-summary-card"><span>Hadir</span><strong>{{ $presentCount }}</strong></article>
            <article class="report-summary-card"><span>Terlambat</span><strong>{{ $lateCount }}</strong></article>
            <article class="report-summary-card"><span>Durasi terlambat</span><strong>{{ number_format($lateHoursTotal, 2, ',', '.') }} <small>jam</small></strong></article>
            <article class="report-summary-card"><span>Sakit · Izin · Alpha</span><strong>{{ $sickCount }} · {{ $permittedCount }} · {{ $absentCount }}</strong></article>
        </section>

        <section class="portal-panel report-sheet-panel" aria-labelledby="report-sheet-title">
            <div class="report-sheet-heading">
                <div>
                    <p class="portal-eyebrow">REKAP KEHADIRAN KARYAWAN</p>
                    <h2 id="report-sheet-title">{{ $employee->name }}</h2>
                    <p>Periode {{ $startDate->format('d/m/Y') }} – {{ $endDate->format('d/m/Y') }}</p>
                </div>
                <span class="report-sheet-mark" aria-hidden="true">XLS</span>
            </div>

            <div class="report-sheet-scroll">
                <table class="report-spreadsheet">
                    <caption class="visually-hidden">Rekap absensi {{ $employee->name }} selama 30 hari terakhir</caption>
                    <thead>
                        <tr class="report-column-letters" aria-hidden="true">
                            <th></th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th>
                        </tr>
                        <tr class="report-column-headings">
                            <th scope="col">No.</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Hari</th>
                            <th scope="col">Status kehadiran</th>
                            <th scope="col">Terlambat (jam)</th>
                            <th scope="col">Catatan</th>
                            <th scope="col">Dicatat oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php($record = $row['record'])
                            <tr>
                                <th class="report-row-number" scope="row">{{ $index + 1 }}</th>
                                <td class="report-date-cell">{{ $row['date']->format('d/m/Y') }}</td>
                                <td>{{ $row['date']->locale('id')->translatedFormat('l') }}</td>
                                <td>
                                    @if ($record)
                                        <span @class([
                                            'report-status',
                                            'report-status-present' => $record->status === \App\Models\AttendanceRecord::STATUS_PRESENT,
                                            'report-status-late' => $record->status === \App\Models\AttendanceRecord::STATUS_LATE,
                                            'report-status-other' => in_array($record->status, [
                                                \App\Models\AttendanceRecord::STATUS_SICK,
                                                \App\Models\AttendanceRecord::STATUS_PERMITTED,
                                                \App\Models\AttendanceRecord::STATUS_ABSENT,
                                            ], true),
                                        ])>{{ $statusLabels[$record->status] }}</span>
                                    @else
                                        <span class="report-not-recorded">Belum dicatat</span>
                                    @endif
                                </td>
                                <td class="report-number-cell">{{ $record?->late_hours === null ? '—' : number_format((float) $record->late_hours, 2, ',', '.') }}</td>
                                <td class="report-note-cell">{{ $record?->note ?: '—' }}</td>
                                <td>{{ $record?->actor?->name ?? ($record ? 'Administrator' : '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" scope="row">Ringkasan periode</th>
                            <td>Hadir {{ $presentCount }} · Terlambat {{ $lateCount }}</td>
                            <td>{{ number_format($lateHoursTotal, 2, ',', '.') }} jam</td>
                            <td colspan="2">Sakit {{ $sickCount }} · Izin {{ $permittedCount }} · Alpha {{ $absentCount }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="report-sheet-footer">
                <span>Menampilkan {{ $rows->count() }} hari kalender</span>
                <span>Data yang belum diisi ditandai “Belum dicatat”.</span>
            </div>
        </section>
    </div>
@endsection
