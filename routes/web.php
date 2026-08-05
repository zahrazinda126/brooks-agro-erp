<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HR\EmployeeAccountController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\Poultry\BatchController;
use App\Http\Controllers\Poultry\BreedController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Poultry\DailyEntryController;
use App\Http\Controllers\Poultry\MortalityAssessmentController;
use App\Http\Controllers\Poultry\FeedTypeController;
use App\Http\Controllers\Poultry\BirdConsumptionController;
use App\Http\Controllers\Poultry\HealthController;
use App\Http\Controllers\Poultry\CollectionController;





/*
|--------------------------------------------------------------------------
| Brooks Agro ERP routes
|--------------------------------------------------------------------------
|
| There is no registration route. Accounts are created by HR inside the
| application, never by the person signing in.
|
*/

/*
 | Where "/" goes depends on who is asking. Workers go to their work,
 | everyone else to a dashboard.
 */
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->homeRoute());
});

/* ---------- Signed out ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

/* ---------- Signed in ---------- */
Route::middleware('auth')->group(function () {

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Reachable even while a password change is outstanding.
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    // Everything past here requires a settled password.
    Route::middleware('password.settled')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        /* ---------- Human Resources ---------- */
        Route::prefix('hr')->group(function () {

            Route::get('/employees', [EmployeeController::class, 'index'])
                ->middleware('module:hr')->name('employees.index');

            Route::get('/employees/create', [EmployeeController::class, 'create'])
                ->middleware('module:hr,create')->name('employees.create');

            Route::post('/employees', [EmployeeController::class, 'store'])
                ->middleware('module:hr,create')->name('employees.store');

            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
                ->middleware('module:hr')->name('employees.show');

            Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
                ->middleware('module:hr,edit')->name('employees.edit');

            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
                ->middleware('module:hr,edit')->name('employees.update');

            /* Issuing and managing logins — deliberately its own controller. */
            Route::get('/employees/{employee}/account', [EmployeeAccountController::class, 'create'])
                ->middleware('module:hr,edit')->name('employees.account.create');

            Route::post('/employees/{employee}/account', [EmployeeAccountController::class, 'store'])
                ->middleware('module:hr,edit')->name('employees.account.store');

            Route::get('/employees/{employee}/account/edit', [EmployeeAccountController::class, 'edit'])
                ->middleware('module:hr,edit')->name('employees.account.edit');

            Route::put('/employees/{employee}/account', [EmployeeAccountController::class, 'update'])
                ->middleware('module:hr,edit')->name('employees.account.update');

            Route::post('/employees/{employee}/account/reset', [EmployeeAccountController::class, 'reset'])
                ->middleware('module:hr,edit')->name('employees.account.reset');

            Route::post('/employees/{employee}/account/toggle', [EmployeeAccountController::class, 'toggle'])
                ->middleware('module:hr,edit')->name('employees.account.toggle');
        });

        /* ---------- Poultry ---------- */
        Route::prefix('poultry')->group(function () {

            /* Batches */
            Route::get('/batches', [BatchController::class, 'index'])
                ->middleware('module:batches')->name('batches.index');

            Route::get('/batches/create', [BatchController::class, 'create'])
                ->middleware('module:batches,create')->name('batches.create');

            Route::post('/batches', [BatchController::class, 'store'])
                ->middleware('module:batches,create')->name('batches.store');

            Route::get('/batches/{batch}', [BatchController::class, 'show'])
                ->middleware('module:batches')->name('batches.show');

            Route::get('/batches/{batch}/edit', [BatchController::class, 'edit'])
                ->middleware('module:batches,edit')->name('batches.edit');

            Route::put('/batches/{batch}', [BatchController::class, 'update'])
                ->middleware('module:batches,edit')->name('batches.update');

            Route::post('/batches/{batch}/close', [BatchController::class, 'close'])
                ->middleware('module:batches,edit')->name('batches.close');

            /* Breeds */
            Route::get('/breeds', [BreedController::class, 'index'])
                ->middleware('module:breeds')->name('breeds.index');

            Route::get('/breeds/create', [BreedController::class, 'create'])
                ->middleware('module:breeds,create')->name('breeds.create');

            Route::post('/breeds', [BreedController::class, 'store'])
                ->middleware('module:breeds,create')->name('breeds.store');

            Route::get('/breeds/{breed}/edit', [BreedController::class, 'edit'])
                ->middleware('module:breeds,edit')->name('breeds.edit');

            Route::put('/breeds/{breed}', [BreedController::class, 'update'])
                ->middleware('module:breeds,edit')->name('breeds.update');

            Route::post('/breeds/{breed}/toggle', [BreedController::class, 'toggle'])
                ->middleware('module:breeds,edit')->name('breeds.toggle');
               /* --- Worker daily entry --- */
            Route::get('/today', [DailyEntryController::class, 'index'])
                ->name('daily.index');

            Route::get('/today/{batch}/eggs', [DailyEntryController::class, 'eggsForm'])
                ->middleware('module:egg-entry')->name('daily.eggs');
            Route::post('/today/{batch}/eggs', [DailyEntryController::class, 'eggsStore'])
                ->middleware('module:egg-entry,create')->name('daily.eggs.store');

            Route::get('/today/{batch}/deaths', [DailyEntryController::class, 'deathsForm'])
                ->middleware('module:mortality-entry')->name('daily.deaths');
            Route::post('/today/{batch}/deaths', [DailyEntryController::class, 'deathsStore'])
                ->middleware('module:mortality-entry,create')->name('daily.deaths.store');

            Route::get('/today/{batch}/feed', [DailyEntryController::class, 'feedForm'])
                ->middleware('module:feed-consumption')->name('daily.feed');
            Route::post('/today/{batch}/feed', [DailyEntryController::class, 'feedStore'])
                ->middleware('module:feed-consumption,create')->name('daily.feed.store');

            /* --- Manager assessment --- */
            Route::get('/assess', [MortalityAssessmentController::class, 'index'])
                ->middleware('module:mortality-assess')->name('mortality.assess.index');

            Route::get('/assess/{record}', [MortalityAssessmentController::class, 'edit'])
                ->middleware('module:mortality-assess')->name('mortality.assess.edit');

            Route::put('/assess/{record}', [MortalityAssessmentController::class, 'update'])
                ->middleware('module:mortality-assess,edit')->name('mortality.assess.update');
            /* --- Feed types --- */
            Route::get('/feed-types', [FeedTypeController::class, 'index'])
                ->middleware('module:feed-types')->name('feed-types.index');

            Route::get('/feed-types/create', [FeedTypeController::class, 'create'])
                ->middleware('module:feed-types,create')->name('feed-types.create');

            Route::post('/feed-types', [FeedTypeController::class, 'store'])
                ->middleware('module:feed-types,create')->name('feed-types.store');

            Route::get('/feed-types/{feedType}/edit', [FeedTypeController::class, 'edit'])
                ->middleware('module:feed-types,edit')->name('feed-types.edit');

            Route::put('/feed-types/{feedType}', [FeedTypeController::class, 'update'])
                ->middleware('module:feed-types,edit')->name('feed-types.update');

            Route::post('/feed-types/{feedType}/toggle', [FeedTypeController::class, 'toggle'])
                ->middleware('module:feed-types,edit')->name('feed-types.toggle'); 
                 /* --- Birds used on the farm --- */
            Route::get('/consumption', [BirdConsumptionController::class, 'index'])
                ->middleware('module:bird-consumption')->name('consumption.index');

            Route::get('/consumption/create', [BirdConsumptionController::class, 'create'])
                ->middleware('module:bird-consumption,create')->name('consumption.create');

            Route::post('/consumption', [BirdConsumptionController::class, 'store'])
                ->middleware('module:bird-consumption,create')->name('consumption.store');

            Route::post('/consumption/{record}/confirm', [BirdConsumptionController::class, 'confirm'])
                ->middleware('module:bird-consumption,approve')->name('consumption.confirm');
                  /* --- Flock health --- */
            Route::get('/health', [HealthController::class, 'index'])
                ->middleware('module:health')->name('health.index');

            Route::get('/health/record', [HealthController::class, 'create'])
                ->middleware('module:health,create')->name('health.create');

            Route::post('/health', [HealthController::class, 'store'])
                ->middleware('module:health,create')->name('health.store');

            Route::get('/health/{batch}', [HealthController::class, 'show'])
                ->middleware('module:health')->name('health.show');
            Route::get('/collections', [CollectionController::class, 'index'])
                ->middleware('module:egg-inventory')->name('collections.index');


   
    
        });

        // Catch-all for modules that are seeded but not yet built.
        Route::get('/m/{key}', [DashboardController::class, 'placeholder'])
            ->name('module.placeholder');
    });
});