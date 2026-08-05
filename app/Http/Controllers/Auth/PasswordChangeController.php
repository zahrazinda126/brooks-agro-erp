<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Forced on first sign-in, and again whenever the password expires.
 | Enforces the policy stored in `settings`, including no reuse of the
 | last N passwords.
 */
class PasswordChangeController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user    = $request->user();
        $minimum = (int) Setting::get('security.password_min_length', 8);

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => [
                'required', 'confirmed',
                Password::min($minimum)->letters()->numbers(),
            ],
        ], [
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        if ($this->wasUsedRecently($user->id, $request->input('password'))) {
            $keep = (int) Setting::get('security.password_history', 3);

            throw ValidationException::withMessages([
                'password' => "Choose a password you have not used in your last {$keep}.",
            ]);
        }

        DB::table('password_histories')->insert([
            'user_id'       => $user->id,
            'password_hash' => $user->password,
            'created_at'    => now(),
        ]);

        $expiryDays = (int) Setting::get('security.password_expiry_days', 90);

        $user->forceFill([
            'password'             => Hash::make($request->input('password')),
            'must_change_password' => false,
            'password_changed_at'  => now(),
            'password_expires_at'  => $expiryDays > 0 ? now()->addDays($expiryDays) : null,
        ])->save();

        AuditLog::record('password_changed', $user, 'Password changed.');

         return redirect()
            ->route($user->homeRoute())
            ->with('status', 'Your password has been changed.');
    }

    private function wasUsedRecently(int $userId, string $candidate): bool
    {
        $keep = (int) Setting::get('security.password_history', 3);

        if ($keep < 1) {
            return false;
        }

        $recent = DB::table('password_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($keep)
            ->pluck('password_hash');

        foreach ($recent as $hash) {
            if (Hash::check($candidate, $hash)) {
                return true;
            }
        }

        return false;
    }
}
