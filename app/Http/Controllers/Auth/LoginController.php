<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Sign in by USERNAME. There is no public registration anywhere in this
 | system — HR creates every account.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string'],
        ]);

        // Forgiving match: "baf 003", "BAF003" and "BAF 003" all find the same login.
        $typed = strtolower(preg_replace('/\s+/', '', $credentials['username']));
        $user = User::where('username', trim($credentials['username']))->first()
            ?? User::whereRaw("lower(replace(username, ' ', '')) = ?", [$typed])->first();

        // Locked out after repeated failures.
        if ($user && $user->isLocked()) {
            $minutes = max(1, now()->diffInMinutes($user->locked_until));

            throw ValidationException::withMessages([
                'username' => "This account is locked. Try again in {$minutes} minute(s), or ask an administrator to unlock it.",
            ]);
        }

        // Wrong username or password.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->registerFailure($user);

            throw ValidationException::withMessages([
                'username' => 'That username and password do not match.',
            ]);
        }

        // Correct password, but the account has been switched off.
        if (! $user->is_active) {
            AuditLog::create([
                'user_id'    => $user->id,
                'username'   => $user->username,
                'event'      => 'login_blocked',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'description'    => 'Sign-in attempt on a disabled account.',
                'ip_address'     => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 255),
                'created_at'     => now(),
            ]);

            throw ValidationException::withMessages([
                'username' => 'This account has been disabled. Contact HR.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => $request->ip(),
        ])->save();

        AuditLog::record('login', $user, 'Signed in.');

        if ($user->must_change_password || $user->passwordHasExpired()) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route($user->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('logout', $request->user(), 'Signed out.');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    /**
     * Count the failure and lock the account once the limit is reached.
     * Nothing is recorded for an unknown username — that would let
     * someone probe for valid names.
     */
    private function registerFailure(?User $user): void
    {
        if (! $user) {
            return;
        }

        $max      = (int) Setting::get('security.max_login_attempts', 5);
        $minutes  = (int) Setting::get('security.lockout_minutes', 15);
        $attempts = $user->failed_login_attempts + 1;

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until'          => $attempts >= $max ? now()->addMinutes($minutes) : null,
        ])->save();

        AuditLog::create([
            'user_id'        => $user->id,
            'username'       => $user->username,
            'event'          => $attempts >= $max ? 'account_locked' : 'login_failed',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'description'    => "Failed sign-in attempt {$attempts} of {$max}.",
            'ip_address'     => request()->ip(),
            'user_agent'     => substr((string) request()->userAgent(), 0, 255),
            'created_at'     => now(),
        ]);
    }
}
