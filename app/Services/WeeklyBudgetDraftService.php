<?php

namespace App\Services;

use App\Models\Budget;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class WeeklyBudgetDraftService
{
    /**
     * Start a new week's working draft from the most recent budget.
     * Existing lines are never overwritten, so managers remain free to edit
     * or remove this week's requirements after the draft appears.
     */
    public function ensureForWeek(CarbonInterface $week, int $recordedBy, int $departmentId): int
    {
        $start = $week->copy()->startOfWeek();
        $startDate = $start->toDateString();

        // Any line means this week has already been initialized. This is
        // deliberately broader than a status check: a manager may remove a
        // recurring item, and a later page refresh must not put it back.
        if (Budget::where('department_id', $departmentId)->whereDate('period_start', $startDate)->exists()) {
            return 0;
        }

        $sourceWeek = Budget::where('department_id', $departmentId)->whereDate('period_start', '<', $startDate)->max('period_start');
        if (! $sourceWeek) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($sourceWeek, $start, $startDate, $recordedBy, $departmentId, &$created) {
            foreach (Budget::where('department_id', $departmentId)->whereDate('period_start', $sourceWeek)->orderBy('id')->get() as $source) {
                $line = Budget::firstOrCreate(
                    [
                        'period_start' => $startDate,
                        'department_id' => $departmentId,
                        'category' => $source->category,
                        'item' => $source->item,
                    ],
                    [
                        'unit' => $source->unit,
                        'quantity' => $source->quantity,
                        'unit_price' => $source->unit_price,
                        'planned_amount' => $source->planned_amount,
                        'period_end' => $start->copy()->endOfWeek()->toDateString(),
                        'notes' => $source->notes,
                        'status' => 'draft',
                        'recorded_by' => $recordedBy,
                        'funding_status' => 'pending',
                    ],
                );

                if ($line->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        return $created;
    }
}
