<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 | Keeps a user on the change-password screen until they have set their
 | own password. Applies on first sign-in and after expiry.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->must_change_password || $user->passwordHasExpired())) {
            if (! $request->routeIs('password.change', 'password.update', 'logout')) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
