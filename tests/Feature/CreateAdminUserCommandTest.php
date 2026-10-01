<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_command_creates_an_active_admin_with_hashed_password(): void
    {
        $password = 'KerabatAman123';

        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Nama administrator', 'Pemilik Toko Kerabat')
            ->expectsQuestion('Email administrator', 'owner@example.test')
            ->expectsQuestion('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)', $password)
            ->expectsQuestion('Konfirmasi kata sandi (tersembunyi)', $password)
            ->expectsOutputToContain('Akun administrator berhasil dibuat.')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check($password, $admin->password));
    }

    public function test_admin_command_explains_invalid_password_and_retries(): void
    {
        $password = 'KerabatAman123';

        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Nama administrator', 'Pemilik Toko Kerabat')
            ->expectsQuestion('Email administrator', 'owner@example.test')
            ->expectsQuestion('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)', '')
            ->expectsQuestion('Konfirmasi kata sandi (tersembunyi)', '')
            ->expectsOutputToContain('Kata sandi tidak boleh kosong.')
            ->expectsQuestion('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)', $password)
            ->expectsQuestion('Konfirmasi kata sandi (tersembunyi)', $password)
            ->expectsOutputToContain('Akun administrator berhasil dibuat.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_command_retries_when_password_confirmation_differs(): void
    {
        $password = 'KerabatAman123';

        $this->artisan('app:create-admin-user')
            ->expectsQuestion('Nama administrator', 'Pemilik Toko Kerabat')
            ->expectsQuestion('Email administrator', 'owner@example.test')
            ->expectsQuestion('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)', $password)
            ->expectsQuestion('Konfirmasi kata sandi (tersembunyi)', 'KataSandiBerbeda123')
            ->expectsOutputToContain('Konfirmasi kata sandi tidak sama.')
            ->expectsQuestion('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)', $password)
            ->expectsQuestion('Konfirmasi kata sandi (tersembunyi)', $password)
            ->expectsOutputToContain('Akun administrator berhasil dibuat.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
    }
}
