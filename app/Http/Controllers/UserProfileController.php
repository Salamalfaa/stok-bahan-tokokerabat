<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class UserProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $emailRules = [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user),
        ];
        $customerId = $user->customer()->value('id');

        if ($customerId !== null) {
            $emailRules[] = Rule::unique('customers', 'email')->ignore($customerId);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'no_telepon' => ['nullable', 'string', 'max:30'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $oldPhotoPath = $user->profile_photo_path;
        $newPhotoPath = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('profile-photos/'.$user->id, 'public')
            : null;

        if ($request->hasFile('profile_photo') && ! is_string($newPhotoPath)) {
            return back()->withErrors(['profile_photo' => 'Foto tidak dapat disimpan. Silakan coba kembali.']);
        }

        try {
            DB::transaction(function () use ($user, $validated, $newPhotoPath): void {
                $user->fill([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'no_telepon' => $validated['no_telepon'] ?? null,
                ]);

                if ($newPhotoPath !== null) {
                    $user->profile_photo_path = $newPhotoPath;
                }

                $user->save();

                $customer = $user->customer()->first();

                if ($customer instanceof Customer) {
                    $customer->fill([
                        'nama' => $user->name,
                        'email' => $user->email,
                        'no_telepon' => $user->no_telepon,
                    ])->save();
                }
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath !== null && $oldPhotoPath !== null) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('profile.edit')->with('status', 'Profil berhasil diperbarui.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $photoPath = $user->profile_photo_path;

        $user->profile_photo_path = null;
        $user->save();

        if ($photoPath !== null) {
            Storage::disk('public')->delete($photoPath);
        }

        return redirect()->route('profile.edit')->with('status', 'Foto profil dihapus.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('status', 'Kata sandi berhasil diperbarui.');
    }
}
