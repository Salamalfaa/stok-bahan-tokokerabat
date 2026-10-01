<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-admin-user')]
#[Description('Buat akun administrator pertama secara interaktif')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama administrator'));
        $email = mb_strtolower(trim((string) $this->ask('Email administrator')));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Nama dan alamat email yang valid wajib diisi.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('Email tersebut sudah digunakan oleh akun lain.');

            return self::FAILURE;
        }

        $this->line('Catatan: karakter kata sandi tidak akan tampil saat diketik. Tekan Enter setelah selesai.');

        while (true) {
            $password = (string) $this->secret('Kata sandi (tersembunyi; minimal 10 karakter, mengandung huruf dan angka)');
            $confirmation = (string) $this->secret('Konfirmasi kata sandi (tersembunyi)');
            $passwordErrors = [];

            if ($password === '') {
                $passwordErrors[] = 'Kata sandi tidak boleh kosong.';
            } else {
                if (mb_strlen($password) < 10) {
                    $passwordErrors[] = 'Gunakan minimal 10 karakter.';
                }

                if (preg_match('/[A-Za-z]/', $password) !== 1) {
                    $passwordErrors[] = 'Tambahkan setidaknya satu huruf Latin.';
                }

                if (preg_match('/[0-9]/', $password) !== 1) {
                    $passwordErrors[] = 'Tambahkan setidaknya satu angka.';
                }
            }

            if ($password !== $confirmation) {
                $passwordErrors[] = 'Konfirmasi kata sandi tidak sama.';
            }

            if ($passwordErrors === []) {
                break;
            }

            $this->components->error(implode(' ', $passwordErrors));
            $this->line('Silakan masukkan kembali kata sandi dan konfirmasinya.');
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->components->info('Akun administrator berhasil dibuat.');

        return self::SUCCESS;
    }
}
