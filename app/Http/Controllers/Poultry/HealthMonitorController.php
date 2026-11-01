<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\{AnimalGroup, HealthEvent, MortalityRecord, User};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Monitor → Health & Treatment for every animal unit on one page:
 * treatments (incl. deworming and spraying) and deaths, for poultry, goats, rabbits and dogs.
 */
class HealthMonitorController extends Controller
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs'];

    private const TASKS = ['treatments' => 'Treatments', 'deaths' => 'Deaths'];

    public function index(Request $request, string $task): View
    {
        $user = $request->user();
        $units = array_filter(self::UNITS, fn ($label, $unit) => $this->canSee($user, $unit), ARRAY_FILTER_USE_BOTH);
        abort_if($units === [], 403);

        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->toDateString();
        abort_if($from > $to, 422, 'The start date must be before the end date.');
        $unit = array_key_exists((string) $request->query('unit'), $units) ? $request->query('unit') : 'all';
        // Batch filter values are "<unit>:<id>", so one list covers poultry batches and livestock groups.
        [$batchUnit, $batchId] = array_pad(explode(':', (string) $request->query('batch'), 2), 2, null);
        $batchId = isset($units[$batchUnit]) && ctype_digit((string) $batchId) ? (int) $batchId : null;
        $shows = fn (string $u) => isset($units[$u]) && ($unit === 'all' || $unit === $u) && (! $batchId || $batchUnit === $u);

        $rows = collect();
        if ($shows('poultry')) $rows = $rows->merge($task === 'deaths' ? $this->poultryDeaths($from, $to, $batchId) : $this->poultryTreatments($from, $to, $batchId));
        foreach (['goats', 'rabbits', 'dogs'] as $u) {
            if ($shows($u)) $rows = $rows->merge($task === 'deaths' ? $this->livestockDeaths($u, $from, $to, $batchId) : $this->livestockTreatments($u, $from, $to, $batchId));
        }
        $rows = $rows->sortByDesc('date')->values();

        $groups = collect($units)->mapWithKeys(fn ($label, $u) => [$u => $u === 'poultry'
            ? AnimalGroup::orderBy('code')->get(['id', 'code'])->map(fn ($b) => ['id' => $b->id, 'code' => $b->code])
            : DB::table('livestock_groups')->where('unit_code', $u)->orderBy('group_code')->get(['id', 'group_code'])->map(fn ($g) => ['id' => $g->id, 'code' => $g->group_code])]);

        // Animals under medicine withdrawal: their eggs or milk/meat must not be sold yet.
        $withdrawals = collect();
        if ($task === 'treatments') {
            if (isset($units['poultry']) && in_array($unit, ['all', 'poultry'], true)) {
                foreach (AnimalGroup::visibleTo($user)->where('status', 'active')->orderBy('code')->get() as $flock) {
                    if ($until = HealthEvent::eggsSafeFrom($flock->id)) $withdrawals->push(['unit' => 'Poultry', 'group' => $flock->code, 'what' => 'eggs', 'until' => $until]);
                }
            }
            $livestockUnits = array_values(array_intersect(array_keys($units), $unit === 'all' ? ['goats', 'rabbits', 'dogs'] : [$unit]));
            if ($livestockUnits) {
                DB::table('livestock_health_records')->join('livestock_groups', 'livestock_groups.id', '=', 'livestock_health_records.livestock_group_id')
                    ->whereIn('livestock_groups.unit_code', $livestockUnits)->whereDate('withdrawal_ends_on', '>=', today())
                    ->groupBy('livestock_groups.unit_code', 'livestock_groups.group_code')
                    ->selectRaw('livestock_groups.unit_code, livestock_groups.group_code, max(withdrawal_ends_on) as until')->get()
                    ->each(fn ($w) => $withdrawals->push(['unit' => self::UNITS[$w->unit_code], 'group' => $w->group_code, 'what' => $w->unit_code === 'goats' ? 'milk or meat' : 'meat', 'until' => Carbon::parse($w->until)]));
            }
        }

        return view('poultry.health-monitor', [
            'withdrawals' => $withdrawals,
            'task' => $task, 'taskTitle' => self::TASKS[$task], 'rows' => $rows, 'units' => $units, 'unit' => $unit,
            'from' => $from, 'to' => $to, 'batch' => $batchId ? $batchUnit.':'.$batchId : '', 'groups' => $groups,
            'recordLinks' => $this->recordLinks($user, $task, $units),
            'examineLinks' => $task === 'deaths' ? $this->examineLinks($user, $units) : [],

        ]);
    }

    /** Poultry deaths are recorded per batch: pick the batch, then its deaths form opens. */
    public function recordPoultryDeaths(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canDo('mortality-entry', 'create'), 403);
        // Occupied houses of the batches this user can see; choosing a house also chooses its batch.
        $batchIds = AnimalGroup::visibleTo($user)->where('status', 'active')->pluck('id');
        $houses = \App\Models\PoultryHouse::with('batch')->whereIn('animal_group_id', $batchIds)->where('is_active', true)->where('bird_quantity', '>', 0)
            ->get()->sortBy('house_number', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('poultry.record-deaths', ['houses' => $houses]);
    }

    private function poultryTreatments(string $from, string $to, ?int $batchId): Collection
    {
        return HealthEvent::with(['batch', 'medication', 'recorder'])->whereDate('occurred_on', '>=', $from)->whereDate('occurred_on', '<=', $to)
            ->when($batchId, fn ($q) => $q->where('animal_group_id', $batchId))->get()
            ->map(fn ($x) => ['date' => $x->occurred_on->toDateString(), 'unit' => 'Poultry', 'group' => $x->batch?->code, 'event' => $x->typeLabel(),
                'detail' => $x->medication?->name ?? $x->reason ?? '—', 'count' => $x->birds_treated, 'extra' => $x->withdrawal_ends_eggs ? 'Eggs held until '.$x->withdrawal_ends_eggs->format('d M Y') : null,
                'by' => $x->recorder?->displayName()]);
    }

    private function poultryDeaths(string $from, string $to, ?int $batchId): Collection
    {
        return MortalityRecord::with(['batch', 'cause', 'recorder'])->whereDate('occurred_on', '>=', $from)->whereDate('occurred_on', '<=', $to)
            ->when($batchId, fn ($q) => $q->where('animal_group_id', $batchId))->get()
            ->map(fn ($x) => ['date' => $x->occurred_on->toDateString(), 'unit' => 'Poultry', 'group' => $x->batch?->code, 'event' => $x->quantity_culled ? 'Died / culled' : 'Died',
                'detail' => $x->cause?->name ?? $x->worker_note ?? '—', 'count' => $x->totalLost(), 'extra' => ucfirst(str_replace('_', ' ', $x->status)),
                'by' => $x->recorder?->displayName()]);
    }

    private function livestockTreatments(string $unit, string $from, string $to, ?int $groupId): Collection
    {
        return $this->livestock('livestock_health_records', 'recorded_on', $unit, $from, $to, $groupId)
            ->whereIn('livestock_health_records.event_type', ['treatment', 'deworming', 'spraying'])
            ->select('livestock_health_records.*', 'livestock_groups.group_code', 'users.name as recorder')->get()
            ->map(fn ($x) => ['date' => $x->recorded_on, 'unit' => self::UNITS[$unit], 'group' => $x->group_code, 'event' => ucfirst($x->event_type),
                'detail' => trim($x->condition_or_vaccine.($x->medicine ? ' · '.$x->medicine : '')), 'count' => $x->animals_affected,
                'extra' => $x->withdrawal_ends_on ? 'Withdrawal until '.Carbon::parse($x->withdrawal_ends_on)->format('d M Y') : null, 'by' => $x->recorder]);
    }

    private function livestockDeaths(string $unit, string $from, string $to, ?int $groupId): Collection
    {
        return $this->livestock('livestock_mortality_records', 'occurred_on', $unit, $from, $to, $groupId)
            ->select('livestock_mortality_records.*', 'livestock_groups.group_code', 'users.name as recorder')->get()
            ->map(fn ($x) => ['date' => $x->occurred_on, 'unit' => self::UNITS[$unit], 'group' => $x->group_code, 'event' => 'Died',
                'detail' => ($x->status === 'assessed' ? $x->established_cause : $x->suspected_cause) ?: '—', 'count' => $x->quantity,
                'extra' => ($x->status === 'assessed' ? 'Examined' : 'Waiting for examination').($x->disposal_method ? ' · Disposal: '.$x->disposal_method : ''), 'by' => $x->recorder]);
    }

    private function livestock(string $table, string $date, string $unit, string $from, string $to, ?int $groupId)
    {
        return DB::table($table)->join('livestock_groups', 'livestock_groups.id', '=', $table.'.livestock_group_id')
            ->leftJoin('users', 'users.id', '=', $table.'.recorded_by')
            ->where('livestock_groups.unit_code', $unit)->whereDate($table.'.'.$date, '>=', $from)->whereDate($table.'.'.$date, '<=', $to)
            ->when($groupId, fn ($q) => $q->where($table.'.livestock_group_id', $groupId));
    }

    /** "Examine deaths" choices: one per unit, with how many deaths are waiting. */
    private function examineLinks(User $user, array $units): array
    {
        $links = [];
        $waiting = \App\Http\Controllers\Operations\LivestockDeathExaminationController::waitingCounts();
        foreach ($units as $u => $label) {
            if ($u === 'poultry') {
                if ($user->canAccess('mortality-assess')) $links['Poultry deaths'] = [route('mortality.assess.index'), MortalityRecord::where('status', 'pending_assessment')->count()];
            } elseif (\App\Http\Controllers\Operations\LivestockDeathExaminationController::canExamine($user, $u)) {
                $links[rtrim($label, 's').' deaths'] = [route('operations.livestock.examine', $u), $waiting[$u] ?? 0];
            }
        }
        return $links;
    }

    /** "+ Record" choices: one per unit (goats also deworming and spraying). */
    private function recordLinks(User $user, string $task, array $units): array
    {
        $links = [];
        foreach ($units as $u => $label) {
            if ($u === 'poultry') {
                if ($task === 'treatments' && $user->canDo('health', 'create')) $links['Poultry treatment'] = route('health.create');
                if ($task === 'deaths' && $user->canDo('mortality-entry', 'create')) $links['Poultry deaths'] = route('monitor.health.poultry-deaths');
                continue;
            }
            if (! ($user->canDo($u, 'create') || $user->canDo($u, 'edit'))) continue;
            // Goat deworming and spraying are a Type on the goat treatment form.
            $sections = $task === 'deaths' ? ['mortality' => 'deaths'] : ['treatment' => 'treatment'];
            foreach ($sections as $section => $word) {
                $links[rtrim($label, 's').' '.$word] = route('operations.livestock.section', ['unit' => $u, 'section' => $section, 'create' => 1, 'monitor' => 'health']);
            }
        }
        return $links;
    }

    private function canSee(User $user, string $unit): bool
    {
        return $unit === 'poultry'
            ? $user->canViewAllReports() || $user->canAccess('production') || $user->canAccess('batches') || $user->canAccess('egg-inventory') || $user->canAccess('health')
            : $user->canAccessFarmUnit($unit);
    }
}
