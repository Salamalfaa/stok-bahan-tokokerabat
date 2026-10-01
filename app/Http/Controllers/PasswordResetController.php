<?php

namespace App\Http\Controllers;

use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function createRequestForm(Request $request): View
    {
        if ($request->boolean('new')) {
            $request->session()->forget('password_recovery_submitted');
        }

        $recoveryRequestId = $request->session()->get('password_recovery_request_id');
        $approvedReset = null;

        if (is_int($recoveryRequestId)) {
            $recoveryRequest = PasswordRecoveryRequest::query()->find($recoveryRequestId);
            if ($recoveryRequest !== null
                && $recoveryRequest->status === PasswordRecoveryRequest::STATUS_APPROVED
                && $recoveryRequest->expires_at->isFuture()) {
                $user = $recoveryRequest->user;
                if ($user !== null) {
                    $approvedReset = [
                        'email' => $user->email,
                        'token' => Password::broker()->createToken($user),
                    ];
                }
            }
        }

        return view('auth.forgot-password', [
            'requestSubmitted' => $request->session()->get('password_recovery_submitted') === true,
            'hasTrackedRequest' => is_int($recoveryRequestId),
            'approvedReset' => $approvedReset,
        ]);
    }

    public function createResetForm(Request $request, string $token): View|RedirectResponse
    {
        $email = $request->string('email')->toString();
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ]);
        }

        if ($user->role !== User::ROLE_ADMIN && ! $this->hasApprovedRequest($user)) {
            return redirect()->route('password.request')->with(
                'status',
                'Permintaan reset harus disetujui administrator sebelum kata sandi dapat diubah.',
            );
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Password::broker()->tokenExists($user, $validated['token'])) {
            return back()->withErrors([
                'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
            ])->withInput($request->only('email'));
        }

        if ($user->role !== User::ROLE_ADMIN && ! $this->hasApprovedRequest($user)) {
            return back()->withErrors([
                'email' => 'Permintaan reset harus disetujui administrator sebelum kata sandi dapat diubah.',
            ])->withInput($request->only('email'));
        }

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            if ($user->role !== User::ROLE_ADMIN) {
                $user->passwordRecoveryRequests()
                    ->where('status', PasswordRecoveryRequest::STATUS_APPROVED)
                    ->latest('resolved_at')
                    ->first()?->update([
                        'status' => PasswordRecoveryRequest::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
            }
        });

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->forget([
                'password_recovery_request_id',
                'password_recovery_submitted',
            ]);

            return redirect()->route('login')->with(
                'status',
                'Kata sandi berhasil direset. Silakan masuk.',
            );
        }

        return back()->withErrors([
            'email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.',
        ])->withInput($request->only('email'));
    }

    private function hasApprovedRequest(User $user): bool
    {
        return $user->passwordRecoveryRequests()
            ->where('status', PasswordRecoveryRequest::STATUS_APPROVED)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
