<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\VaccinationReminderService;
use App\Services\WeeklyBudgetDraftService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(VaccinationReminderService::class)->sendDueReminders())
    ->name('poultry-vaccination-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Artisan::command('budgets:prepare-weekly-drafts', function () {
    $count = app(WeeklyBudgetDraftService::class)->ensureAllForWeek(now());
    $this->info("Prepared {$count} weekly budget draft line(s).");
})->purpose('Prepare each department weekly budget draft from its latest plan');

// Daily is intentional: it remains idempotent and catches a missed Monday scheduler run.
Schedule::command('budgets:prepare-weekly-drafts')
    ->name('prepare-weekly-department-budget-drafts')
    ->dailyAt('00:10')
    ->withoutOverlapping();
