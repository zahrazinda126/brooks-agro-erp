<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use App\Models\Communication;
use App\Models\ExecutiveDirective;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Page links in the farm's own style (Laravel's default needs Tailwind, which the system does not load).
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.farm');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.farm');
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // @money($amount) → "UGX 1,250"; @money($amount, 2) → "UGX 1,250.00".
        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::format({$expression})); ?>");

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

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            if (! $user) {
                $view->with('directiveReminders', collect());
                return;
            }

            $audiences = Communication::audiencesFor($user);
            $directiveReminders = ExecutiveDirective::query()
                ->whereNotIn('status', ['done', 'completed', 'cancelled'])
                ->where(function ($assigned) use ($user, $audiences) {
                    $assigned->where('assigned_to', $user->id)
                        ->orWhere('tracked_by', $user->id);
                    if ($audiences->isNotEmpty()) {
                        $assigned->orWhereIn('recipient_scope', $audiences);
                    }
                })
                ->orderByRaw('case when due_on is not null and due_on < ? then 0 else 1 end', [today()->toDateString()])
                ->orderBy('due_on')
                ->orderBy('scheduled_for')
                ->get();

            $view->with('directiveReminders', $directiveReminders);
        });
    }
}
