<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out, for every active schedule item and group, when the next dose is due.
 * A dose counts for an item when it was recorded against that item, or when the
 * vaccine name matches (so doses recorded before the schedule existed still count).
 */
class LivestockVaccinationSchedule
{
    public const DUE_SOON_DAYS = 14;

    /** One row per schedule item and group: plan, group, last given, next due, status. */
    public function rows(string $unit, bool $activeOnly = false): Collection
    {
        $plans = DB::table('livestock_vaccination_plans')->where('unit_code', $unit)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('vaccine')->get();
        $groups = DB::table('livestock_groups')->where('unit_code', $unit)->where('status', 'active')->orderBy('group_code')->get()->keyBy('id');

        return $plans->flatMap(function ($plan) use ($groups) {
            $targets = $plan->livestock_group_id ? $groups->only([$plan->livestock_group_id]) : $groups;
            if ($targets->isEmpty()) return [['plan' => $plan, 'group' => null, 'last' => null, 'next' => null, 'status' => 'no-group']];

            return $targets->map(function ($group) use ($plan) {
                $last = DB::table('livestock_health_records')->where('livestock_group_id', $group->id)->where('event_type', 'vaccination')
                    ->where(fn ($q) => $q->where('livestock_vaccination_plan_id', $plan->id)->orWhereRaw('lower(condition_or_vaccine) = ?', [mb_strtolower($plan->vaccine)]))
                    ->max('recorded_on');
                $last = $last ? Carbon::parse($last) : null;
                $next = $last
                    ? ($plan->repeat_every_months ? $last->copy()->addMonthsNoOverflow($plan->repeat_every_months) : null)
                    : Carbon::parse($plan->first_due_on);

                return ['plan' => $plan, 'group' => $group, 'last' => $last, 'next' => $next, 'status' => $this->status($plan, $next)];
            })->values();
        })->values();
    }

    private function status(object $plan, ?Carbon $next): string
    {
        if (! $plan->is_active) return 'inactive';
        if (! $next) return 'done';
        if ($next->lt(today())) return 'overdue';
        if ($next->lte(today()->addDays(self::DUE_SOON_DAYS))) return 'due';
        return 'scheduled';
    }
}
