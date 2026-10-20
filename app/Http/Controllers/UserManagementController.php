<?php

namespace App\Http\Controllers;

use App\Models\EmploymentEvent;
use App\Models\PasswordRecoveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Anda tidak dapat mengubah peran akun sendiri.']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                User::ROLE_CUSTOMER,
                User::ROLE_OPERATOR,
                User::ROLE_OWNER,
                User::ROLE_ADMIN,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($request, $user, $validated): string {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($account->role === $validated['role']) {
                return 'unchanged';
            }

            if ($account->role === User::ROLE_ADMIN && $account->is_active && $validated['role'] !== User::ROLE_ADMIN) {
                $activeAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                if ($activeAdmins->count() <= 1) {
                    return 'last_admin';
                }
            }

            $previousRole = $account->role;
            $account->update(['role' => $validated['role']]);

            if ($previousRole === User::ROLE_OPERATOR || $validated['role'] === User::ROLE_OPERATOR) {
                $account->employmentEvents()->create([
                    'actor_id' => $request->user()->id,
                    'action' => 'role_changed',
                    'note' => $validated['note'] ?? null,
                ]);
            }

            return 'updated';
        });

        if ($result === 'last_admin') {
            return back()->withErrors(['user' => 'Setidaknya satu administrator harus tetap aktif.']);
        }

        return redirect()->route('users.index')->with(
            'status',
            $result === 'unchanged' ? 'Peran akun tidak berubah.' : 'Peran akun berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $user): string {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($account->is($request->user())) {
                return 'current_user';
            }

            if (
                $account->employmentEvents()->exists()
                || $account->attendanceRecords()->exists()
                || $account->disciplinaryActions()->exists()
            ) {
                return 'employment_history';
            }

            if ($account->role === User::ROLE_ADMIN && $account->is_active) {
                $activeAdmins = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                if ($activeAdmins->count() <= 1) {
                    return 'last_admin';
                }
            }

            $account->delete();

            return 'deleted';
        });

        if ($result === 'current_user') {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        if ($result === 'employment_history') {
            return back()->withErrors(['user' => 'Akun dengan riwayat kepegawaian tidak dapat dihapus.']);
        }

        if ($result === 'last_admin') {
            return back()->withErrors(['user' => 'Setidaknya satu administrator harus tetap aktif.']);
        }

        return redirect()->route('users.index')->with('status', 'Akun berhasil dihapus. Riwayat transaksi pelanggan tetap tersimpan.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        if ($user->role === User::ROLE_ADMIN || ! $user->is_active) {
            return back()->withErrors(['user' => 'Akses hanya tersedia untuk akun aktif non-administrator.']);
        }

        if ($request->session()->has('admin_impersonator_id')) {
            return back()->withErrors(['user' => 'Akhiri akses akun saat ini sebelum mengakses akun lain.']);
        }

        $request->session()->put('admin_impersonator_id', $request->user()->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($user->role === User::ROLE_CUSTOMER
            ? route('account.orders.index')
            : route('dashboard'));
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('admin_impersonator_id');
        $admin = $adminId === null
            ? null
            : User::query()
                ->whereKey($adminId)
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->first();

        if ($admin === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('users.index');
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
