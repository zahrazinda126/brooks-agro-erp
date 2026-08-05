<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 | Route guard. Usage in routes/web.php:
 |
 |    Route::get('/poultry', ...)->middleware('module:poultry');
 |    Route::post('/poultry', ...)->middleware('module:poultry,create');
 |
 | Checking here rather than inside each controller means a screen can
 | never be reached by typing its address.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey, string $action = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = $action === 'view'
            ? $user->canAccess($moduleKey)
            : $user->canDo($moduleKey, $action);

        if (! $allowed) {
            abort(403, 'You do not have access to this screen.');
        }

        return $next($request);
    }
}
