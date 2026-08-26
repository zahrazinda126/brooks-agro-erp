<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\VaccinationReminderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(VaccinationReminderService::class)->sendDueReminders())
    ->name('poultry-vaccination-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();
