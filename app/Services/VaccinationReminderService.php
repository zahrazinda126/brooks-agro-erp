<?php

namespace App\Services;

use App\Models\AnimalGroup;
use App\Models\Communication;
use App\Models\HealthEvent;
use App\Models\User;
use App\Models\VaccinationPlan;

class VaccinationReminderService
{
    public function sendDueReminders(?User $onlyRecipient = null): int
    {
        $recipients = $onlyRecipient
            ? collect([$onlyRecipient])->filter(fn (User $user) => $this->isProductionManager($user))
            : User::with(['role', 'roles'])->where('is_active', true)->get()
                ->filter(fn (User $user) => $this->isProductionManager($user));

        if ($recipients->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach (AnimalGroup::with('breed')->where('status', 'active')->get() as $batch) {
            $age = $batch->ageInDays();
            $given = HealthEvent::where('animal_group_id', $batch->id)
                ->where('event_type', 'vaccination')
                ->whereNotNull('medication_id')
                ->get()
                ->groupBy('medication_id');

            foreach (VaccinationPlan::with('medication')->for($batch)->get() as $plan) {
                foreach ($plan->dueAges() as $dueAge) {
                    $dueDate = $batch->received_date->copy()->addDays($dueAge - $batch->age_days_on_arrival);
                    $daysUntil = (int) today()->diffInDays($dueDate, false);

                    // Notify when the permitted vaccination window opens,
                    // and retain the alert while an overdue dose is unresolved.
                    if ($daysUntil > $plan->window_days || $daysUntil < -60) {
                        continue;
                    }

                    $done = ($given[$plan->medication_id] ?? collect())->first(
                        fn (HealthEvent $event) => abs($event->occurred_on->diffInDays($dueDate, false)) <= $plan->window_days + 2
                    );
                    if ($done) {
                        continue;
                    }

                    $vaccine = $plan->medication?->name ?? 'Scheduled vaccine';
                    $subject = "Vaccination due: {$batch->code} · {$vaccine} · {$dueDate->format('Y-m-d')}";
                    $timing = $daysUntil < 0
                        ? abs($daysUntil).' day(s) overdue'
                        : ($daysUntil === 0 ? 'due today' : 'due in '.$daysUntil.' day(s)');
                    $url = route('vaccination-schedule.index', [
                        'view' => 'records',
                        'batch' => $batch->id,
                        'vaccine' => $plan->medication_id,
                    ], absolute: false).'#record-vaccination';

                    foreach ($recipients as $recipient) {
                        if (Communication::where('recipient_id', $recipient->id)->where('subject', $subject)->exists()) {
                            continue;
                        }

                        app(CommunicationService::class)->toUser(
                            $recipient,
                            $subject,
                            "{$vaccine} for batch {$batch->code} is {$timing}. Record it when administered.",
                            'vaccination',
                            null,
                            $url,
                        );
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }

    private function isProductionManager(User $user): bool
    {
        return $user->role?->code === 'production-manager'
            || $user->roles->contains('code', 'production-manager');
    }
}
