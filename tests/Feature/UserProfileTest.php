<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_updates_identity_photo_and_linked_customer_details(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'before@example.test',
            'no_telepon' => '081111111111',
        ]);
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'no_telepon' => $user->no_telepon,
        ]);
        $oldPhotoPath = 'profile-photos/'.$user->id.'/previous.jpg';
        Storage::disk('public')->put($oldPhotoPath, 'previous photo');
        $user->profile_photo_path = $oldPhotoPath;
        $user->save();
        $photo = UploadedFile::fake()->image('profile.png', 120, 120);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Admin Baru',
            'email' => 'after@example.test',
            'no_telepon' => '081234567890',
            'profile_photo' => $photo,
            'role' => User::ROLE_OPERATOR,
            'is_active' => false,
        ])->assertRedirectToRoute('profile.edit');

        $user->refresh();

        $this->assertSame('Nama Admin Baru', $user->name);
        $this->assertSame('after@example.test', $user->email);
        $this->assertSame('081234567890', $user->no_telepon);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->profile_photo_path);
        $this->assertNotSame($oldPhotoPath, $user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        Storage::disk('public')->assertMissing($oldPhotoPath);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'nama' => 'Nama Admin Baru',
            'email' => 'after@example.test',
            'no_telepon' => '081234567890',
        ]);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('profilePhotoPreview')
            ->assertSee(Storage::disk('public')->url($user->profile_photo_path), false);
    }

    public function test_deleting_profile_photo_clears_the_saved_photo_and_renders_the_fallback(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Rani']);
        $photoPath = 'profile-photos/'.$user->id.'/profile.jpg';
        Storage::disk('public')->put($photoPath, 'profile photo');
        $user->profile_photo_path = $photoPath;
        $user->save();

        $this->actingAs($user)
            ->delete(route('profile.photo.destroy'))
            ->assertRedirectToRoute('profile.edit')
            ->assertSessionHas('status', 'Foto profil dihapus.');

        $this->assertNull($user->refresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);

        $this->get(route('profile.edit'))
            ->assertDontSee(Storage::disk('public')->url($photoPath), false)
            ->assertDontSee('Hapus foto profil')
            ->assertSee('R</span>', false);
    }

    public function test_customer_cannot_change_email_to_an_address_used_by_another_customer_profile(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'email' => 'customer.one@example.test',
        ]);
        Customer::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        Customer::factory()->create([
            'user_id' => null,
            'email' => 'customer.guest@example.test',
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'customer.guest@example.test',
                'no_telepon' => $user->no_telepon,
            ])
            ->assertRedirectToRoute('profile.edit')
            ->assertSessionHasErrors('email');

        $this->assertSame('customer.one@example.test', $user->refresh()->email);
    }

    public function test_guest_cannot_open_profile_settings(): void
    {
        $this->get(route('profile.edit'))->assertRedirectToRoute('login');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'PasswordLama123']);

        $this->actingAs($user)->patch(route('profile.password.update'), [
            'current_password' => 'bukan-password-lama',
            'password' => 'PasswordBaru456',
            'password_confirmation' => 'PasswordBaru456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('PasswordLama123', $user->refresh()->password));

        $this->patch(route('profile.password.update'), [
            'current_password' => 'PasswordLama123',
            'password' => 'PasswordBaru456',
            'password_confirmation' => 'PasswordBaru456',
        ])->assertRedirectToRoute('profile.edit');

        $this->assertTrue(Hash::check('PasswordBaru456', $user->refresh()->password));
    }

    public function test_forgot_password_request_creates_an_admin_review_request(): void
    {
        $user = User::factory()->create(['email' => 'forgot@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHas('status', 'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.');

        $this->assertDatabaseHas('password_recovery_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_request_does_not_reveal_unknown_emails(): void
    {
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHas('status', 'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.');

        $this->assertDatabaseCount('password_recovery_requests', 0);
    }

    public function test_log_mailer_does_not_create_a_reset_token_that_would_be_written_to_logs(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['email' => 'local-reset@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHas('status', 'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.');

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseHas('password_recovery_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_reset_token_changes_password_once_and_then_expires(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $recoveryRequest = PasswordRecoveryRequest::create([
            'user_id' => $user->id,
            'status' => PasswordRecoveryRequest::STATUS_APPROVED,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        $token = Password::createToken($user);

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

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Kata sandi berhasil direset. Silakan masuk.')
            ->assertSee('Masuk ke akunmu')
            ->assertSee('Lanjutkan cerita bersama Toko Kerabat.');

        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    }
}
