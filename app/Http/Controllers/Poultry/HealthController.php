<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Models\HealthEvent;
use App\Models\Medication;
use App\Models\VaccinationPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/*
 | Flock health.
 |
 | Two jobs, and the second is the one that matters most.
 |
 |   What is due   — the programme, checked against each flock's age
 |   What is held  — which flocks are under withdrawal, and until when
 |
 | A missed vaccination costs a flock. A missed withdrawal costs the farm
 | its name in the market, and possibly worse, so it is shown loudly and
 | never inferred quietly.
 */
class HealthController extends Controller
{
    /** The old Flock health page now lives in Monitor → Health & Treatment → Treatments (all animal units). */
    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('monitor.health', 'treatments');
    }

    /** Previous Flock health overview; kept for reference, no longer routed. */
    public function flockOverview(Request $request): View
    {
        $user = $request->user();

        $batches = AnimalGroup::with('breed')
            ->visibleTo($user)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        $due        = [];
        $overdue    = [];
        $withdrawal = [];

        foreach ($batches as $batch) {
            foreach ($this->dueFor($batch) as $item) {
                if ($item['overdue']) {
                    $overdue[] = $item;
                } else {
                    $due[] = $item;
                }
            }

            $until = HealthEvent::eggsSafeFrom($batch->id);

            if ($until) {
                $withdrawal[] = [
                    'batch'     => $batch,
                    'until'     => $until,
                    'days_left' => HealthEvent::daysOfWithdrawalLeft($batch->id),
                ];
            }
        }

        return view('poultry.health.index', [
            'batches'    => $batches,
            'due'        => collect($due)->sortBy('days_until')->take(20),
            'overdue'    => collect($overdue)->sortByDesc('days_late'),
            'withdrawal' => collect($withdrawal),
            'recent'     => HealthEvent::with(['batch', 'medication', 'recorder.employee'])
                                ->visibleTo($user)
                                ->orderByDesc('occurred_on')
                                ->limit(15)
                                ->get(),
        ]);
    }

    /** One flock's history and what is coming. */
    public function show(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        return view('poultry.health.show', [
            'batch'  => $batch,
            'events' => HealthEvent::with(['medication', 'recorder.employee'])
                            ->where('animal_group_id', $batch->id)
                            ->orderByDesc('occurred_on')
                            ->get(),
            'due'         => collect($this->dueFor($batch))->sortBy('days_until'),
            'safeFrom'    => HealthEvent::eggsSafeFrom($batch->id),
            'daysLeft'    => HealthEvent::daysOfWithdrawalLeft($batch->id),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('poultry.health.create', [
            'batches'     => AnimalGroup::visibleTo($user)->where('status', 'active')->orderBy('code')->get(),
            'medications' => Medication::active()->get()->groupBy('type'),
            'preselect'   => $request->query('batch'),
            'preselectMed' => $request->query('medication'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('health', 'create'), 403);

        $data = $request->validate([
            'animal_group_id'   => ['required', 'exists:animal_groups,id'],
            'event_type'        => ['required', 'in:vaccination,treatment,deworming,inspection,vet_visit'],
            'medication_id'     => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== 'new' && ! Medication::whereKey($value)->exists()) $fail('Choose a medicine from the list.');
            }],
            'new_medication_name' => ['nullable', 'required_if:medication_id,new', 'string', 'max:160'],
            'new_medication_type' => ['nullable', 'required_if:medication_id,new', 'in:vaccine,antibiotic,dewormer,coccidiostat,vitamin,other'],
            'new_withdrawal_days' => ['nullable', 'required_if:medication_id,new', 'integer', 'min:0', 'max:365'],
            'occurred_on'       => ['required', 'date', 'before_or_equal:today'],
            'birds_treated'     => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'dose_given'        => ['nullable', 'string', 'max:160'],
            'days_of_course'    => ['required', 'integer', 'min:1', 'max:60'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'findings'          => ['nullable', 'string', 'max:2000'],
            'cost'              => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'administered_name' => ['nullable', 'string', 'max:160'],
            'vet_involved'      => ['nullable', 'boolean'],
        ], [
            'occurred_on.before_or_equal' => 'This cannot be recorded for a future date.',
        ]);

        $batch = AnimalGroup::findOrFail($data['animal_group_id']);
        $this->assertVisible($request, $batch);

        // Anything other than a plain inspection needs to say what was given.
        if (in_array($data['event_type'], ['vaccination', 'treatment', 'deworming'], true)
            && blank($data['medication_id'] ?? null)) {
            return back()->withInput()->withErrors([
                'medication_id' => 'Say what was given.',
            ]);
        }

        // A medicine typed in on the form joins the list (or reuses one with the same name).
        if (($data['medication_id'] ?? null) === 'new') {
            $name = trim($data['new_medication_name']);
            $med = Medication::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
            if (! $med) {
                $med = Medication::create([
                    'code' => 'custom-'.\Illuminate\Support\Str::slug($name).'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4)),
                    'name' => $name, 'type' => $data['new_medication_type'],
                    'withdrawal_days_eggs' => (int) $data['new_withdrawal_days'], 'withdrawal_days_meat' => 0,
                    'banned_in_lay' => false, 'sort_order' => 999, 'is_active' => true,
                    'note' => 'Added from a health record by '.$request->user()->displayName().'.',
                ]);
                \App\Models\AuditLog::record('medication_added', $med, "Medicine '{$name}' added to the list from a health record.", null, $med->only(['name', 'type', 'withdrawal_days_eggs']), 'health');
            }
            $data['medication_id'] = $med->id;
        }
        unset($data['new_medication_name'], $data['new_medication_type'], $data['new_withdrawal_days']);

        $med   = $data['medication_id'] ? Medication::find($data['medication_id']) : null;
        $start = Carbon::parse($data['occurred_on']);
        $days  = (int) $data['days_of_course'];

        /*
         | Withdrawal is worked out here and stored on the record, counted
         | from the END of the course. A five-day course of a drug with
         | seven days' withdrawal holds the eggs for twelve days from the
         | first dose — getting that wrong is the difference between safe
         | eggs and an illegal sale.
         */
        $eggsEnd = HealthEvent::withdrawalEnd($med, $start, $days, 'eggs');
        $meatEnd = HealthEvent::withdrawalEnd($med, $start, $days, 'meat');

        $event = HealthEvent::create([
            'animal_group_id'      => $batch->id,
            'department_id'        => $batch->department_id,
            'event_type'           => $data['event_type'],
            'medication_id'        => $data['medication_id'] ?? null,
            'occurred_on'          => $data['occurred_on'],
            'birds_treated'        => $data['birds_treated'] ?? $batch->current_quantity,
            'dose_given'           => $data['dose_given'] ?? null,
            'days_of_course'       => $days,
            'withdrawal_ends_eggs' => $eggsEnd?->toDateString(),
            'withdrawal_ends_meat' => $meatEnd?->toDateString(),
            'reason'               => $data['reason'] ?? null,
            'findings'             => $data['findings'] ?? null,
            'cost'                 => $data['cost'] ?? 0,
            'administered_by'      => $request->user()->id,
            'administered_name'    => $data['administered_name'] ?? null,
            'vet_involved'         => (bool) ($data['vet_involved'] ?? false),
            'recorded_by'          => $request->user()->id,
            'recorded_at'          => now(),
        ]);

        AuditLog::record(
            'created', $event,
            $event->typeLabel() . " recorded for {$batch->code}"
                . ($med ? ": {$med->name}" : '') . '.',
            null, null, 'health'
        );

        $message = $event->typeLabel() . ' recorded.';

        if ($eggsEnd) {
            $message = "Recorded. EGGS FROM {$batch->code} MUST NOT BE SOLD until "
                . $eggsEnd->format('j F') . '.';
        }

        return redirect()
            ->route('health.show', $batch)
            ->with('status', $message);
    }

    /* ----------------------------------------------------------------
     | What the programme says is due for a flock
     | ---------------------------------------------------------------- */

    private function dueFor(AnimalGroup $batch): array
    {
        $age   = $batch->ageInDays();
        $plans = VaccinationPlan::with('medication')->for($batch)->get();

        // What has already been given, so a done vaccination stops nagging.
        $given = HealthEvent::where('animal_group_id', $batch->id)
            ->whereNotNull('medication_id')
            ->get()
            ->groupBy('medication_id');

        $out = [];

        foreach ($plans as $plan) {
            foreach ($plan->dueAges() as $dueAge) {

                // Not yet on the horizon, or long past and irrelevant.
                if ($dueAge > $age + 14 || $dueAge < $age - 60) {
                    continue;
                }

                $dueDate = $batch->received_date->copy()
                    ->addDays($dueAge - $batch->age_days_on_arrival);

                /*
                 | Counted as done if it was given anywhere inside the
                 | window. A vaccination two days early is a vaccination,
                 | and flagging it as missed teaches people to ignore the
                 | flags.
                 */
                $done = ($given[$plan->medication_id] ?? collect())
                    ->first(fn ($e) => abs($e->occurred_on->diffInDays($dueDate, false)) <= $plan->window_days + 2);

                if ($done) {
                    continue;
                }

                $daysUntil = (int) now()->startOfDay()->diffInDays($dueDate, false);

                $out[] = [
                    'batch'      => $batch,
                    'plan'       => $plan,
                    'medication' => $plan->medication,
                    'due_date'   => $dueDate,
                    'due_age'    => $dueAge,
                    'days_until' => $daysUntil,
                    'days_late'  => $daysUntil < 0 ? abs($daysUntil) : 0,
                    'overdue'    => $daysUntil < -$plan->window_days,
                ];
            }
        }

        return $out;
    }

    private function assertVisible(Request $request, AnimalGroup $batch): void
    {
        $user = $request->user();

        if ($user->isSuperuser()) {
            return;
        }

        abort_unless(
            in_array($batch->department_id, $user->departmentIds(), true),
            403,
            'This flock belongs to a section you are not posted to.'
        );
    }
}
