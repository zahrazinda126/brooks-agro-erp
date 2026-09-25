<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MortalityCause;
use App\Models\MortalityRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 | The manager's half of the two-stage model.
 |
 | A worker has counted dead birds. Someone qualified now decides why, and
 | records what was done about it. Their judgement is stored separately
 | from the count, so:
 |
 |   - an assessment can be revised without touching the observation
 |   - a count is never quietly reinterpreted by whoever assessed it
 |   - reports can distinguish "4 birds died" from "someone thinks it was heat"
 |
 | Oldest first, because a death waiting three days for a cause is a death
 | whose cause nobody will now remember.
 */
class MortalityAssessmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $pending = MortalityRecord::with(['batch.breed', 'recorder.employee', 'department'])
            ->when(! $this->isProductionManager($user), fn ($query) => $query->visibleTo($user))
            ->pendingAssessment()
            ->orderBy('recorded_at')          // oldest first
            ->get();

        $recent = MortalityRecord::with(['batch', 'cause', 'assessor.employee'])
            ->when(! $this->isProductionManager($user), fn ($query) => $query->visibleTo($user))
            ->where('status', 'assessed')
            ->orderByDesc('assessed_at')
            ->limit(10)
            ->get();

        return view('poultry.assess.index', [
            'pending' => $pending,
            'recent'  => $recent,
        ]);
    }

    public function edit(Request $request, MortalityRecord $record): View
    {
        $this->assertVisible($request, $record);

        $record->load(['batch.breed', 'house', 'recorder.employee', 'department']);

        /*
         | Recent deaths in the same flock. A single bird is noise; four
         | deaths in the same house over three days is a pattern, and the
         | person assessing needs to see it before choosing a cause.
         */
        $context = MortalityRecord::with('cause')
            ->where('animal_group_id', $record->animal_group_id)
            ->where('id', '!=', $record->id)
            ->where('occurred_on', '>=', now()->subDays(14))
            ->orderByDesc('occurred_on')
            ->limit(6)
            ->get();

        return view('poultry.assess.edit', [
            'record'   => $record,
            'causes'   => MortalityCause::where(fn ($q) => $q->where('is_active', true)
                ->orWhere('id', $record->mortality_cause_id))->orderBy('sort_order')->get()->groupBy('category'),
            'context'  => $context,
            'recent14' => $context->sum(fn ($r) => $r->totalLost()) + $record->totalLost(),
        ]);
    }

    public function update(Request $request, MortalityRecord $record): RedirectResponse
    {
        $this->assertVisible($request, $record);

        abort_unless(
            $request->user()->canDo('mortality-assess', 'edit')
                || $request->user()->canDo('mortality-assess', 'create'),
            403
        );

        if (is_string($request->input('vet_name'))) {
            $request->merge(['vet_name' => trim($request->input('vet_name'))]);
        }
        $data = $request->validate([
            'cause_name' => ['required', 'string', 'max:100'],
            'assessment_note'    => ['nullable', 'string', 'max:2000'],
            'action_taken'       => ['nullable', 'string', 'max:2000'],
            'vet_called'         => ['nullable', 'boolean'],
            'vet_name'           => [\Illuminate\Validation\Rule::excludeIf(!$request->boolean('vet_called')), 'required', 'string', 'max:160'],
        ], [
            'cause_name.required' => 'Choose what caused this. If you are not sure, choose "Cause not established" — that is a real answer.',
        ]);

        $customName = trim($data['cause_name']);
        if ($customName === '') {
            return back()->withInput()->withErrors(['cause_name' => 'Write the cause before saving.']);
        }
        $cause = MortalityCause::whereRaw('LOWER(name) = ?', [\Illuminate\Support\Str::lower($customName)])->first();

        // A contagious diagnosis without an action is a diagnosis nobody
        // acted on. Ask for it while the manager is still on the screen.
        if ($cause?->is_contagious && blank($data['action_taken'])) {
            return back()->withInput()->withErrors([
                'action_taken' => "{$cause->name} spreads through a flock. Record what was done about it.",
            ]);
        }

        // Keep free-text assessments out of the shared list of standard causes.
        // Using a related cause preserves the wording in existing reports.
        $cause ??= MortalityCause::create([
            'code' => 'custom-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(24)),
            'name' => $customName, 'category' => 'other', 'is_active' => false,
        ]);

        $record->update([
            'mortality_cause_id' => $cause->id,
            'assessment_note'    => $data['assessment_note'] ?? null,
            'action_taken'       => $data['action_taken'] ?? null,
            'vet_called'         => (bool) ($data['vet_called'] ?? false),
            'vet_name'           => $data['vet_name'] ?? null,
            'assessed_by'        => $request->user()->id,
            'assessed_at'        => now(),
            'status'             => 'assessed',
        ]);

        AuditLog::record(
            'assessed', $record,
            "Assessed {$record->totalLost()} deaths in {$record->batch->code} as {$cause->name}.",
            null,
            ['cause' => $cause->name, 'vet_called' => $record->vet_called, 'vet_name' => $record->vet_name],
            'mortality-assess'
        );

        $message = "Recorded as {$cause->name}.";

        if ($cause->is_contagious) {
            $message .= ' This spreads — watch the flock closely.';
        }

        return redirect()
            ->route('mortality.assess.index')
            ->with('status', $message);
    }

    /* ---------------------------------------------------------------- */

    private function assertVisible(Request $request, MortalityRecord $record): void
    {
        $user = $request->user();

        if ($user->isSuperuser() || $this->isProductionManager($user)) {
            return;
        }

        abort_unless(
            in_array($record->department_id, $user->departmentIds(), true),
            403,
            'This record belongs to a section you are not posted to.'
        );
    }

    private function isProductionManager($user): bool
    {
        return $user->role?->code === 'production-manager'
            || $user->roles->contains('code', 'production-manager')
            || (($user->role?->code === 'department-manager' || $user->roles->contains('code', 'department-manager'))
                && ($user->department?->code === 'production' || $user->departments->contains('code', 'production')));
    }
}
