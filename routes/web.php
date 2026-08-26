<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\OwnerDirectoryController;
use App\Http\Controllers\HR\EmployeeAccountController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\EmployeeRecordController;
use App\Http\Controllers\HR\HrReportController;
use App\Http\Controllers\HR\PerformanceController;
use App\Http\Controllers\HR\FleetController;
use App\Http\Controllers\HR\WelfareBudgetController;
use App\Http\Controllers\Poultry\BatchController;
use App\Http\Controllers\Poultry\BreedController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Poultry\DailyEntryController;
use App\Http\Controllers\Poultry\MortalityAssessmentController;
use App\Http\Controllers\Poultry\FeedTypeController;
use App\Http\Controllers\Poultry\BirdConsumptionController;
use App\Http\Controllers\Poultry\HealthController;
use App\Http\Controllers\Poultry\CollectionController;
use App\Http\Controllers\Poultry\PoultryReportController;
use App\Http\Controllers\Finance\TransactionController;
use App\Http\Controllers\Finance\ReceivableController;
use App\Http\Controllers\Finance\AccountingController;
use App\Http\Controllers\Poultry\PerformanceController as PoultryPerformanceController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Finance\FarmOperationsController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\FinanceAdministrationController;
use App\Http\Controllers\ExecutiveController;
use App\Http\Controllers\Operations\FarmUnitController;
use App\Http\Controllers\Operations\FeedStockController;
use App\Http\Controllers\ProductionBudgetController;
use App\Http\Controllers\DepartmentBudgetController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\ExecutiveFollowUpController;
use App\Http\Controllers\VaccinationScheduleController;
use App\Http\Controllers\Poultry\HouseController;
use App\Http\Controllers\DailyManagementReportController;





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
        Route::get('/daily-management-report', [DailyManagementReportController::class, 'index'])->name('daily-reports.index');
        Route::post('/daily-management-report', [DailyManagementReportController::class, 'save'])->name('daily-reports.save');
        Route::post('/daily-management-report/{section}/review', [DailyManagementReportController::class, 'review'])->name('daily-reports.review');
        Route::post('/daily-management-report/comments', [DailyManagementReportController::class, 'comment'])->name('daily-reports.comments');
        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');
        Route::get('/owner/customers', [OwnerDirectoryController::class, 'customers'])->name('owner.customers');
        Route::get('/customer-accounts/{customer}', [OwnerDirectoryController::class, 'showCustomer'])->name('customer-accounts.show');
        Route::get('/vaccination-schedule', [VaccinationScheduleController::class, 'index'])->name('vaccination-schedule.index');
        Route::post('/vaccination-schedule', [VaccinationScheduleController::class, 'store'])->name('vaccination-schedule.store');
        Route::post('/vaccination-records', [VaccinationScheduleController::class, 'storeRecord'])->name('vaccination-records.store');
        Route::put('/vaccination-schedule/{plan}', [VaccinationScheduleController::class, 'update'])->name('vaccination-schedule.update');
        Route::get('/poultry/houses', [HouseController::class, 'index'])->name('poultry-houses.index');
        Route::post('/poultry/houses', [HouseController::class, 'store'])->name('poultry-houses.store');
        Route::put('/poultry/houses/{house}', [HouseController::class, 'update'])->name('poultry-houses.update');
          Route::post('/poultry/houses/assign', [HouseController::class, 'assign'])->name('poultry-houses.assign');
          Route::post('/poultry/houses/transfer', [HouseController::class, 'transfer'])->name('poultry-houses.transfer');
        Route::post('/poultry/houses/assignments/{assignment}/end', [HouseController::class, 'end'])->name('poultry-houses.assignments.end');
        Route::get('/inventory', [InventoryController::class, 'index'])->middleware('module:inventory')->name('inventory.index');
        Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->middleware('module:inventory,create')->name('inventory.items.store');
        Route::post('/inventory/movements', [InventoryController::class, 'storeMovement'])->middleware('module:inventory,create')->name('inventory.movements.store');
        Route::get('/visitors', [VisitorController::class, 'index'])->name('visitors.index');
        Route::get('/supervision', [SupervisorController::class, 'index'])->name('supervision.index');
        Route::post('/supervision/assignments', [SupervisorController::class, 'assign'])->name('supervision.assignments.store');
        Route::post('/supervision/assignments/{assignment}/end', [SupervisorController::class, 'end'])->name('supervision.assignments.end');
        Route::get('/supervision/records/{type}/{id}', [SupervisorController::class, 'showRecord'])->name('supervision.records.show');
        Route::post('/supervision/records/{type}/{id}/review', [SupervisorController::class, 'reviewRecord'])->name('supervision.records.review');
        Route::post('/visitors', [VisitorController::class, 'store'])->name('visitors.store');
        Route::post('/visitors/{visitor}/checkout', [VisitorController::class, 'checkout'])->name('visitors.checkout');
        Route::get('/hr/fleet', [FleetController::class, 'index'])->name('hr.fleet.index');
        Route::post('/hr/fleet', [FleetController::class, 'store'])->name('hr.fleet.store');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/my-salary-balance', [PayrollController::class, 'myBalance'])->name('payroll.my-balance');
        Route::get('/salary-accounts/{employee}', [PayrollController::class, 'salaryAccount'])->name('payroll.salary-accounts.show');
        Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::put('/payroll/{payroll}/lines/{line}', [PayrollController::class, 'updateLine'])->name('payroll.lines.update');
        Route::post('/payroll/{payroll}/submit', [PayrollController::class, 'submit'])->name('payroll.submit');
        Route::post('/payroll/{payroll}/pay', [PayrollController::class, 'fund'])->name('payroll.pay');
        Route::get('/payroll/{payroll}/lines/{line}/withdraw', [PayrollController::class, 'withdrawalForm'])->name('payroll.lines.withdraw-form');
        Route::post('/payroll/{payroll}/lines/{line}/withdraw', [PayrollController::class, 'withdraw'])->name('payroll.lines.withdraw');
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::post('/sales/products', [SalesController::class, 'storeProduct'])->name('sales.products.store');
        Route::put('/sales/products/{product}/price', [SalesController::class, 'updatePrice'])->name('sales.products.price');
        Route::get('/sales/price-approvals', [SalesController::class, 'priceApprovals'])->name('sales.price-approvals');
        Route::post('/sales/price-approvals/{priceRequest}', [SalesController::class, 'decidePrice'])->name('sales.price-approvals.decide');
        Route::post('/sales/customers', [SalesController::class, 'storeCustomer'])->name('sales.customers.store');
        Route::post('/sales', [SalesController::class, 'storeSale'])->name('sales.store');
        Route::get('/sales/{sale}', [SalesController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/receipts/{payment}/print', [SalesController::class, 'receipt'])->name('sales.receipts.print');
        Route::post('/sales/{sale}/payments', [SalesController::class, 'payment'])->name('sales.payments.store');
        Route::post('/sales/{sale}/returns', [SalesController::class, 'returnLine'])->name('sales.returns.store');
        Route::get('/operations/reports', [FarmUnitController::class, 'report'])->name('operations.reports');
        Route::get('/operations/feed-stock', [FeedStockController::class, 'index'])->name('operations.feed-stock.index');
        Route::post('/operations/feed-stock', [FeedStockController::class, 'store'])->name('operations.feed-stock.store');
        Route::get('/hr/staff-welfare-budget', [WelfareBudgetController::class, 'index'])->name('hr.welfare-budget.index');
        Route::post('/hr/staff-welfare-budget/template', [WelfareBudgetController::class, 'loadTemplate'])->name('hr.welfare-budget.template');
        Route::post('/hr/staff-welfare-budget', [WelfareBudgetController::class, 'store'])->name('hr.welfare-budget.store');
        Route::put('/hr/staff-welfare-budget/{budget}', [WelfareBudgetController::class, 'update'])->name('hr.welfare-budget.update');
        Route::delete('/hr/staff-welfare-budget/{budget}', [WelfareBudgetController::class, 'destroy'])->name('hr.welfare-budget.destroy');
        Route::get('/operations/reports/export', [FarmUnitController::class, 'reportExport'])->name('operations.reports.export');
        Route::get('/production/finance-draft-budget', [ProductionBudgetController::class, 'financeDraft'])->name('production.finance-draft.index');
        Route::post('/production/finance-draft-budget', [ProductionBudgetController::class, 'addFinanceDraftLine'])->name('production.finance-draft.store');
        Route::put('/production/finance-draft-budget/{budget}', [ProductionBudgetController::class, 'updateFinanceDraftLine'])->name('production.finance-draft.update');
        Route::delete('/production/finance-draft-budget/{budget}', [ProductionBudgetController::class, 'deleteFinanceDraftLine'])->name('production.finance-draft.delete');
        Route::get('/department/weekly-budget', [DepartmentBudgetController::class, 'index'])->name('department-budget.index');
        Route::post('/department/weekly-budget', [DepartmentBudgetController::class, 'store'])->name('department-budget.store');
        Route::put('/department/weekly-budget/{budget}', [DepartmentBudgetController::class, 'update'])->name('department-budget.update');
        Route::delete('/department/weekly-budget/{budget}', [DepartmentBudgetController::class, 'destroy'])->name('department-budget.destroy');
        Route::get('/operations/units/{unit}', [FarmUnitController::class, 'index'])->name('operations.units.index');
        Route::get('/operations/units/{unit}/report', [FarmUnitController::class, 'unitReport'])->name('operations.units.report');
        Route::get('/operations/units/{unit}/export', [FarmUnitController::class, 'export'])->name('operations.units.export');
        Route::post('/operations/units/{unit}', [FarmUnitController::class, 'store'])->name('operations.units.store');
        Route::post('/operations/units/{unit}/animals', [FarmUnitController::class, 'storeAnimal'])->name('operations.units.animals.store');
        Route::get('/operations/livestock/{unit}/{section}', [FarmUnitController::class, 'livestock'])->name('operations.livestock.section');
        Route::post('/operations/livestock/{unit}/{section}', [FarmUnitController::class, 'storeLivestock'])->name('operations.livestock.store');
        Route::get('/executive/{screen}', [ExecutiveController::class, 'index'])->name('executive.index');
        Route::put('/executive/security/roles/{role}', [ExecutiveController::class, 'updatePermissions'])->name('executive.permissions.update');
        Route::post('/executive/security/accounts/{account}/reset-password', [ExecutiveController::class, 'resetAccountPassword'])->name('executive.accounts.reset-password');
        Route::get('/messages', [CommunicationController::class, 'index'])->name('communications.index');
        Route::post('/messages/{communication}/read', [CommunicationController::class, 'markRead'])->name('communications.read');
        Route::get('/directives', [ExecutiveFollowUpController::class, 'index'])->name('directives.index');
        Route::post('/executive-follow-up/directives', [ExecutiveFollowUpController::class, 'storeDirective'])->name('executive-follow-up.directives.store');
        Route::put('/executive-follow-up/directives/{directive}', [ExecutiveFollowUpController::class, 'updateDirective'])->name('executive-follow-up.directives.update');
        Route::post('/executive-follow-up/directives/{directive}/forward', [ExecutiveFollowUpController::class, 'forwardDirective'])->name('directives.forward');
        Route::post('/executive-follow-up/directives/{directive}/acknowledge', [ExecutiveFollowUpController::class, 'acknowledgeDirective'])->name('directives.acknowledge');
        Route::post('/executive-follow-up/meetings', [ExecutiveFollowUpController::class, 'storeMeeting'])->name('executive-follow-up.meetings.store');
        Route::put('/executive-follow-up/meetings/{meeting}', [ExecutiveFollowUpController::class, 'updateMeeting'])->name('executive-follow-up.meetings.update');
        Route::post('/executive-follow-up/funds', [ExecutiveFollowUpController::class, 'storeFundFollowUp'])->name('executive-follow-up.funds.store');
        Route::put('/executive-follow-up/funds/{followUp}', [ExecutiveFollowUpController::class, 'updateFundFollowUp'])->name('executive-follow-up.funds.update');
        Route::post('/executive/messages', [ExecutiveController::class, 'storeMessage'])->name('executive.messages.store');
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('/approvals/{approval}/decision', [ApprovalController::class, 'decide'])->name('approvals.decide');
        Route::post('/approvals/{approval}/budget-funding', [ApprovalController::class, 'updateBudgetFunding'])->name('approvals.budget-funding.update');
        Route::post('/approvals/{approval}/comments', [ApprovalController::class, 'comment'])->name('approvals.comments.store');
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
        Route::post('/finance/budgets/revise', [AccountingController::class, 'reviseBudget'])->name('finance.budgets.revise');
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
        Route::get('/finance/reports/unit-profitability', [FinanceReportController::class, 'profitability'])->name('finance.reports.profitability');
        Route::get('/finance/reports/export/{report}', [FinanceReportController::class, 'export'])->name('finance.reports.export');
        Route::get('/finance/reports/{report}', [FinanceReportController::class, 'statement'])->name('finance.reports.statement');
        Route::get('/finance/administration/{screen}', [FinanceAdministrationController::class, 'index'])->name('finance.admin.index');
        Route::post('/finance/administration/{screen}', [FinanceAdministrationController::class, 'store'])->name('finance.admin.store');
        Route::post('/finance/administration/periods/{period}/toggle', [FinanceAdministrationController::class, 'togglePeriod'])->name('finance.admin.periods.toggle');

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

            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
                ->middleware('module:hr,delete')->name('employees.destroy');

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

            Route::post('/batches/{batch}/request-edit', [BatchController::class, 'requestEdit'])
                ->name('batches.request-edit');
            Route::post('/batches/{batch}/authorize-edit', [BatchController::class, 'authorizeEdit'])
                ->name('batches.authorize-edit');

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
              Route::post('/today/{batch}/feed-serving', [DailyEntryController::class, 'feedServingStore'])
                  ->middleware('module:feed-consumption,create')->name('daily.feed-serving.store');

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
            Route::get('/report', [PoultryReportController::class, 'index'])->name('poultry.report.index');
            Route::get('/report/export', [PoultryReportController::class, 'export'])->name('poultry.report.export');
            Route::get('/performance', [PoultryPerformanceController::class, 'index'])
                ->middleware('module:staff-performance')->name('poultry.performance.index');


   
    
        });

        // Catch-all for modules that are seeded but not yet built.
        Route::get('/m/{key}', [DashboardController::class, 'placeholder'])
            ->name('module.placeholder');
    });
});
