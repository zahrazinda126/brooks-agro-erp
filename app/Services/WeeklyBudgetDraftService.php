<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class WeeklyBudgetDraftService
{
    private const BUDGET_DEPARTMENTS = ['production', 'hr', 'finance', 'administration'];

    private const STARTER_LINES = [
        'production' => [
            ['Feed & stock', 'Weekly feed requirements', 'kg'],
            ['Veterinary', 'Vaccines and veterinary supplies', 'item'],
            ['Farm operations', 'Farm inputs and tools', 'item'],
            ['Operations', 'Transport and fuel', 'trip'],
            ['Maintenance', 'Repairs and maintenance', 'item'],
        ],
        'hr' => [
            ['Staff welfare', 'Staff meals and welfare', 'person'],
            ['Staff welfare', 'Protective clothing and PPE', 'item'],
            ['Administration', 'Staff transport', 'trip'],
            ['Administration', 'Office and administration supplies', 'item'],
            ['Facilities', 'Facilities repairs and maintenance', 'item'],
        ],
        'finance' => [
            ['Finance operations', 'Bank and mobile money charges', 'item'],
            ['Finance operations', 'Audit, tax and accounting requirements', 'item'],
            ['Administration', 'Office supplies', 'item'],
            ['Administration', 'Communication', 'item'],
            ['Finance operations', 'Finance facilitation and transport', 'trip'],
        ],
        'administration' => [
            ['Administration', 'Office and meeting requirements', 'item'],
            ['Administration', 'Communication', 'item'],
            ['Facilities', 'Utilities and facilities', 'item'],
            ['Facilities', 'Repairs and maintenance', 'item'],
            ['Administration', 'Transport and facilitation', 'trip'],
        ],
    ];

    /** Each production unit's own operating requirements: its starting draft (quantity 1, price 0 to fill in). */
    public const UNIT_STARTER_LINES = [
        'Poultry' => [['Layers mash', 'kg'], ['Chick and grower mash', 'kg'], ['Vaccines', 'doses'], ['Dewormers and vitamins', 'item'], ['Disinfectant', 'litres'], ['Wood shavings / litter', 'bags'], ['Egg trays', 'trays']],
        'Goat Unit' => [['Dairy meal / concentrates', 'kg'], ['Hay and fodder', 'bales'], ['Mineral licks', 'blocks'], ['Dewormers', 'doses'], ['Acaricide (tick spray)', 'litres'], ['Veterinary drugs', 'item']],
        'Rabbits Unit' => [['Rabbit pellets', 'kg'], ['Hay and greens', 'kg'], ['Veterinary drugs', 'item'], ['Cage repairs and bedding', 'item']],
        'Dogs Unit' => [['Dog food', 'kg'], ['Rabies and other vaccines', 'doses'], ['Dewormers', 'doses'], ['Flea and tick treatment', 'item']],
        'Kituntu Garden' => [['Seeds and seedlings', 'item'], ['Fertiliser', 'kg'], ['Pesticides and fungicides', 'litres'], ['Irrigation fuel / water', 'litres'], ['Casual labour', 'days']],
        'Kituntu Tomato Garden' => [['Tomato seedlings', 'seedlings'], ['Stakes and twine', 'item'], ['Fungicides and pesticides', 'litres'], ['Fertiliser', 'kg'], ['Casual labour', 'days']],
        'Kituntu Passion Fruits' => [['Passion fruit seedlings', 'seedlings'], ['Poles and wire', 'item'], ['Fertiliser', 'kg'], ['Pesticides and fungicides', 'litres'], ['Pruning and weeding labour', 'days']],
    ];

    /**
     * Give every production unit its own starting draft the first time it has none.
     * After that the weekly copy carries each unit forward, so lines a manager removes stay removed.
     */
    public function ensureUnitStarters(CarbonInterface $week, int $recordedBy, int $departmentId): int
    {
        if (Department::whereKey($departmentId)->value('code') !== 'production') return 0;
        $start = $week->copy()->startOfWeek();
        $created = 0;
        foreach (self::UNIT_STARTER_LINES as $unit => $lines) {
            if (Budget::where('department_id', $departmentId)->where('category', $unit)->exists()) continue;
            foreach ($lines as [$item, $measure]) {
                Budget::create([
                    'period_start' => $start->toDateString(), 'period_end' => $start->copy()->endOfWeek()->toDateString(),
                    'department_id' => $departmentId, 'category' => $unit, 'item' => $item, 'unit' => $measure,
                    'quantity' => 1, 'unit_price' => 0, 'planned_amount' => 0, 'status' => 'draft',
                    'recorded_by' => $recordedBy, 'funding_status' => 'pending', 'carryover_type' => 'template',
                ]);
                $created++;
            }
        }
        return $created;
    }

    /**
     * Start a new week's working draft from the most recent budget.
     * Existing lines are never overwritten, so managers remain free to edit
     * or remove this week's requirements after the draft appears.
     */
    public function ensureForWeek(CarbonInterface $week, int $recordedBy, int $departmentId): int
    {
        $start = $week->copy()->startOfWeek();
        $startDate = $start->toDateString();

        $created = 0;

        DB::transaction(function () use ($start, $startDate, $recordedBy, $departmentId, &$created) {
            $claimed = DB::table('weekly_budget_draft_initializations')->insertOrIgnore([
                'department_id' => $departmentId, 'week_start' => $startDate, 'source_week' => null,
                'initialized_by' => $recordedBy, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($claimed === 0) return;

            $sourceWeek = Budget::where('department_id', $departmentId)->whereDate('period_start', '<', $startDate)->max('period_start');
            $hasCurrentLines = Budget::where('department_id', $departmentId)->whereDate('period_start', $startDate)->exists();

            if (! $hasCurrentLines) {
                $sources = $sourceWeek
                    ? Budget::where('department_id', $departmentId)->whereDate('period_start', $sourceWeek)
                        ->where(fn ($query) => $query->whereNull('funding_status')->orWhere('funding_status', '!=', 'cancelled'))
                        ->orderBy('id')->get()
                    : collect();

                foreach ($sources as $source) {
                    Budget::create([
                        'period_start' => $startDate, 'period_end' => $start->copy()->endOfWeek()->toDateString(),
                        'department_id' => $departmentId, 'category' => $source->category, 'item' => $source->item,
                        'unit' => $source->unit, 'quantity' => $source->quantity, 'unit_price' => $source->unit_price,
                        'planned_amount' => $source->planned_amount, 'notes' => $source->notes, 'status' => 'draft',
                        'recorded_by' => $recordedBy, 'funding_status' => 'pending', 'source_budget_id' => $source->id,
                        'carryover_type' => $source->funding_status === 'unfunded' ? 'unfunded' : 'recurring',
                    ]);
                    $created++;
                }

                if ($sources->isEmpty()) {
                    $code = Department::whereKey($departmentId)->value('code');
                    foreach (self::STARTER_LINES[$code] ?? [] as [$category, $item, $unit]) {
                        Budget::create([
                            'period_start' => $startDate, 'period_end' => $start->copy()->endOfWeek()->toDateString(),
                            'department_id' => $departmentId, 'category' => $category, 'item' => $item, 'unit' => $unit,
                            'quantity' => 1, 'unit_price' => 0, 'planned_amount' => 0, 'status' => 'draft',
                            'recorded_by' => $recordedBy, 'funding_status' => 'pending', 'carryover_type' => 'template',
                        ]);
                        $created++;
                    }
                }
            }

            DB::table('weekly_budget_draft_initializations')
                ->where('department_id', $departmentId)->whereDate('week_start', $startDate)
                ->update(['source_week' => $sourceWeek, 'updated_at' => now()]);
        });

        return $created;
    }

    public function ensureAllForWeek(CarbonInterface $week): int
    {
        $created = 0;
        foreach (Department::whereIn('code', self::BUDGET_DEPARTMENTS)->where('is_active', true)->get() as $department) {
            $owner = User::where('is_active', true)->where(function ($query) use ($department) {
                $query->where('department_id', $department->id)->orWhereHas('departments', fn ($q) => $q->whereKey($department->id));
            })->orderBy('id')->value('id') ?? User::where('is_active', true)->orderBy('id')->value('id');
            if ($owner) $created += $this->ensureForWeek($week, $owner, $department->id);
        }
        return $created;
    }
}
