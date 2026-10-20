<?php

namespace Tests\Feature;

use App\Mail\AttendanceWarning;
use App\Models\AttendanceRecord;
use App\Models\DisciplinaryAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_record_and_update_manual_attendance_for_each_day(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($admin)
            ->post(route('attendance.store'), $this->attendanceInput($employee))
            ->assertRedirectToRoute('attendance.index', ['employee_id' => $employee->id]);

        $this->post(route('attendance.store'), $this->attendanceInput($employee, [
            'status' => AttendanceRecord::STATUS_LATE,
            'late_hours' => '1.50',
            'note' => 'Datang terlambat.',
        ]))->assertRedirectToRoute('attendance.index', ['employee_id' => $employee->id]);

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $employee->id,
            'status' => AttendanceRecord::STATUS_LATE,
            'late_hours' => '1.50',
            'note' => 'Datang terlambat.',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_late_attendance_requires_a_positive_duration_of_at_most_24_hours(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($admin)
            ->post(route('attendance.store'), $this->attendanceInput($employee, [
                'status' => AttendanceRecord::STATUS_LATE,
                'late_hours' => '',
            ]))
            ->assertSessionHasErrors('late_hours');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_late_attendance_rejects_durations_over_24_hours_or_two_decimal_places(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        foreach (['24.01', '1.234'] as $lateHours) {
            $this->actingAs($admin)
                ->post(route('attendance.store'), $this->attendanceInput($employee, [
                    'status' => AttendanceRecord::STATUS_LATE,
                    'late_hours' => $lateHours,
                ]))
                ->assertSessionHasErrors('late_hours');
        }

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_employee_sees_only_their_own_attendance_and_sanction_history(): void
    {
        $employee = User::factory()->create([
            'name' => 'Karyawan Pribadi',
            'role' => User::ROLE_OPERATOR,
        ]);
        $anotherEmployee = User::factory()->create([
            'name' => 'Karyawan Rahasia',
            'role' => User::ROLE_OPERATOR,
        ]);
        AttendanceRecord::factory()->for($employee)->create(['note' => 'CATATAN_MILIK_SENDIRI']);
        AttendanceRecord::factory()->for($anotherEmployee)->create(['note' => 'CATATAN_MILIK_ORANG_LAIN']);
        DisciplinaryAction::factory()->for($employee)->create(['note' => 'SANKSI_MILIK_SENDIRI']);
        DisciplinaryAction::factory()->for($anotherEmployee)->create(['note' => 'SANKSI_MILIK_ORANG_LAIN']);

        $this->actingAs($employee)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('CATATAN_MILIK_SENDIRI')
            ->assertSee('SANKSI_MILIK_SENDIRI')
            ->assertDontSee('Karyawan Rahasia')
            ->assertDontSee('CATATAN_MILIK_ORANG_LAIN')
            ->assertDontSee('SANKSI_MILIK_ORANG_LAIN');
    }

    public function test_administrator_can_review_attendance_for_multiple_employees(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $firstEmployee = User::factory()->create([
            'name' => 'Karyawan Pertama',
            'role' => User::ROLE_OPERATOR,
        ]);
        $secondEmployee = User::factory()->create([
            'name' => 'Karyawan Kedua',
            'role' => User::ROLE_OPERATOR,
        ]);
        AttendanceRecord::factory()->for($firstEmployee)->create([
            'status' => AttendanceRecord::STATUS_LATE,
            'late_hours' => '1.50',
        ]);
        AttendanceRecord::factory()->for($secondEmployee)->create();

        $this->actingAs($admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Karyawan Pertama')
            ->assertSee('Karyawan Kedua')
            ->assertSee('1,50 jam')
            ->assertSee('Catat absensi');
    }

    public function test_administrator_employee_filter_scopes_attendance_and_sanction_history_together(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $selectedEmployee = User::factory()->create([
            'name' => 'Karyawan Terpilih',
            'role' => User::ROLE_OPERATOR,
        ]);
        $otherEmployee = User::factory()->create([
            'name' => 'Karyawan Lain',
            'role' => User::ROLE_OPERATOR,
        ]);
        AttendanceRecord::factory()->for($selectedEmployee)->create(['note' => 'ABSENSI_TERPILIH']);
        AttendanceRecord::factory()->for($otherEmployee)->create(['note' => 'ABSENSI_LAIN']);
        DisciplinaryAction::factory()->for($selectedEmployee)->create(['note' => 'SANKSI_TERPILIH']);
        DisciplinaryAction::factory()->for($otherEmployee)->create(['note' => 'SANKSI_LAIN']);

        $this->actingAs($admin)
            ->get(route('attendance.index', ['employee_id' => $selectedEmployee->id]))
            ->assertOk()
            ->assertSee('ABSENSI_TERPILIH')
            ->assertSee('SANKSI_TERPILIH')
            ->assertSee('Karyawan Terpilih')
            ->assertDontSee('ABSENSI_LAIN')
            ->assertDontSee('SANKSI_LAIN');
    }

    public function test_administrator_report_shows_only_the_selected_employees_last_30_days(): void
    {
        $this->travelTo('2026-10-13 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'name' => 'Karyawan Rekap',
            'role' => User::ROLE_OPERATOR,
        ]);
        $anotherEmployee = User::factory()->create([
            'name' => 'Karyawan Rahasia',
            'role' => User::ROLE_OPERATOR,
        ]);
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => '2026-09-14',
            'status' => AttendanceRecord::STATUS_LATE,
            'late_hours' => '1.50',
            'note' => 'TERLAMBAT_DALAM_PERIODE',
        ]);
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => '2026-09-13',
            'note' => 'ABSENSI_DI_LUAR_PERIODE',
        ]);
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => '2026-10-13',
            'status' => AttendanceRecord::STATUS_PRESENT,
            'note' => 'HADIR_HARI_INI',
        ]);
        AttendanceRecord::factory()->for($anotherEmployee)->create([
            'attendance_date' => '2026-10-13',
            'note' => 'CATATAN_KARYAWAN_LAIN',
        ]);

        $this->actingAs($admin)
            ->get(route('attendance.employees.report', $employee))
            ->assertSee('Karyawan Rekap')
            ->assertSee('14/09/2026')
            ->assertSee('13/10/2026')
            ->assertSee('TERLAMBAT_DALAM_PERIODE')
            ->assertSee('HADIR_HARI_INI')
            ->assertSee('1,50 jam')
            ->assertSee('Belum dicatat')
            ->assertDontSee('ABSENSI_DI_LUAR_PERIODE')
            ->assertDontSee('Karyawan Rahasia')
            ->assertDontSee('CATATAN_KARYAWAN_LAIN');
    }

    public function test_administrator_can_download_an_excel_compatible_employee_report(): void
    {
        $this->travelTo('2026-10-13 10:00:00');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create([
            'name' => 'Karyawan Export',
            'role' => User::ROLE_OPERATOR,
        ]);
        $anotherEmployee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        AttendanceRecord::factory()->for($employee)->create([
            'attendance_date' => '2026-10-13',
            'status' => AttendanceRecord::STATUS_LATE,
            'late_hours' => '0.75',
            'note' => '=HYPERLINK("https://example.com")',
        ]);
        AttendanceRecord::factory()->for($anotherEmployee)->create([
            'attendance_date' => '2026-10-13',
            'note' => 'CATATAN_RAHASIA_EXPORT',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('attendance.employees.report.export', $employee));
        $content = $response->streamedContent();

        $response->assertDownload('rekap-absensi-karyawan-export-2026-10-13.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Karyawan Export', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString('Belum dicatat', $content);
        $this->assertStringNotContainsString('CATATAN_RAHASIA_EXPORT', $content);
    }

    public function test_employee_cannot_access_another_employees_report_or_export(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $anotherEmployee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($employee)
            ->get(route('attendance.employees.report', $anotherEmployee))
            ->assertForbidden();
        $this->get(route('attendance.employees.report.export', $anotherEmployee))
            ->assertForbidden();
    }

    public function test_administrator_cannot_view_a_report_for_a_non_employee_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)
            ->get(route('attendance.employees.report', $customer))
            ->assertNotFound();
    }

    public function test_employee_cannot_write_attendance_or_customer_access_the_attendance_page(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $anotherEmployee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($employee)
            ->post(route('attendance.store'), $this->attendanceInput($employee))
            ->assertForbidden();
        $this->post(route('attendance.sanctions.store'), [
            'employee_id' => $anotherEmployee->id,
            'kind' => DisciplinaryAction::KIND_VERBAL_WARNING,
            'note' => 'Catatan privat.',
        ])->assertForbidden();
        $this->assertDatabaseCount('attendance_records', 0);
        $this->assertDatabaseCount('disciplinary_actions', 0);

        $this->actingAs($customer)
            ->get(route('attendance.index'))
            ->assertForbidden();
    }

    public function test_administrator_sends_a_warning_letter_to_the_selected_employee(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $anotherEmployee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($admin)
            ->post(route('attendance.sanctions.store'), [
                'employee_id' => $employee->id,
                'kind' => DisciplinaryAction::KIND_WARNING_LETTER,
                'warning_subject' => 'Surat Peringatan',
                'note' => 'Keterlambatan berulang.',
            ])
            ->assertRedirectToRoute('attendance.index', ['employee_id' => $employee->id]);

        Mail::assertSent(
            AttendanceWarning::class,
            fn (AttendanceWarning $mail): bool => $mail->hasTo($employee->email),
        );
        $this->assertDatabaseHas('disciplinary_actions', [
            'user_id' => $employee->id,
            'actor_id' => $admin->id,
            'kind' => DisciplinaryAction::KIND_WARNING_LETTER,
            'warning_subject' => 'Surat Peringatan',
        ]);
        $this->assertNotNull($employee->disciplinaryActions()->firstOrFail()->email_sent_at);

        $this->actingAs($anotherEmployee)
            ->get(route('attendance.index'))
            ->assertDontSee('Keterlambatan berulang.');
    }

    public function test_warning_letter_remains_unsent_until_mail_delivery_is_configured_and_can_be_retried(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($admin)
            ->post(route('attendance.sanctions.store'), [
                'employee_id' => $employee->id,
                'kind' => DisciplinaryAction::KIND_WARNING_LETTER,
                'warning_subject' => 'Surat Peringatan',
                'note' => 'Keterlambatan berulang.',
            ])
            ->assertSessionHas('error');

        $action = $employee->disciplinaryActions()->firstOrFail();
        $this->assertNull($action->email_sent_at);

        config(['mail.default' => 'smtp']);

        $this->post(route('attendance.warnings.send', $action))
            ->assertRedirectToRoute('attendance.index', ['employee_id' => $employee->id]);

        Mail::assertSent(
            AttendanceWarning::class,
            fn (AttendanceWarning $mail): bool => $mail->hasTo($employee->email),
        );
        $this->assertNotNull($action->refresh()->email_sent_at);
    }

    public function test_employee_cannot_delete_an_account_with_attendance_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        AttendanceRecord::factory()->for($employee)->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $employee))
            ->assertSessionHasErrors('user');

        $this->assertModelExists($employee);
        $this->assertDatabaseHas('attendance_records', ['user_id' => $employee->id]);
    }

    public function test_warning_letter_content_escapes_employee_supplied_text(): void
    {
        $employee = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $action = DisciplinaryAction::factory()->for($employee)->create([
            'kind' => DisciplinaryAction::KIND_WARNING_LETTER,
            'warning_subject' => 'Peringatan',
            'note' => '<script>alert("x")</script>',
        ]);

        $content = (new AttendanceWarning($action->load('user')))->render();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $content);
    }

    /**
     * @param  array<string, int|string>  $overrides
     * @return array<string, int|string>
     */
    private function attendanceInput(User $employee, array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'status' => AttendanceRecord::STATUS_PRESENT,
            'late_hours' => '',
            'note' => '',
        ], $overrides);
    }
}
