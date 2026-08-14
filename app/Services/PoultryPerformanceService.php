<?php

namespace App\Services;

use App\Models\EggCollection;
use App\Models\FeedConsumption;
use App\Models\MortalityRecord;
use App\Models\EggPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PoultryPerformanceService
{
    /** Mirrors the farm's production workbook, using ERP records. */
    public static function forPeriod(string $from, string $to): Collection
    {
        $days = Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1;
        $collections = EggCollection::with(['batch.breed', 'lines.grade', 'recorder.employee'])
            ->whereBetween('collected_on', [$from, $to])->get();
        $feed = FeedConsumption::with('type')->whereBetween('used_on', [$from, $to])->get()
            ->groupBy(fn ($row) => $row->recorded_by.':'.$row->animal_group_id);
        $mortality = MortalityRecord::whereBetween('occurred_on', [$from, $to])->get()
            ->groupBy(fn ($row) => $row->recorded_by.':'.$row->animal_group_id);

        $rows = $collections->groupBy(fn ($row) => $row->recorded_by.':'.$row->animal_group_id)
            ->map(function ($entries, $key) use ($feed, $mortality, $days) {
                $first = $entries->first();
                $batch = $first->batch;
                $birds = max(0, (int) $batch->current_quantity);
                $eggs = $entries->sum('total_eggs');
                $damage = $entries->sum(fn ($entry) => $entry->lines
                    ->filter(fn ($line) => $line->grade?->is_defect)->sum('total_eggs'));
                $trayValue = $entries->sum(fn ($entry) => $entry->lines->sum(function ($line) use ($entry) {
                    $trayPrice = EggPrice::priceOn($line->egg_grade_id, $entry->collected_on) ?? (float) ($line->grade?->default_price ?? 0);
                    return ($trayPrice / max(1, (int) ($line->grade?->eggs_per_tray ?? 30))) * $line->total_eggs;
                }));
                $feedCost = ($feed[$key] ?? collect())->sum(fn ($row) =>
                    (float) $row->quantity_kg * (float) ($row->type?->cost_per_kg ?? 0));
                $lost = ($mortality[$key] ?? collect())->sum(fn ($row) => $row->quantity_died + $row->quantity_culled);
                $expectedRate = $batch->shouldBeLaying()
                    ? (float) ($batch->breed?->peak_lay_percent ?? 0) : 0;
                $expectedEggs = $birds * ($expectedRate / 100) * $days;
                $deficit = max(0, $expectedEggs - $eggs);
                $averageEggValue = $eggs > 0 ? $trayValue / $eggs : 0;

                return [
                    'attendant' => $first->recorder?->employee?->fullName() ?? $first->recorder?->displayName() ?? 'Unassigned',
                    'employee_id' => $first->recorder?->employee_id,
                    'house' => $batch->house ?: $batch->code,
                    'birds' => $birds, 'feed_cost' => $feedCost, 'deaths' => $lost,
                    'eggs' => $eggs, 'trays' => $eggs / 30, 'damage' => $damage,
                    'production_value' => $trayValue, 'profit_loss' => $trayValue - $feedCost,
                    'performance' => $birds ? ($eggs / $birds) * 100 : 0,
                    'expected_performance' => $expectedRate,
                    'expected_eggs' => $expectedEggs, 'deficit' => $deficit,
                    'days' => $days, 'deficit_cost' => round($deficit * $averageEggValue, 2),
                ];
            })->values()->sortByDesc('performance')->values();

        return $rows->map(fn ($row, $index) => $row + ['position' => $index + 1]);
    }

    public static function shortfallsByEmployee(string $from, string $to): Collection
    {
        return static::forPeriod($from, $to)->whereNotNull('employee_id')->groupBy('employee_id')
            ->map(fn (Collection $rows) => round($rows->sum('deficit_cost'), 2));
    }
}
