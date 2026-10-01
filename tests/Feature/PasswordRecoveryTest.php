<?php

namespace Tests\Feature;

use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_employee_requests_are_recorded_for_admin_review(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@example.test',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $employee = User::factory()->create([
            'email' => 'employee@example.test',
            'role' => User::ROLE_OPERATOR,
        ]);

        foreach ([$customer, $employee] as $user) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertRedirectToRoute('password.request')
                ->assertSessionHas('status', 'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.');
        }

        $this->assertDatabaseHas('password_recovery_requests', [
            'user_id' => $customer->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('password_recovery_requests', [
            'user_id' => $employee->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_repeated_submissions_do_not_create_duplicate_active_requests(): void
    {
        $user = User::factory()->create(['email' => 'repeated@example.test']);

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertDatabaseCount('password_recovery_requests', 1);
    }

    public function test_admin_can_open_the_recovery_inbox_and_approve_a_request(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create([
            'email' => 'forgot@example.test',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('users.password-recovery.index'))
            ->assertOk()
            ->assertSee('Permintaan reset kata sandi')
            ->assertSee($user->email);

        $this->post(route('users.password-recovery.approve', $recoveryRequest))
            ->assertRedirectToRoute('users.password-recovery.index')
            ->assertSessionHas('status', 'Permintaan disetujui; tautan reset telah dikirim ke email pengguna.');

        $this->assertDatabaseHas('password_recovery_requests', [
            'id' => $recoveryRequest->id,
            'status' => PasswordRecoveryRequest::STATUS_APPROVED,
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_admin_users_page_shows_a_linked_pending_recovery_badge(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['email' => 'badge@example.test']);
        PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.password-recovery.index'), false)
            ->assertSee('Notifikasi reset')
            ->assertSee('1');
    }

    public function test_guest_cannot_open_the_recovery_inbox(): void
    {
        $this->get(route('users.password-recovery.index'))->assertRedirectToRoute('login');
    }

    public function test_admin_can_delete_selected_recovery_requests(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $userOne = User::factory()->create(['email' => 'delete-one@example.test']);
        $userTwo = User::factory()->create(['email' => 'delete-two@example.test']);
        $requestOne = PasswordRecoveryRequest::create([
            'user_id' => $userOne->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);
        $requestTwo = PasswordRecoveryRequest::create([
            'user_id' => $userTwo->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->delete(route('users.password-recovery.destroy'), ['ids' => [$requestOne->id, $requestTwo->id]])
            ->assertRedirect(route('users.password-recovery.index'))
            ->assertSessionHas('status', 'Permintaan reset yang dipilih berhasil dihapus.');

        $this->assertDatabaseMissing('password_recovery_requests', ['id' => $requestOne->id]);
        $this->assertDatabaseMissing('password_recovery_requests', ['id' => $requestTwo->id]);
    }

    public function test_admin_reset_request_uses_the_existing_email_reset_flow(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = User::factory()->create([
            'email' => 'admin-reset@example.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post(route('password.email'), ['email' => $admin->email])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHas('status', 'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.');

        Notification::assertSentTo($admin, ResetPassword::class);
        $this->assertDatabaseCount('password_recovery_requests', 0);
    }

    public function test_password_recovery_result_is_rendered_as_a_status_notice_without_revealing_account_existence(): void
    {
        $this->post(route('password.email'), ['email' => 'notice@example.test'])
            ->assertRedirectToRoute('password.request');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('recovery-notice', false)
            ->assertSee('Permintaan diterima')
            ->assertSee('Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.')
            ->assertDontSee('Menunggu pemeriksaan administrator');

        $this->assertDatabaseCount('password_recovery_requests', 0);
    }

    public function test_recovery_form_is_replaced_by_notice_and_can_be_opened_again(): void
    {
        $user = User::factory()->create(['email' => 'submitted@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirectToRoute('password.request');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('recovery-notice', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('Kirim permintaan pemulihan')
            ->assertSee('Ajukan permintaan lain');

        $this->get(route('password.request', ['new' => 1]))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('Kirim permintaan pemulihan')
            ->assertDontSee('recovery-notice', false);
    }

    public function test_requester_can_poll_approval_state_without_receiving_a_reset_token(): void
    {
        $user = User::factory()->create([
            'email' => 'poll@example.test',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHas('password_recovery_request_id');
        $recoveryRequest = PasswordRecoveryRequest::query()->where('user_id', $user->id)->firstOrFail();

        $this->get(route('password.recovery.status'))
            ->assertOk()
            ->assertJsonPath('status', PasswordRecoveryRequest::STATUS_PENDING)
            ->assertJsonMissingPath('resetUrl');

        $token = Password::createToken($user);
        $recoveryRequest->update(['status' => PasswordRecoveryRequest::STATUS_APPROVED]);

        $this->get(route('password.recovery.status'))
            ->assertOk()
            ->assertJsonPath('status', PasswordRecoveryRequest::STATUS_APPROVED)
            ->assertJsonPath('message', 'Permintaan disetujui. Tautan reset sekali pakai telah dikirim ke email akun.')
            ->assertDontSee($token);
    }

    public function test_untracked_recovery_status_does_not_claim_that_an_email_is_pending(): void
    {
        $this->get(route('password.recovery.status'))
            ->assertOk()
            ->assertJsonPath('status', 'untracked')
            ->assertJsonMissingPath('resetUrl');
    }

    public function test_approval_status_for_local_log_mailer_directs_user_to_admin_not_to_an_exposed_token(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_APPROVED,
        ]);
        session(['password_recovery_request_id' => $recoveryRequest->id]);

        $this->get(route('password.recovery.status'))
            ->assertOk()
            ->assertJsonPath('status', PasswordRecoveryRequest::STATUS_APPROVED)
            ->assertJsonPath('message', 'Permintaan disetujui. Hubungi administrator untuk menerima tautan reset melalui kanal yang aman.')
            ->assertJsonMissingPath('resetUrl');
    }

    public function test_non_admin_cannot_open_or_process_recovery_requests(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        foreach ([User::ROLE_CUSTOMER, User::ROLE_OPERATOR, User::ROLE_OWNER] as $role) {
            $unprivilegedUser = User::factory()->create(['role' => $role]);

            $this->actingAs($unprivilegedUser)
                ->get(route('users.password-recovery.index'))
                ->assertForbidden();
            $this->post(route('users.password-recovery.approve', $recoveryRequest))
                ->assertForbidden();
        }

        $this->assertSame(PasswordRecoveryRequest::STATUS_PENDING, $recoveryRequest->refresh()->status);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_admin_rejection_resolves_request_without_creating_a_reset_token(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('users.password-recovery.reject', $recoveryRequest))
            ->assertRedirectToRoute('users.password-recovery.index')
            ->assertSessionHas('status', 'Permintaan reset ditolak.');

        $this->assertDatabaseHas('password_recovery_requests', [
            'id' => $recoveryRequest->id,
            'status' => PasswordRecoveryRequest::STATUS_REJECTED,
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_local_log_mailer_shows_a_manual_reset_link_to_admin_instead_of_an_error(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('users.password-recovery.approve', $recoveryRequest))
            ->assertRedirectToRoute('users.password-recovery.index')
            ->assertSessionHas('status', 'Permintaan disetujui. Salin tautan reset berikut dan kirimkan kepada pengguna melalui kanal yang aman.')
            ->assertSessionHas('manualResetUrl');

        $this->assertSame(PasswordRecoveryRequest::STATUS_APPROVED, $recoveryRequest->refresh()->status);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Notification::assertNothingSent();

        $this->get(route('users.password-recovery.index'))
            ->assertOk()
            ->assertSee('Tautan reset sementara')
            ->assertSee('reset-kata-sandi/', false)
            ->assertSee($user->email);
    }

    public function test_failed_reset_email_delivery_reopens_the_request_and_deletes_its_token(): void
    {
        config(['mail.default' => 'unconfigured']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('users.password-recovery.approve', $recoveryRequest))
            ->assertSessionHasErrors('recovery');

        $this->assertSame(PasswordRecoveryRequest::STATUS_PENDING, $recoveryRequest->refresh()->status);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_customer_cannot_reset_password_before_admin_approval(): void
    {
        $user = User::factory()->create(['email' => 'pending@example.test']);
        $token = Password::createToken($user);
        PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertRedirectToRoute('password.request');
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_invalid_reset_token_does_not_reveal_recovery_approval_state(): void
    {
        $user = User::factory()->create(['email' => 'invalid-token@example.test']);

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertSessionHasErrors([
            'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
        ]);
    }

    public function test_approved_request_renders_password_reset_form_on_the_recovery_page(): void
    {
        $user = User::factory()->create(['email' => 'approved-form@example.test']);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_APPROVED,
        ]);

        $this->withSession(['password_recovery_request_id' => $recoveryRequest->id])
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('Kata sandi baru')
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);

        $token = Password::createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertRedirectToRoute('login');

        $this->assertTrue(Hash::check('KataSandiBaru123', $user->refresh()->password));
    }

    public function test_approved_request_allows_one_reset_and_is_marked_completed(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['email' => 'approved@example.test']);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('users.password-recovery.approve', $recoveryRequest))
            ->assertRedirectToRoute('users.password-recovery.index');
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertInstanceOf(ResetPassword::class, $notification);
        $token = $notification->token;

        $this->post(route('logout'));
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Kata sandi baru');
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ];
        $this->post(route('password.update'), $payload)->assertRedirectToRoute('login');
        $this->assertTrue(Hash::check('KataSandiBaru123', $user->refresh()->password));
        $this->assertSame(PasswordRecoveryRequest::STATUS_COMPLETED, $recoveryRequest->refresh()->status);
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Kata sandi berhasil direset. Silakan masuk.')
            ->assertSee('Masuk ke akunmu')
            ->assertSee('Lanjutkan cerita bersama Toko Kerabat.');

        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'KataSandiBaru123',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_application_uses_indonesian_western_timezone_and_locale(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
    }
}
