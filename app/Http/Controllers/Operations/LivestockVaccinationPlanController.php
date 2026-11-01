<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Schedule items (vaccine, group, first due date, repeat) for goats, rabbits and dogs. */
class LivestockVaccinationPlanController extends Controller
{
    public function store(Request $request, string $unit): RedirectResponse
    {
        $data = $this->validated($request, $unit);
        $id = DB::table('livestock_vaccination_plans')->insertGetId($data + ['unit_code' => $unit, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::record('livestock_vaccination_plan_added', null, "Vaccination schedule item '{$data['vaccine']}' added for {$unit}.", null, ['plan_id' => $id] + $data, $unit);

        return back()->with('status', 'Schedule item added.');
    }

    public function update(Request $request, string $unit, int $plan): RedirectResponse
    {
        $before = DB::table('livestock_vaccination_plans')->where('unit_code', $unit)->find($plan);
        abort_unless($before, 404);
        $data = $this->validated($request, $unit);
        DB::table('livestock_vaccination_plans')->where('id', $plan)->update($data + ['updated_at' => now()]);
        AuditLog::record('livestock_vaccination_plan_updated', null, "Vaccination schedule item '{$data['vaccine']}' updated for {$unit}.", (array) $before, $data, $unit);

        return back()->with('status', 'Schedule item updated.');
    }

    private function validated(Request $request, string $unit): array
    {
        abort_unless(in_array($unit, ['goats', 'rabbits', 'dogs'], true), 404);
        $user = $request->user();
        abort_unless($user->canAccessFarmUnit($unit) && ($user->canDo($unit, 'create') || $user->canDo($unit, 'edit')), 403);

        $data = $request->validate([
            'vaccine' => ['required', 'string', 'max:180'],
            'livestock_group_id' => ['nullable', Rule::exists('livestock_groups', 'id')->where('unit_code', $unit)],
            'first_due_on' => ['required', 'date'],
            'repeat_every_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_essential' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return ['vaccine' => trim($data['vaccine']), 'livestock_group_id' => $data['livestock_group_id'] ?? null, 'first_due_on' => $data['first_due_on'],
            'repeat_every_months' => $data['repeat_every_months'] ?? null, 'is_essential' => $request->boolean('is_essential'),
            'is_active' => $request->has('_editing') ? $request->boolean('is_active') : true, 'note' => $data['note'] ?? null];
    }
}
