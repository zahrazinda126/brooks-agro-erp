<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         | Every view gets $user — the signed-in person, or null when
         | signed out. Blade renders child views before the layout, so a
         | variable set in the layout is not visible inside the page it
         | wraps. Sharing it here means no controller ever has to
         | remember to pass it.
         */
        View::composer('*', function ($view) {
            $view->with('user', Auth::user());
        });
    }
}