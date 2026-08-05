<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
         | Short names for our two guards, so routes can say
         | ->middleware('module:poultry,create') instead of the full
         | class name.
         */
        $middleware->alias([
            'module'           => \App\Http\Middleware\EnsureModuleAccess::class,
            'password.settled' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);

        // Where an already-signed-in person goes if they hit /login.
        $middleware->redirectUsersTo('/dashboard');

        // Where a signed-out person goes if they hit a protected page.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
