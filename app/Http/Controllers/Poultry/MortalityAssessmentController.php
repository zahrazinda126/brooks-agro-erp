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
            ->visibleTo($user)
            ->pendingAssessment()
            ->orderBy('recorded_at')          // oldest first
            ->get();

        $recent = MortalityRecord::with(['batch', 'cause', 'assessor.employee'])
            ->visibleTo($user)
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

        $record->load(['batch.breed', 'recorder.employee', 'department']);

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
            'causes'   => MortalityCause::ordered()->get()->groupBy('category'),
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

        $data = $request->validate([
            'mortality_cause_id' => ['required', 'exists:mortality_causes,id'],
            'assessment_note'    => ['nullable', 'string', 'max:2000'],
            'action_taken'       => ['nullable', 'string', 'max:2000'],
            'vet_called'         => ['nullable', 'boolean'],
        ], [
            'mortality_cause_id.required' => 'Choose what caused this. If you are not sure, choose "Cause not established" — that is a real answer.',
        ]);

        $cause = MortalityCause::find($data['mortality_cause_id']);

        // A contagious diagnosis without an action is a diagnosis nobody
        // acted on. Ask for it while the manager is still on the screen.
        if ($cause?->is_contagious && blank($data['action_taken'])) {
            return back()->withInput()->withErrors([
                'action_taken' => "{$cause->name} spreads through a flock. Record what was done about it.",
            ]);
        }

        $record->update([
            'mortality_cause_id' => $data['mortality_cause_id'],
            'assessment_note'    => $data['assessment_note'] ?? null,
            'action_taken'       => $data['action_taken'] ?? null,
            'vet_called'         => (bool) ($data['vet_called'] ?? false),
            'assessed_by'        => $request->user()->id,
            'assessed_at'        => now(),
            'status'             => 'assessed',
        ]);

        AuditLog::record(
            'assessed', $record,
            "Assessed {$record->totalLost()} deaths in {$record->batch->code} as {$cause->name}.",
            null,
            ['cause' => $cause->name, 'vet_called' => $record->vet_called],
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

        if ($user->isSuperuser()) {
            return;
        }

        abort_unless(
            in_array($record->department_id, $user->departmentIds(), true),
            403,
            'This record belongs to a section you are not posted to.'
        );
    }
}
