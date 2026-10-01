<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InactiveAccountController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->is_active) {
            $destination = $user->role === User::ROLE_CUSTOMER
                ? 'account.orders.index'
                : 'dashboard';

            $response = redirect()->route($destination);
            $latestEmploymentEvent = $user->role === User::ROLE_OPERATOR
                ? $user->employmentEvents()->latest('id')->first()
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

        $dismissalNote = $user->role === User::ROLE_OPERATOR
            ? $user->employmentEvents()
                ->where('action', 'dismissed')
                ->latest()
                ->value('note')
            : null;

        return view('auth.inactive-account', compact('dismissalNote'));
    }

    public function employmentStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === User::ROLE_OPERATOR, 404);

        $latestDismissal = $user->is_active
            ? null
            : $user->employmentEvents()
                ->where('action', 'dismissed')
                ->latest('id')
                ->first();

        return response()->json([
            'is_active' => $user->is_active,
            'event' => $latestDismissal === null ? null : [
                'action' => $latestDismissal->action,
                'note' => $latestDismissal->note,
            ],
        ]);
    }
}
