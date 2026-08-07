<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HR\EmployeeAccountController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\EmployeeRecordController;
use App\Http\Controllers\HR\HrReportController;
use App\Http\Controllers\HR\PerformanceController;
use App\Http\Controllers\Poultry\BatchController;
use App\Http\Controllers\Poultry\BreedController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Poultry\DailyEntryController;
use App\Http\Controllers\Poultry\MortalityAssessmentController;
use App\Http\Controllers\Poultry\FeedTypeController;
use App\Http\Controllers\Poultry\BirdConsumptionController;
use App\Http\Controllers\Poultry\HealthController;
use App\Http\Controllers\Poultry\CollectionController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\ReceivableController;
use App\Http\Controllers\Finance\AccountingController;
use App\Http\Controllers\Poultry\PerformanceController as PoultryPerformanceController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Finance\FarmOperationsController;
use App\Http\Controllers\Finance\FinanceReportController;





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
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/approvals/{approval}/decision', [ApprovalController::class, 'decide'])->name('approvals.decide');
        Route::get('/finance/transactions', [TransactionController::class, 'index'])->name('finance.transactions.index');
        Route::get('/finance/transactions/create', [TransactionController::class, 'create'])->name('finance.transactions.create');
        Route::post('/finance/transactions', [TransactionController::class, 'store'])->name('finance.transactions.store');
        Route::get('/finance/general-ledger', [TransactionController::class, 'generalLedger'])->name('finance.general-ledger.index');
        Route::get('/finance/receivables', [ReceivableController::class, 'index'])->name('finance.receivables.index');
        Route::post('/finance/receivables', [ReceivableController::class, 'store'])->name('finance.receivables.store');
        Route::post('/finance/receivables/payment', [ReceivableController::class, 'recordPayment'])->name('finance.receivables.payment');
        Route::post('/finance/receivables/prepayment', [ReceivableController::class, 'recordPrepayment'])->name('finance.receivables.prepayment');
        Route::post('/finance/receivables/apply-prepayment', [ReceivableController::class, 'applyPrepayment'])->name('finance.receivables.apply-prepayment');
        Route::get('/finance/payables', [AccountingController::class, 'payables'])->name('finance.payables.index');
        Route::post('/finance/payables', [AccountingController::class, 'storePayable'])->name('finance.payables.store');
        Route::get('/finance/budgets', [AccountingController::class, 'budgets'])->name('finance.budgets.index');
        Route::post('/finance/budgets', [AccountingController::class, 'storeBudget'])->name('finance.budgets.store');
        Route::put('/finance/budgets/{budget}', [AccountingController::class, 'updateBudget'])->name('finance.budgets.update');
        Route::put('/finance/budgets', [AccountingController::class, 'updateWeeklyBudget'])->name('finance.budgets.update-weekly');
        Route::post('/finance/budgets/submit', [AccountingController::class, 'submitBudget'])->name('finance.budgets.submit');
        Route::post('/finance/budgets/weekly-template', [AccountingController::class, 'seedWeeklyBudget'])->name('finance.budgets.weekly-template');
        Route::delete('/finance/budgets/{budget}', [AccountingController::class, 'destroyBudget'])->name('finance.budgets.destroy');
        Route::get('/finance/assets', [AccountingController::class, 'assets'])->name('finance.assets.index');
        Route::post('/finance/assets', [AccountingController::class, 'storeAsset'])->name('finance.assets.store');
        Route::get('/finance/cash-book', [TransactionController::class, 'cashBook'])->name('finance.cash-book.index');
        Route::get('/finance/bank-book', [TransactionController::class, 'bankBook'])->name('finance.bank-book.index');
        Route::get('/finance/farm-operations/purchases', [FarmOperationsController::class, 'purchases'])->name('finance.farm.purchases');
        Route::post('/finance/farm-operations/purchases', [FarmOperationsController::class, 'storePurchase'])->name('finance.farm.purchases.store');
        Route::post('/finance/farm-operations/purchases/{purchase}/submit', [FarmOperationsController::class, 'submitPurchase'])->name('finance.farm.purchases.submit');
        Route::get('/finance/farm-operations/production', [FarmOperationsController::class, 'production'])->name('finance.farm.production');
        Route::get('/finance/farm-operations/veterinary', [FarmOperationsController::class, 'veterinary'])->name('finance.farm.veterinary');
        Route::get('/finance/reports', [FinanceReportController::class, 'overview'])->name('finance.reports.index');
        Route::get('/finance/reports/kpis', [FinanceReportController::class, 'kpis'])->name('finance.reports.kpis');
        Route::get('/finance/reports/three-statement', [FinanceReportController::class, 'threeStatement'])->name('finance.reports.three-statement');
        Route::get('/finance/reports/export/{report}', [FinanceReportController::class, 'export'])->name('finance.reports.export');
        Route::get('/finance/reports/{report}', [FinanceReportController::class, 'statement'])->name('finance.reports.statement');

        Route::get('/hr/reports', [HrReportController::class, 'index'])->name('hr.reports.index');
        Route::get('/hr/reports/export', [HrReportController::class, 'export'])->name('hr.reports.export');
        Route::get('/hr/kpi-dashboard', [HrReportController::class, 'kpis'])->name('hr.kpis.index');
        Route::get('/hr/performance', [PerformanceController::class, 'index'])->name('hr.performance.index');

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

            Route::get('/records/{module}', [EmployeeRecordController::class, 'index'])
                ->name('hr.records.index');
            Route::get('/records/{module}/create', [EmployeeRecordController::class, 'create'])
                ->name('hr.records.create');
            Route::post('/records/{module}', [EmployeeRecordController::class, 'store'])
                ->name('hr.records.store');
            Route::post('/records/{module}/{record}/approve', [EmployeeRecordController::class, 'approve'])
                ->name('hr.records.approve');
            Route::get('/records/{module}/{record}/attachments/{attachment}', [EmployeeRecordController::class, 'download'])
                ->name('hr.records.attachments.download');
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
            Route::get('/performance', [PoultryPerformanceController::class, 'index'])
                ->middleware('module:staff-performance')->name('poultry.performance.index');


   
    
        });

        // Catch-all for modules that are seeded but not yet built.
        Route::get('/m/{key}', [DashboardController::class, 'placeholder'])
            ->name('module.placeholder');
    });
});
