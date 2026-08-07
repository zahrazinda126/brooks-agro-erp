<?php

use App\Models\Budget;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $periods = DB::table('budgets')
            ->where('status', 'submitted')
            ->select('period_start')
            ->distinct()
            ->pluck('period_start');

        foreach ($periods as $periodStart) {
            $budget = DB::table('budgets')
                ->where('period_start', $periodStart)
                ->where('status', 'submitted')
                ->orderBy('id')
                ->first();

            if (! $budget) {
                continue;
            }

            $budgetIds = DB::table('budgets')
                ->where('period_start', $periodStart)
                ->pluck('id');

            $alreadyQueued = DB::table('approvals')
                ->where('approvable_type', Budget::class)
                ->whereIn('approvable_id', $budgetIds)
                ->exists();

            if ($alreadyQueued) {
                continue;
            }

            DB::table('approvals')->insert([
                'approvable_type' => Budget::class,
                'approvable_id' => $budget->id,
                'module_key' => 'budget',
                'department_id' => DB::table('users')->where('id', $budget->recorded_by)->value('department_id'),
                'submitted_by' => $budget->recorded_by,
                'submitted_at' => $budget->submitted_at ?? $budget->updated_at,
                'submission_note' => $budgetIds->count().' budget lines submitted for executive review.',
                'status' => 'pending',
                'level' => 1,
                'required_levels' => 1,
                'due_at' => now()->addDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Historical approval decisions must not be deleted on rollback.
    }
};
