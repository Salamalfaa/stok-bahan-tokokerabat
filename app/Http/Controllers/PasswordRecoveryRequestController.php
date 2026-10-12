<?php

namespace App\Http\Controllers;

use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordRecoveryRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->session()->forget('password_recovery_request_id');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user?->role === User::ROLE_ADMIN) {
            if (config('mail.default') === 'log') {
                return $this->genericResponse($request);
            }

            Password::sendResetLink(['email' => $user->email]);

            return $this->genericResponse($request);
        }

        if ($user !== null && in_array($user->role, [User::ROLE_CUSTOMER, User::ROLE_OPERATOR, User::ROLE_OWNER], true)) {
            $recoveryRequestId = DB::transaction(function () use ($user): ?int {
                $lockedUser = User::query()->lockForUpdate()->find($user->id);

                if ($lockedUser === null) {
                    return null;
                }

                $lockedUser->passwordRecoveryRequests()
                    ->whereIn('status', [PasswordRecoveryRequest::STATUS_PENDING, PasswordRecoveryRequest::STATUS_APPROVED])
                    ->where('expires_at', '<=', now())
                    ->update(['status' => PasswordRecoveryRequest::STATUS_EXPIRED]);

                $activeRequest = $lockedUser->passwordRecoveryRequests()
                    ->whereIn('status', [PasswordRecoveryRequest::STATUS_PENDING, PasswordRecoveryRequest::STATUS_APPROVED])
                    ->where('expires_at', '>', now())
                    ->latest('requested_at')
                    ->first();

                if ($activeRequest !== null) {
                    return $activeRequest->id;
                }

                return $lockedUser->passwordRecoveryRequests()->create([
                    'status' => PasswordRecoveryRequest::STATUS_PENDING,
                    'requested_at' => now(),
                    'expires_at' => now()->addHours(24),
                ])->id;
            });

            if ($recoveryRequestId !== null) {
                $request->session()->put('password_recovery_request_id', $recoveryRequestId);
            }
        }

        return $this->genericResponse($request);
    }

    public function index(): View
    {
        PasswordRecoveryRequest::query()
            ->whereIn('status', [PasswordRecoveryRequest::STATUS_PENDING, PasswordRecoveryRequest::STATUS_APPROVED])
            ->where('expires_at', '<=', now())
            ->update(['status' => PasswordRecoveryRequest::STATUS_EXPIRED]);

        $requests = PasswordRecoveryRequest::query()
            ->with(['user', 'resolvedBy'])
            ->latest('requested_at')
            ->paginate(20);
        $pendingRequestCount = PasswordRecoveryRequest::query()
            ->where('status', PasswordRecoveryRequest::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count();

        return view('portal.users.password-recovery', compact('requests', 'pendingRequestCount'));
    }

    public function status(Request $request): JsonResponse
    {
        $recoveryRequestId = $request->session()->get('password_recovery_request_id');

        if (! is_int($recoveryRequestId)) {
            return response()->json([
                'status' => 'untracked',
                'message' => 'Jika email terdaftar, permintaan akan diproses sesuai prosedur keamanan akun.',
            ])->header('Cache-Control', 'no-store');
        }

        $recoveryRequest = PasswordRecoveryRequest::query()->find($recoveryRequestId);

        if ($recoveryRequest === null) {
            $request->session()->forget('password_recovery_request_id');

            return response()->json([
                'status' => 'untracked',
                'message' => 'Jika email terdaftar, permintaan akan diproses sesuai prosedur keamanan akun.',
            ])->header('Cache-Control', 'no-store');
        }

        if (in_array($recoveryRequest->status, [PasswordRecoveryRequest::STATUS_PENDING, PasswordRecoveryRequest::STATUS_APPROVED], true)
            && $recoveryRequest->expires_at->isPast()) {
            $recoveryRequest->update(['status' => PasswordRecoveryRequest::STATUS_EXPIRED]);
        }

        $message = match ($recoveryRequest->status) {
            PasswordRecoveryRequest::STATUS_PENDING => 'Permintaan masih menunggu pemeriksaan administrator.',
            PasswordRecoveryRequest::STATUS_APPROVED => config('mail.default') === 'log'
                ? 'Permintaan disetujui. Hubungi administrator untuk menerima tautan reset melalui kanal yang aman.'
                : 'Permintaan disetujui. Tautan reset sekali pakai telah dikirim ke email akun.',
            PasswordRecoveryRequest::STATUS_REJECTED => 'Permintaan reset tidak disetujui. Hubungi administrator untuk informasi lebih lanjut.',
            PasswordRecoveryRequest::STATUS_COMPLETED => 'Kata sandi telah diperbarui. Silakan masuk menggunakan kata sandi baru.',
            default => 'Permintaan reset sudah kedaluwarsa. Silakan ajukan permintaan baru.',
        };

        return response()->json([
            'status' => $recoveryRequest->status,
            'message' => $message,
        ])->header('Cache-Control', 'no-store');
    }

    public function approve(Request $request, PasswordRecoveryRequest $recoveryRequest): RedirectResponse
    {
        $resetToken = '';
        $result = DB::transaction(function () use ($request, $recoveryRequest, &$resetToken): string {
            $lockedRequest = PasswordRecoveryRequest::query()
                ->lockForUpdate()
                ->findOrFail($recoveryRequest->id);

            if ($lockedRequest->status !== PasswordRecoveryRequest::STATUS_PENDING) {
                return 'already_resolved';
            }

            if ($lockedRequest->expires_at->isPast()) {
                $lockedRequest->update(['status' => PasswordRecoveryRequest::STATUS_EXPIRED]);

                return 'expired';
            }

            $user = $lockedRequest->user;

            if ($user === null || $user->role === User::ROLE_ADMIN) {
                return 'invalid_user';
            }

            $status = Password::sendResetLink(
                ['email' => $user->email],
                static function (User $user, string $token) use (&$resetToken): string {
                    $resetToken = $token;

                    return Password::RESET_LINK_SENT;
                },
            );

            if ($status !== Password::RESET_LINK_SENT) {
                return $status;
            }

            $lockedRequest->update([
                'status' => PasswordRecoveryRequest::STATUS_APPROVED,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'expires_at' => now()->addMinutes((int) config('auth.passwords.users.expire')),
            ]);

            return 'approved';
        });

        if ($result === 'approved') {
            $user = User::query()->findOrFail($recoveryRequest->user_id);
            $resetUrl = route('password.reset', [
                'token' => $resetToken,
                'email' => $user->email,
            ]);

            if (config('mail.default') === 'log') {
                return redirect()->route('users.password-recovery.index')
                    ->with('status', 'Permintaan disetujui. Salin tautan reset berikut dan kirimkan kepada pengguna melalui kanal yang aman.')
                    ->with('manualResetUrl', $resetUrl);
            }

            try {
                $user->sendPasswordResetNotification($resetToken);
            } catch (Throwable $exception) {
                Password::broker()->deleteToken($user);
                $this->reopenRequest($recoveryRequest);
                report($exception);

                return back()->with(
                    'error',
                    'Email reset gagal dikirim. Permintaan dikembalikan ke status menunggu; pastikan layanan SMTP aktif dan coba lagi.',
                );
            }

            event(new PasswordResetLinkSent($user));

            return redirect()->route('users.password-recovery.index')->with(
                'status',
                'Permintaan disetujui; tautan reset telah dikirim ke email pengguna.',
            );
        }

        $message = match ($result) {
            'expired' => 'Permintaan sudah kedaluwarsa. Minta pengguna mengajukan kembali.',
            'already_resolved' => 'Permintaan ini sudah diproses.',
            Password::RESET_THROTTLED => 'Tautan reset baru saja dikirim. Tunggu sebentar sebelum mencoba lagi.',
            default => 'Permintaan tidak dapat diproses. Periksa kembali akun pengguna.',
        };

        return back()->with('error', $message);
    }

    public function reject(Request $request, PasswordRecoveryRequest $recoveryRequest): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $recoveryRequest): string {
            $lockedRequest = PasswordRecoveryRequest::query()
                ->lockForUpdate()
                ->findOrFail($recoveryRequest->id);

            if ($lockedRequest->status !== PasswordRecoveryRequest::STATUS_PENDING) {
                return 'already_resolved';
            }

            if ($lockedRequest->expires_at->isPast()) {
                $lockedRequest->update(['status' => PasswordRecoveryRequest::STATUS_EXPIRED]);

                return 'expired';
            }

            $lockedRequest->update([
                'status' => PasswordRecoveryRequest::STATUS_REJECTED,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            return 'rejected';
        });

        if ($result !== 'rejected') {
            return back()->with(
                'error',
                $result === 'expired'
                    ? 'Permintaan sudah kedaluwarsa.'
                    : 'Permintaan ini sudah diproses.',
            );
        }

        return redirect()->route('users.password-recovery.index')->with('status', 'Permintaan reset ditolak.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:password_recovery_requests,id'],
        ])['ids'];

        PasswordRecoveryRequest::query()->whereIn('id', $ids)->delete();

        return redirect()->route('users.password-recovery.index')->with('status', 'Permintaan reset yang dipilih berhasil dihapus.');
    }

    public function destroySingle(PasswordRecoveryRequest $recoveryRequest): RedirectResponse
    {
        $recoveryRequest->delete();

        return redirect()->route('users.password-recovery.index')->with('status', 'Permintaan reset berhasil dihapus.');
    }

    private function genericResponse(Request $request): RedirectResponse
    {
        $request->session()->put('password_recovery_submitted', true);

        return redirect()->route('password.request')->with(
            'status',
            'Jika email terdaftar, instruksi pemulihan akan diproses sesuai prosedur keamanan akun.',
        );
    }

    private function reopenRequest(PasswordRecoveryRequest $recoveryRequest): void
    {
        PasswordRecoveryRequest::query()
            ->whereKey($recoveryRequest->id)
            ->update([
                'status' => PasswordRecoveryRequest::STATUS_PENDING,
                'resolved_by' => null,
                'resolved_at' => null,
                'expires_at' => $recoveryRequest->requested_at->copy()->addHours(24),
            ]);
    }
}
