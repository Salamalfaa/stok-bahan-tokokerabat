<?php

namespace App\Http\Controllers;

use App\Mail\AttendanceWarning;
use App\Models\AttendanceRecord;
use App\Models\DisciplinaryAction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AttendanceController extends Controller
{
    private const ATTENDANCE_PERIOD_DAYS = 30;

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdministrator = $user->role === User::ROLE_ADMIN;
        $statusLabels = [
            AttendanceRecord::STATUS_PRESENT => 'Hadir',
            AttendanceRecord::STATUS_SICK => 'Sakit',
            AttendanceRecord::STATUS_PERMITTED => 'Izin',
            AttendanceRecord::STATUS_ABSENT => 'Alpha',
            AttendanceRecord::STATUS_LATE => 'Terlambat',
        ];
        $kindLabels = [
            DisciplinaryAction::KIND_VERBAL_WARNING => 'Teguran lisan',
            DisciplinaryAction::KIND_WARNING_LETTER => 'Surat peringatan',
            DisciplinaryAction::KIND_OTHER => 'Sanksi lainnya',
        ];

        if ($isAdministrator) {
            $validated = $request->validate([
                'employee_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where('role', User::ROLE_OPERATOR),
                ],
            ]);
            $selectedEmployeeId = $validated['employee_id'] ?? null;
            $employees = User::query()
                ->where('role', User::ROLE_OPERATOR)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']);
            $attendanceRecords = AttendanceRecord::query()
                ->with(['user', 'actor'])
                ->when($selectedEmployeeId !== null, fn ($query) => $query->where('user_id', $selectedEmployeeId))
                ->orderByDesc('attendance_date')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
            $lateSummaries = AttendanceRecord::query()
                ->where('status', AttendanceRecord::STATUS_LATE)
                ->select('user_id')
                ->selectRaw('COUNT(*) as late_count, COALESCE(SUM(late_hours), 0) as late_hours_total')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
            $disciplinaryActions = DisciplinaryAction::query()
                ->with(['user', 'actor'])
                ->when($selectedEmployeeId !== null, fn ($query) => $query->where('user_id', $selectedEmployeeId))
                ->latest('id')
                ->paginate(10, ['*'], 'sanctions')
                ->withQueryString();
            $todaySummary = AttendanceRecord::query()
                ->whereDate('attendance_date', today())
                ->selectRaw(
                    'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as late_count',
                    [AttendanceRecord::STATUS_LATE],
                )
                ->first();
            $attendanceSummary = collect();
        } else {
            $selectedEmployeeId = null;
            $employees = collect();
            $lateSummaries = collect();
            $attendanceRecords = $user->attendanceRecords()
                ->with('actor')
                ->orderByDesc('attendance_date')
                ->orderByDesc('id')
                ->paginate(15);
            $disciplinaryActions = $user->disciplinaryActions()
                ->with('actor')
                ->latest('id')
                ->paginate(10, ['*'], 'sanctions');
            $todaySummary = null;
            $attendanceSummary = $user->attendanceRecords()
                ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(late_hours), 0) as late_hours_total')
                ->groupBy('status')
                ->get()
                ->keyBy('status');
        }

        return view('portal.attendance.index', [
            'attendanceRecords' => $attendanceRecords,
            'attendanceSummary' => $attendanceSummary,
            'disciplinaryActions' => $disciplinaryActions,
            'employees' => $employees,
            'isAdministrator' => $isAdministrator,
            'kindLabels' => $kindLabels,
            'lateSummaries' => $lateSummaries,
            'selectedEmployeeId' => $selectedEmployeeId,
            'statusLabels' => $statusLabels,
            'todayAttendanceCount' => (int) ($todaySummary?->total ?? 0),
            'todayLateCount' => (int) ($todaySummary?->late_count ?? 0),
        ]);
    }

    public function employeeReport(User $user): View
    {
        abort_unless($user->role === User::ROLE_OPERATOR, 404);

        $rows = $this->employeeReportRows($user);
        $records = $rows->pluck('record')->filter();
        $statusLabels = $this->attendanceStatusLabels();

        return view('portal.attendance.employee-report', [
            'employee' => $user,
            'rows' => $rows,
            'startDate' => today()->subDays(self::ATTENDANCE_PERIOD_DAYS - 1),
            'endDate' => today(),
            'statusLabels' => $statusLabels,
            'presentCount' => $records->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
            'sickCount' => $records->where('status', AttendanceRecord::STATUS_SICK)->count(),
            'permittedCount' => $records->where('status', AttendanceRecord::STATUS_PERMITTED)->count(),
            'absentCount' => $records->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'lateCount' => $records->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'lateHoursTotal' => (float) $records->sum(fn (AttendanceRecord $record): float => (float) $record->late_hours),
        ]);
    }

    public function exportEmployeeReport(User $user): StreamedResponse
    {
        abort_unless($user->role === User::ROLE_OPERATOR, 404);

        $rows = $this->employeeReportRows($user);
        $statusLabels = $this->attendanceStatusLabels();
        $filename = 'rekap-absensi-'.Str::slug($user->name).'-'.today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $user, $statusLabels): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('Tidak dapat membuka aliran unduhan rekap absensi.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['REKAP ABSENSI KARYAWAN'], ';', '"', '');
            fputcsv($output, ['Nama karyawan', $this->spreadsheetSafeValue($user->name)], ';', '"', '');
            fputcsv($output, [
                'Periode',
                today()->subDays(self::ATTENDANCE_PERIOD_DAYS - 1)->format('d/m/Y').' - '.today()->format('d/m/Y'),
            ], ';', '"', '');
            fputcsv($output, [], ';', '"', '');
            fputcsv($output, ['No.', 'Tanggal', 'Hari', 'Status', 'Terlambat (jam)', 'Catatan', 'Dicatat oleh'], ';', '"', '');

            foreach ($rows as $index => $row) {
                $record = $row['record'];
                fputcsv($output, [
                    $index + 1,
                    $row['date']->format('d/m/Y'),
                    $row['date']->locale('id')->translatedFormat('l'),
                    $record === null ? 'Belum dicatat' : $statusLabels[$record->status],
                    $record?->late_hours === null ? '' : number_format((float) $record->late_hours, 2, ',', ''),
                    $this->spreadsheetSafeValue($record?->note ?? ''),
                    $this->spreadsheetSafeValue($record?->actor?->name ?? ($record === null ? '' : 'Administrator')),
                ], ';', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_OPERATOR),
            ],
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in([
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_SICK,
                AttendanceRecord::STATUS_PERMITTED,
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_LATE,
            ])],
            'late_hours' => [
                'nullable',
                'required_if:status,'.AttendanceRecord::STATUS_LATE,
                'numeric',
                'decimal:0,2',
                'gt:0',
                'lte:24',
                'prohibited_unless:status,'.AttendanceRecord::STATUS_LATE,
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = User::query()
            ->where('role', User::ROLE_OPERATOR)
            ->findOrFail($validated['employee_id']);
        $record = AttendanceRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('attendance_date', $validated['attendance_date'])
            ->first();
        $record ??= new AttendanceRecord([
            'user_id' => $employee->id,
            'attendance_date' => $validated['attendance_date'],
        ]);
        $record->fill([
            'actor_id' => $request->user()->id,
            'status' => $validated['status'],
            'late_hours' => $validated['late_hours'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);
        $record->save();

        return redirect()
            ->route('attendance.index', ['employee_id' => $employee->id])
            ->with('status', 'Absensi karyawan berhasil disimpan.');
    }

    public function storeDisciplinaryAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_OPERATOR),
            ],
            'kind' => ['required', Rule::in([
                DisciplinaryAction::KIND_VERBAL_WARNING,
                DisciplinaryAction::KIND_WARNING_LETTER,
                DisciplinaryAction::KIND_OTHER,
            ])],
            'note' => ['required', 'string', 'max:5000'],
            'warning_subject' => [
                'exclude_unless:kind,'.DisciplinaryAction::KIND_WARNING_LETTER,
                'nullable',
                Rule::requiredIf($request->input('kind') === DisciplinaryAction::KIND_WARNING_LETTER),
                'string',
                'max:255',
                'not_regex:/[\r\n]/',
            ],
        ]);

        $employee = User::query()
            ->where('role', User::ROLE_OPERATOR)
            ->findOrFail($validated['employee_id']);
        $action = $employee->disciplinaryActions()->create([
            'actor_id' => $request->user()->id,
            'kind' => $validated['kind'],
            'note' => $validated['note'],
            'warning_subject' => $validated['warning_subject'] ?? null,
        ]);

        if ($action->kind !== DisciplinaryAction::KIND_WARNING_LETTER) {
            return redirect()
                ->route('attendance.index', ['employee_id' => $employee->id])
                ->with('status', 'Sanksi berhasil dicatat untuk karyawan tersebut.');
        }

        if (! $this->deliverWarningLetter($action)) {
            return redirect()
                ->route('attendance.index', ['employee_id' => $employee->id])
                ->with('error', 'Surat peringatan tersimpan, tetapi email belum berhasil dikirim. Periksa konfigurasi email lalu coba kirim ulang.');
        }

        return redirect()
            ->route('attendance.index', ['employee_id' => $employee->id])
            ->with('status', 'Surat peringatan berhasil dicatat dan dikirim ke email karyawan terkait.');
    }

    public function resendWarningLetter(DisciplinaryAction $disciplinaryAction): RedirectResponse
    {
        abort_unless(
            $disciplinaryAction->kind === DisciplinaryAction::KIND_WARNING_LETTER
                && $disciplinaryAction->user()->where('role', User::ROLE_OPERATOR)->exists(),
            404,
        );

        if ($disciplinaryAction->email_sent_at !== null) {
            return back()->withErrors(['warning' => 'Surat peringatan ini sudah pernah dikirim.']);
        }

        if (! $this->deliverWarningLetter($disciplinaryAction)) {
            return back()->with('error', 'Email surat peringatan belum berhasil dikirim. Periksa konfigurasi email lalu coba kembali.');
        }

        return redirect()
            ->route('attendance.index', ['employee_id' => $disciplinaryAction->user_id])
            ->with('status', 'Surat peringatan berhasil dikirim ke email karyawan terkait.');
    }

    private function deliverWarningLetter(DisciplinaryAction $action): bool
    {
        $action->loadMissing('user');

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::warning('Surat peringatan tidak dikirim karena mailer tidak mengirim email.', [
                'disciplinary_action_id' => $action->id,
                'mailer' => config('mail.default'),
            ]);

            return false;
        }

        try {
            Mail::to($action->user->email)->send(new AttendanceWarning($action));
        } catch (TransportExceptionInterface $exception) {
            Log::error('Email surat peringatan gagal dikirim.', [
                'disciplinary_action_id' => $action->id,
                'recipient_user_id' => $action->user_id,
                'exception' => $exception,
            ]);

            return false;
        }

        $action->update(['email_sent_at' => now()]);

        return true;
    }

    /**
     * @return Collection<int, array{date: Carbon, record: AttendanceRecord|null}>
     */
    private function employeeReportRows(User $user): Collection
    {
        $startDate = today()->subDays(self::ATTENDANCE_PERIOD_DAYS - 1);
        $attendanceByDate = $user->attendanceRecords()
            ->with('actor:id,name')
            ->whereBetween('attendance_date', [$startDate, today()])
            ->get()
            ->keyBy(fn (AttendanceRecord $record): string => $record->attendance_date->toDateString());

        return collect(range(0, self::ATTENDANCE_PERIOD_DAYS - 1))
            ->map(function (int $dayOffset) use ($attendanceByDate, $startDate): array {
                $date = $startDate->copy()->addDays($dayOffset);

                return [
                    'date' => $date,
                    'record' => $attendanceByDate->get($date->toDateString()),
                ];
            });
    }

    /**
     * @return array<string, string>
     */
    private function attendanceStatusLabels(): array
    {
        return [
            AttendanceRecord::STATUS_PRESENT => 'Hadir',
            AttendanceRecord::STATUS_SICK => 'Sakit',
            AttendanceRecord::STATUS_PERMITTED => 'Izin',
            AttendanceRecord::STATUS_ABSENT => 'Alpha',
            AttendanceRecord::STATUS_LATE => 'Terlambat',
        ];
    }

    private function spreadsheetSafeValue(string $value): string
    {
        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
