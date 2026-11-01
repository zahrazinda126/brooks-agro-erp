<?php

namespace App\Http\Controllers;

use App\Models\{AnimalGroup, AuditLog, MortalityRecord, PoultryHouse, SickBay, SickBayAdmission, SickBayCheck, User};
use Carbon\Carbon;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sick bays (Monitor → Health & Treatment). The Production Manager and the vet admit sick animals and
 * record their progress. Poultry: sick birds leave their house but stay in the batch; recovered birds go
 * back to their house; deaths and culls become a deaths record for examination. Goats, rabbits and dogs:
 * deaths and culls reduce the group, as on the livestock deaths form.
 */
class SickBayController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $units = $this->viewableUnits($user);
        abort_if($units === [], 403);
        $f = $request->validate([
            'unit' => ['nullable', Rule::in(array_keys($units))], 'sick_bay' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['in_care', 'discharged', 'all'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $status = $f['status'] ?? 'in_care';
        $unitFilter = $f['unit'] ?? null;
        $admissions = SickBayAdmission::with(['sickBay', 'batch', 'house', 'checks.recorder', 'admittedBy'])
            ->whereIn('unit_code', array_keys($units))
            ->when($unitFilter, fn ($q, $u) => $q->where('unit_code', $u))
            ->when($f['sick_bay'] ?? null, fn ($q, $id) => $q->where('sick_bay_id', $id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('admitted_on', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('admitted_on', '<=', $d))
            ->orderByRaw("status = 'discharged'")->latest('admitted_on')->latest('id')->paginate(30)->withQueryString();

        $bays = SickBay::where('is_active', true)->whereIn('unit_code', array_keys($units))->orderBy('unit_code')->orderBy('name')->get();
        $inCare = SickBayAdmission::where('status', 'in_care')->whereIn('unit_code', array_keys($units))->when($unitFilter, fn ($q, $u) => $q->where('unit_code', $u));

        return view('monitor.sick-bay', [
            'units' => $units, 'filters' => $f + ['status' => $status], 'admissions' => $admissions, 'bays' => $bays,
            'inCareCount' => (int) (clone $inCare)->sum('in_care'), 'inCareBays' => (clone $inCare)->distinct('sick_bay_id')->count('sick_bay_id'),
            'canRecord' => $this->canRecord($user),
            'sources' => $this->canRecord($user) ? $this->sources($units) : [],
        ]);
    }

    public function storeBay(Request $request): RedirectResponse
    {
        abort_unless($this->canRecord($request->user()), 403);
        $data = $request->validate([
            'unit_code' => ['required', Rule::in(array_keys(SickBay::UNITS))],
            'name' => ['required', 'string', 'max:80', Rule::unique('sick_bays')->where('unit_code', $request->input('unit_code'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['name.unique' => 'There is already a sick bay with this name for that unit.']);
        $bay = SickBay::create($data + ['created_by' => $request->user()->id]);
        AuditLog::record('sick_bay_created', $bay, "Sick bay \"{$bay->name}\" added for ".SickBay::UNITS[$bay->unit_code].'.', null, $data, 'health');

        return back()->with('status', 'Sick bay added.');
    }

    public function admit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canRecord($user), 403);
        $data = $request->validate([
            'sick_bay_id' => ['required', Rule::exists('sick_bays', 'id')->where('is_active', true)],
            'source' => ['required', 'string', 'max:40'], // "house:<id>" for poultry, "group:<id>" for livestock
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'admitted_on' => ['required', 'date', 'before_or_equal:today'],
            'signs' => ['nullable', 'array'], 'signs.*' => [Rule::in(SickBayAdmission::SIGNS)],
            'condition' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000'],
        ], ['source.required' => 'Choose where the sick animals came from.']);
        $bay = SickBay::findOrFail($data['sick_bay_id']);
        [$kind, $id] = array_pad(explode(':', $data['source'], 2), 2, null);

        $admission = DB::transaction(function () use ($data, $bay, $kind, $id, $user) {
            $values = ['sick_bay_id' => $bay->id, 'unit_code' => $bay->unit_code, 'quantity' => $data['quantity'], 'in_care' => $data['quantity'],
                'admitted_on' => $data['admitted_on'], 'signs' => $data['signs'] ?? [], 'condition' => $data['condition'] ?? null, 'notes' => $data['notes'] ?? null, 'admitted_by' => $user->id];
            if ($bay->unit_code === 'poultry') {
                abort_unless($kind === 'house', 422, 'Choose the poultry house the birds came from.');
                $house = PoultryHouse::lockForUpdate()->findOrFail((int) $id);
                abort_unless($house->animal_group_id, 422, 'That house has no birds.');
                abort_if($data['quantity'] > $house->bird_quantity, 422, 'There are only '.number_format($house->bird_quantity).' birds in '.$house->displayNumber().'.');
                // The birds leave the house; they are still part of the batch.
                $house->decrement('bird_quantity', $data['quantity']);
                $values += ['animal_group_id' => $house->animal_group_id, 'poultry_house_id' => $house->id];
            } else {
                abort_unless($kind === 'group', 422, 'Choose the group the animals came from.');
                $group = DB::table('livestock_groups')->where('id', (int) $id)->where('unit_code', $bay->unit_code)->lockForUpdate()->first();
                abort_unless($group, 422, 'Choose a group from this unit.');
                $already = (int) SickBayAdmission::where('livestock_group_id', $group->id)->where('status', 'in_care')->sum('in_care');
                abort_if($data['quantity'] + $already > $group->current_quantity, 422, 'That group has only '.number_format($group->current_quantity - $already).' animals not already in a sick bay.');
                $values += ['livestock_group_id' => $group->id];
            }
            return SickBayAdmission::create($values);
        });
        AuditLog::record('sick_bay_admission', $admission, number_format($admission->quantity)." animals taken into {$bay->name}.", null, $admission->toArray(), 'health');

        return back()->with('status', number_format($admission->quantity).' taken into '.$bay->name.'.');
    }

    public function check(Request $request, SickBayAdmission $admission): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canRecord($user), 403);
        abort_unless($admission->status === 'in_care', 422, 'These animals have already left the sick bay.');
        $data = $request->validate([
            'checked_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$admission->admitted_on->toDateString()],
            'recovered' => ['nullable', 'integer', 'min:0'], 'died' => ['nullable', 'integer', 'min:0'], 'culled' => ['nullable', 'integer', 'min:0'],
            'progress' => ['required', Rule::in(array_keys(SickBayCheck::PROGRESS))],
            'treatment' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
        ], ['checked_on.after_or_equal' => 'The check cannot be before the animals were taken in.']);
        $recovered = (int) ($data['recovered'] ?? 0); $died = (int) ($data['died'] ?? 0); $culled = (int) ($data['culled'] ?? 0);
        $leaving = $recovered + $died + $culled;
        if ($leaving > $admission->in_care) {
            return back()->withErrors(['recovered' => 'Only '.number_format($admission->in_care).' are still in the sick bay.'])->withInput();
        }

        DB::transaction(function () use ($admission, $data, $recovered, $died, $culled, $leaving, $user) {
            $admission = SickBayAdmission::lockForUpdate()->findOrFail($admission->id);
            $note = trim('Sick bay: '.$admission->sickBay->name.'. '.($data['notes'] ?? ''));
            if ($admission->unit_code === 'poultry') {
                $house = $admission->poultry_house_id ? PoultryHouse::lockForUpdate()->find($admission->poultry_house_id) : null;
                $batch = AnimalGroup::lockForUpdate()->find($admission->animal_group_id);
                // Recovered birds go back to their house (if the house still holds the batch).
                if ($recovered && $house && $house->animal_group_id === $admission->animal_group_id) $house->increment('bird_quantity', $recovered);
                if (($died || $culled) && $batch) {
                    MortalityRecord::create([
                        'animal_group_id' => $batch->id, 'poultry_house_id' => $admission->poultry_house_id, 'department_id' => $batch->department_id,
                        'occurred_on' => $data['checked_on'], 'quantity_died' => $died, 'quantity_culled' => $culled,
                        'recorded_by' => $user->id, 'recorded_at' => now(), 'worker_note' => $note, 'status' => 'pending_assessment',
                    ]);
                    $batch->decrement('current_quantity', $died + $culled);
                    $batch->increment('total_mortality', $died);
                    $batch->increment('total_culled', $culled);
                }
            } elseif ($died || $culled) {
                DB::table('livestock_mortality_records')->insert([
                    'livestock_group_id' => $admission->livestock_group_id, 'occurred_on' => $data['checked_on'], 'quantity' => $died + $culled,
                    'suspected_cause' => $admission->condition, 'notes' => $note.($culled ? " ({$culled} culled)" : ''), 'status' => 'pending_assessment',
                    'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('livestock_groups')->where('id', $admission->livestock_group_id)->decrement('current_quantity', $died + $culled);
            }
            $left = $admission->in_care - $leaving;
            SickBayCheck::create([
                'sick_bay_admission_id' => $admission->id, 'checked_on' => $data['checked_on'], 'recovered' => $recovered, 'died' => $died, 'culled' => $culled,
                'still_sick' => $left, 'progress' => $data['progress'], 'treatment' => $data['treatment'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id,
            ]);
            $admission->update([
                'in_care' => $left, 'recovered' => $admission->recovered + $recovered, 'died' => $admission->died + $died, 'culled' => $admission->culled + $culled,
                'status' => $left === 0 ? 'discharged' : 'in_care', 'discharged_on' => $left === 0 ? $data['checked_on'] : null,
            ]);
        });

        return back()->with('status', $leaving ? 'Progress saved. '.collect(['recovered' => $recovered, 'died' => $died, 'culled' => $culled])->filter()->map(fn ($n, $k) => "{$n} {$k}")->implode(', ').'.' : 'Progress saved.');
    }

    /** Where sick animals can come from, per unit: poultry houses with birds, livestock groups. */
    private function sources(array $units): array
    {
        $out = [];
        if (isset($units['poultry'])) {
            $out['poultry'] = PoultryHouse::with('batch')->whereNotNull('animal_group_id')->where('bird_quantity', '>', 0)->get()
                ->sortBy(fn ($h) => $h->displayNumber(), SORT_NATURAL)->mapWithKeys(fn ($h) => ['house:'.$h->id => ($h->batch?->code ?? 'Batch').' · '.$h->displayNumber().' ('.number_format($h->bird_quantity).' birds)'])->all();
        }
        foreach (['goats', 'rabbits', 'dogs'] as $u) {
            if (! isset($units[$u])) continue;
            $out[$u] = DB::table('livestock_groups')->where('unit_code', $u)->where('status', 'active')->orderBy('group_code')->get()
                ->mapWithKeys(fn ($g) => ['group:'.$g->id => $g->group_code.' ('.number_format($g->current_quantity).')'])->all();
        }
        return $out;
    }

    private function viewableUnits(User $user): array
    {
        return array_filter(SickBay::UNITS, fn ($label, $unit) => $this->canRecord($user) || ($unit === 'poultry'
            ? $user->canViewAllReports() || $user->canAccess('production') || $user->canAccess('health')
            : $user->canAccessFarmUnit($unit)), ARRAY_FILTER_USE_BOTH);
    }

    /** The Production Manager and the vet look after the sick bays. */
    public static function canRecord(User $user): bool
    {
        return $user->isSuperuser() || $user->roles->pluck('code')->push($user->role?->code)->intersect(['production-manager', 'veterinary-officer'])->isNotEmpty();
    }
}
