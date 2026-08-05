<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Models\BirdConsumption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
 | Birds taken for the farm's own table.
 |
 | Deliberately NOT on the worker's daily screen.
 |
 | A casual worker records eggs, deaths and feed on their own because those
 | are observations — the birds were already dead, the eggs were already
 | laid. Taking a LIVE bird out of the flock is a decision, and a worker
 | who can do it alone can also do it for themselves.
 |
 | So it sits with supervisors and managers, every record names who
 | authorised it, and someone else confirms it afterwards. That puts three
 | people between a live bird and an unexplained gap in the count.
 */
class BirdConsumptionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $records = BirdConsumption::with(['batch', 'recorder.employee', 'authoriser.employee'])
            ->visibleTo($user)
            ->orderByDesc('consumed_on')
            ->paginate(25);

        $thisMonth = BirdConsumption::visibleTo($user)
            ->whereBetween('consumed_on', [now()->startOfMonth(), now()->endOfMonth()]);

        return view('poultry.consumption.index', [
            'records'    => $records,
            'monthQty'   => (clone $thisMonth)->sum('quantity'),
            'monthValue' => (clone $thisMonth)->sum('estimated_value'),
            'pending'    => BirdConsumption::visibleTo($user)->unconfirmed()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('poultry.consumption.create', [
            'batches' => AnimalGroup::visibleTo($user)
                ->where('status', 'active')
                ->orderBy('code')
                ->get(),

            /*
             | Only people senior enough to authorise are offered. A list of
             | everyone invites naming whoever happens to be nearest.
             */
            'authorisers' => User::with(['employee', 'roles'])
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('code', [
                    'director', 'ceo', 'production-manager',
                    'department-manager', 'daily-supervisor',
                ]))
                ->get()
                ->sortBy(fn ($u) => $u->displayName()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('bird-consumption', 'create'), 403);

        $data = $request->validate([
            'animal_group_id' => ['required', 'exists:animal_groups,id'],
            'consumed_on'     => ['required', 'date', 'before_or_equal:today'],
            'quantity'        => ['required', 'integer', 'min:1', 'max:10000'],
            'purpose'         => ['required', 'in:staff_meals,management,visitors,event,sick_bird,other'],
            'detail'          => ['nullable', 'string', 'max:500'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'authorised_by'   => ['nullable', 'exists:users,id'],
            'authorised_name' => ['nullable', 'string', 'max:160'],
        ], [
            'consumed_on.before_or_equal' => 'Birds cannot be taken in the future.',
        ]);

        // Somebody said yes, and their name belongs on the record.
        if (blank($data['authorised_by'] ?? null) && blank($data['authorised_name'] ?? null)) {
            return back()->withInput()->withErrors([
                'authorised_by' => 'Say who authorised this. A bird leaving the flock always needs a name against it.',
            ]);
        }

        $batch = AnimalGroup::findOrFail($data['animal_group_id']);
        $this->assertVisible($request, $batch);

        if ($data['quantity'] > $batch->current_quantity) {
            return back()->withInput()->withErrors([
                'quantity' => 'There are only ' . number_format($batch->current_quantity)
                    . " birds in {$batch->code}.",
            ]);
        }

        DB::transaction(function () use ($batch, $data, $request) {

            $record = BirdConsumption::create([
                'animal_group_id' => $batch->id,
                'department_id'   => $batch->department_id,
                'consumed_on'     => $data['consumed_on'],
                'quantity'        => $data['quantity'],
                'purpose'         => $data['purpose'],
                'detail'          => $data['detail'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? 0,
                'authorised_by'   => $data['authorised_by'] ?? null,
                'authorised_name' => $data['authorised_name'] ?? null,
                'recorded_by'     => $request->user()->id,
                'recorded_at'     => now(),
                'status'          => 'submitted',
            ]);

            /*
             | The flock count falls, but total_mortality does NOT. These
             | birds were not lost — they were used, and the health figures
             | must stay clean.
             */
            $batch->decrement('current_quantity', $data['quantity']);
            $batch->increment('total_consumed', $data['quantity']);

            AuditLog::record(
                'created', $record,
                "{$data['quantity']} birds from {$batch->code} taken for "
                    . $record->purposeLabel() . ', authorised by ' . $record->whoAuthorised() . '.',
                null, null, 'bird-consumption'
            );
        });

        return redirect()
            ->route('consumption.index')
            ->with('status', $data['quantity'] . ' birds recorded as used on the farm.');
    }

    /** A second person confirms. An authorisation is only worth its check. */
    public function confirm(Request $request, BirdConsumption $record): RedirectResponse
    {
        abort_unless($request->user()->canDo('bird-consumption', 'approve'), 403);
        $this->assertVisible($request, $record->batch);

        abort_if(
            $record->recorded_by === $request->user()->id,
            403,
            'Someone else must confirm a record you entered yourself.'
        );

        $record->update([
            'status'       => 'confirmed',
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        AuditLog::record('approved', $record,
            "Confirmed {$record->quantity} birds used on the farm.", null, null, 'bird-consumption');

        return back()->with('status', 'Confirmed.');
    }

    /* ---------------------------------------------------------------- */

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
