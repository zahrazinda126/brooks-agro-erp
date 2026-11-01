<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Death examination for goats, rabbits and dogs: the manager records why the animals died. */
class LivestockDeathExaminationController extends Controller
{
    public function index(Request $request, string $unit): View
    {
        $this->authorizeFor($request->user(), $unit);
        $base = fn () => DB::table('livestock_mortality_records')
            ->join('livestock_groups', 'livestock_groups.id', '=', 'livestock_mortality_records.livestock_group_id')
            ->leftJoin('users as recorder', 'recorder.id', '=', 'livestock_mortality_records.recorded_by')
            ->leftJoin('users as assessor', 'assessor.id', '=', 'livestock_mortality_records.assessed_by')
            ->where('livestock_groups.unit_code', $unit)
            ->select('livestock_mortality_records.*', 'livestock_groups.group_code', 'recorder.name as recorder_name', 'assessor.name as assessor_name');

        return view('operations.livestock.examine', [
            'unit' => $unit,
            'unitName' => FarmUnitController::UNITS[$unit]['name'],
            'waiting' => $base()->where('livestock_mortality_records.status', 'pending_assessment')->orderBy('occurred_on')->get(),
            'examined' => $base()->where('livestock_mortality_records.status', 'assessed')->orderByDesc('assessed_at')->orderByDesc('occurred_on')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, string $unit, int $death): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFor($user, $unit);
        $record = DB::table('livestock_mortality_records')->join('livestock_groups', 'livestock_groups.id', '=', 'livestock_mortality_records.livestock_group_id')
            ->where('livestock_groups.unit_code', $unit)->where('livestock_mortality_records.id', $death)
            ->select('livestock_mortality_records.*', 'livestock_groups.group_code')->first();
        abort_unless($record, 404);

        $data = $request->validate([
            'established_cause' => ['required', 'string', 'max:160'],
            'assessment_note' => ['nullable', 'string', 'max:2000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'vet_called' => ['nullable', 'boolean'],
            'vet_name' => [Rule::excludeIf(! $request->boolean('vet_called')), 'required', 'string', 'max:160'],
        ], ['established_cause.required' => 'Write what caused the death. If it is not known, write "Cause not established".']);

        $update = ['established_cause' => trim($data['established_cause']), 'assessment_note' => $data['assessment_note'] ?? null,
            'action_taken' => $data['action_taken'] ?? null, 'vet_called' => $request->boolean('vet_called'), 'vet_name' => $data['vet_name'] ?? null,
            'status' => 'assessed', 'assessed_by' => $user->id, 'assessed_at' => now(), 'updated_at' => now()];
        DB::table('livestock_mortality_records')->where('id', $death)->update($update);
        AuditLog::record('livestock_death_examined', null, "Examined {$record->quantity} death(s) in {$record->group_code}: {$update['established_cause']}.",
            ['status' => $record->status, 'established_cause' => $record->established_cause], $update, $unit);

        return back()->with('status', "Examination saved for {$record->group_code}.");
    }

    /** Waiting examinations per unit, for the "Examine deaths" choices. */
    public static function waitingCounts(): array
    {
        return DB::table('livestock_mortality_records')->join('livestock_groups', 'livestock_groups.id', '=', 'livestock_mortality_records.livestock_group_id')
            ->where('livestock_mortality_records.status', 'pending_assessment')->groupBy('livestock_groups.unit_code')
            ->selectRaw('livestock_groups.unit_code, count(*) as waiting')->pluck('waiting', 'unit_code')->map(fn ($n) => (int) $n)->all();
    }

    public static function canExamine(User $user, string $unit): bool
    {
        return $user->canAccessFarmUnit($unit) && ($user->canAccess('mortality-assess') || $user->canDo($unit, 'edit'));
    }

    private function authorizeFor(User $user, string $unit): void
    {
        abort_unless(in_array($unit, ['goats', 'rabbits', 'dogs'], true), 404);
        abort_unless(self::canExamine($user, $unit), 403);
    }
}
