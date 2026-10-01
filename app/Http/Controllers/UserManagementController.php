<?php

namespace App\Http\Controllers;

use App\Models\EmploymentEvent;
use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim($validated['search'] ?? '');

        $users = User::query()
            ->with('employmentEvents.actor')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('no_telepon', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        $pendingRecoveryRequestCount = PasswordRecoveryRequest::query()
            ->where('status', PasswordRecoveryRequest::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->count();

        $view = $request->boolean('partial')
            ? 'portal.users._list'
            : 'portal.users.index';

        return view($view, compact('users', 'pendingRecoveryRequestCount', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'no_telepon' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OWNER, User::ROLE_OPERATOR])],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = DB::transaction(function () use ($validated, $request): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'no_telepon' => $validated['no_telepon'] ?? null,
                'role' => $validated['role'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);

            if ($user->role === User::ROLE_OPERATOR) {
                $user->employmentEvents()->create([
                    'actor_id' => $request->user()->id,
                    'action' => 'hired',
                    'note' => $validated['note'] ?? null,
                ]);
            }

            return $user;
        });

        return redirect()->route('users.index')->with(
            'status',
            $user->role === User::ROLE_OPERATOR
                ? 'Akun karyawan berhasil direkrut dan catatan awal disimpan.'
                : 'Akun berhasil dibuat.',
        );
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->role === User::ROLE_OPERATOR) {
            return back()->withErrors(['user' => 'Gunakan formulir kepegawaian agar perubahan status tercatat.']);
        }

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        if ($user->role === User::ROLE_ADMIN && $user->is_active) {
            $activeAdmins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->count();

            if ($activeAdmins <= 1) {
                return back()->withErrors(['user' => 'Setidaknya satu administrator harus tetap aktif.']);
            }
        }

        if (! $user->is_active && Hash::check('password', $user->password)) {
            return back()->withErrors(['user' => 'Ganti kata sandi bawaan sebelum mengaktifkan akun ini.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('users.index')->with('status', 'Status akun berhasil diperbarui.');
    }

    public function dismiss(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_OPERATOR, 404);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $user, $validated): string {
            $employee = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $employee->is_active) {
                return 'already_inactive';
            }

            $employee->update(['is_active' => false]);
            EmploymentEvent::create([
                'user_id' => $employee->id,
                'actor_id' => $request->user()->id,
                'action' => 'dismissed',
                'note' => $validated['note'],
            ]);

            return 'dismissed';
        });

        if ($result === 'already_inactive') {
            return back()->withErrors(['user' => 'Karyawan ini sudah berstatus nonaktif.']);
        }

        return redirect()->route('users.index')->with('status', 'Karyawan diberhentikan; alasan telah dicatat.');
    }

    public function rehire(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_OPERATOR, 404);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $user, $validated): string {
            $employee = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($employee->is_active) {
                return 'already_active';
            }

            if (Hash::check('password', $employee->password)) {
                return 'default_password';
            }

            $employee->update(['is_active' => true]);
            EmploymentEvent::create([
                'user_id' => $employee->id,
                'actor_id' => $request->user()->id,
                'action' => 'rehired',
                'note' => $validated['note'],
            ]);

            return 'rehired';
        });

        if ($result === 'already_active') {
            return back()->withErrors(['user' => 'Karyawan ini sudah aktif.']);
        }

        if ($result === 'default_password') {
            return back()->withErrors(['user' => 'Ganti kata sandi bawaan sebelum mengaktifkan kembali akun ini.']);
        }

        return redirect()->route('users.index')->with('status', 'Karyawan diaktifkan kembali; catatan telah disimpan.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user->update(['password' => $validated['password']]);

        return redirect()->route('users.index')->with('status', 'Kata sandi berhasil diperbarui. Status akun tidak berubah.');
    }
}
