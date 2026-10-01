<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function createLogin(): View
    {
        return view('auth.form', ['isRegistration' => false]);
    }

    public function createRegistration(): View
    {
        return view('auth.form', ['isRegistration' => true]);
    }

    public function createForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $validated['email']]);

        return redirect()->route('password.request')->with(
            'status',
            'Jika email terdaftar, instruksi reset kata sandi akan dikirim ke alamat tersebut.',
        );
    }

    public function createResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Kata sandi berhasil direset. Silakan masuk.');
        }

        return back()->withErrors([
            'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
        ])->withInput($request->only('email'));
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $inactiveOperator = User::query()
                ->where('email', $validated['email'])
                ->where('role', User::ROLE_OPERATOR)
                ->where('is_active', false)
                ->first();

            if ($inactiveOperator !== null && Auth::validate([
                'email' => $validated['email'],
                'password' => $validated['password'],
            ])) {
                Auth::login($inactiveOperator, $request->boolean('remember'));
                $request->session()->regenerate();

                return redirect()->route('account.inactive');
            }

            return back()
                ->withErrors(['email' => 'Email atau kata sandi tidak sesuai, atau akun sedang nonaktif.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $destination = $request->user()->role === User::ROLE_CUSTOMER
            ? route('account.orders.index')
            : route('dashboard');

        $response = redirect()->intended($destination);
        $latestEmploymentEvent = $request->user()->role === User::ROLE_OPERATOR
            ? $request->user()->employmentEvents()->latest('id')->first()
            : null;

        if ($latestEmploymentEvent?->action === 'rehired') {
            return $response->with('employment_notice', [
                'title' => 'Akun diaktifkan kembali',
                'message' => 'Administrator telah mengaktifkan kembali akun Anda. Anda dapat melanjutkan aktivitas.',
                'note' => $latestEmploymentEvent->note,
            ]);
        }

        return $response;
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'no_telepon' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'no_telepon' => $validated['no_telepon'],
                'password' => $validated['password'],
                'role' => User::ROLE_CUSTOMER,
                'is_active' => true,
            ]);

            $customer = Customer::query()->firstOrNew(['user_id' => $user->id]);

            if (! $customer->exists) {
                $customer = Customer::query()
                    ->whereNull('user_id')
                    ->where('email', $user->email)
                    ->first() ?? $customer;
            }

            $customer->fill([
                'user_id' => $user->id,
                'nama' => $user->name,
                'email' => $user->email,
                'no_telepon' => $user->no_telepon,
            ])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.orders.index')
            ->with('status', 'Akun berhasil dibuat. Selamat datang di Toko Kerabat.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
