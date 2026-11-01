<?php

namespace App\Http\Controllers\Poultry;

use App\Services\FeedStockService;
use App\Services\CommunicationService;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Models\EggCollection;
use App\Models\EggCollectionLine;
use App\Models\EggGrade;
use App\Models\FeedConsumption;
use App\Models\FeedType;
use App\Models\MortalityRecord;
use App\Models\PoultryHouseAssignment;
use App\Models\PoultryHouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
 | What a worker does on their phone.
 |
 | Written for someone who may read slowly, has never used a system like
 | this, and is standing in a poultry house with one hand free. Every
 | decision here follows from that:
 |
 |   - One question per screen. Never a form with twelve fields.
 |   - Tap to count, don't type. Big + and - buttons.
 |   - Nothing is asked that the system can work out for itself.
 |   - No words a worker would not use out loud.
 |
 | A worker never records WHY birds died. They count. The cause is a
 | judgement, and it belongs to someone qualified to make it.
 */
class DailyEntryController extends Controller
{
    /** The worker's home: what needs doing today, one big button each. */
    public function index(Request $request): View
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $batches = AnimalGroup::with('breed')
            ->visibleTo($user)
            ->when(! $user->canAccessFarmUnit('poultry'), fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', 'active')
            ->orderBy('code')
            ->get();

        // Poultry casual workers work only in houses assigned by Production.
        // Managers and multi-role users retain their broader operational view.
        $isPoultryAttendant = $user->isDataEntryOnly() && (
            $user->role?->code === 'poultry-attendant'
            || $user->roles()->where('code', 'poultry-attendant')->exists()
        );
        $assignedHouses = collect();
        if ($user->isDataEntryOnly() && $user->employee_id) {
            $assignedHouses = PoultryHouseAssignment::with('house')
                ->where('employee_id', $user->employee_id)->whereNull('assigned_until')
                ->whereHas('house', fn ($query) => $query->where('is_active', true)->whereNotNull('animal_group_id'))
                ->get()->pluck('house')->filter()->unique('id')->values();
            $batches = $batches->whereIn('id', $assignedHouses->pluck('animal_group_id')->filter())->values();
        }

        if ($isPoultryAttendant) {
            $houseChoices = $assignedHouses;
            $selectedHouse = $houseChoices->firstWhere('id', (int) $request->query('house'))
                ?? $houseChoices->first();
            $houseIds = $selectedHouse ? collect([$selectedHouse->id]) : $houseChoices->pluck('id');
            $todayEntries = collect();

            if ($houseIds->isNotEmpty()) {
                EggCollection::with(['house', 'recorder'])->whereIn('poultry_house_id', $houseIds)
                    ->whereDate('collected_on', $today)->whereIn('status', ['approved', 'submitted', 'pending'])
                    ->latest('recorded_at')->get()->each(fn ($row) => $todayEntries->push([
                        'time' => $row->recorded_at ?? $row->created_at, 'label' => 'Eggs collected',
                        'house' => $row->house?->house_number ?? 'House', 'quantity' => number_format($row->total_eggs).' eggs',
                    ]));
                MortalityRecord::with('house')->whereIn('poultry_house_id', $houseIds)->whereDate('occurred_on', $today)
                    ->latest('recorded_at')->get()->each(fn ($row) => $todayEntries->push([
                        'time' => $row->recorded_at ?? $row->created_at, 'label' => 'Deaths recorded',
                        'house' => $row->house?->house_number ?? 'House', 'quantity' => number_format($row->quantity_died + $row->quantity_culled).' birds',
                    ]));
                FeedConsumption::with('house')->whereIn('poultry_house_id', $houseIds)->whereDate('used_on', $today)
                    ->whereIn('status', ['approved', 'submitted', 'pending'])->latest('recorded_at')->get()->each(fn ($row) => $todayEntries->push([
                        'time' => $row->recorded_at ?? $row->created_at, 'label' => 'Feed recorded',
                        'house' => $row->house?->house_number ?? 'House', 'quantity' => number_format((float) $row->quantity_kg, 2).' kg',
                    ]));
                DB::table('poultry_feed_servings as servings')->join('poultry_houses as houses', 'houses.id', '=', 'servings.poultry_house_id')
                    ->whereIn('servings.poultry_house_id', $houseIds)->whereDate('servings.fed_on', $today)
                    ->orderByDesc('servings.fed_at')->get(['servings.fed_at', 'servings.quantity_kg', 'houses.house_number'])
                    ->each(fn ($row) => $todayEntries->push([
                        'time' => $today.' '.$row->fed_at, 'label' => 'Feed given', 'house' => $row->house_number,
                        'quantity' => number_format((float) $row->quantity_kg, 2).' kg',
                    ]));
            }

            $myTasks = \App\Http\Controllers\Operations\FarmTaskController::activeFor($user);
            $myTaskToday = $myTasks->isEmpty() ? collect() : DB::table('farm_task_entries')
                ->whereIn('farm_task_assignment_id', $myTasks->pluck('id'))->whereDate('done_on', $today)->pluck('farm_task_assignment_id');

            return view('poultry.daily.attendant-home', [
                'user' => $user, 'houseChoices' => $houseChoices, 'selectedHouse' => $selectedHouse,
                'todayEntries' => $todayEntries->sortByDesc(fn ($entry) => (string) $entry['time'])->values(),
                'myTasks' => $myTasks, 'myTaskToday' => $myTaskToday,
            ]);
        }

        // What has already been done today, so the screen can say so
        // rather than letting someone enter it twice.
        $doneFeed = FeedConsumption::whereDate('used_on', $today)
            ->whereIn('animal_group_id', $batches->pluck('id'))
            ->pluck('animal_group_id')
            ->unique();
        if ($user->isDataEntryOnly() && $assignedHouses->isNotEmpty()) {
            $givenByHouse = DB::table('poultry_feed_servings')->whereDate('fed_on', $today)
                ->whereIn('poultry_house_id', $assignedHouses->pluck('id'))
                ->select('poultry_house_id', DB::raw('sum(quantity_kg) as total_kg'))
                ->groupBy('poultry_house_id')->pluck('total_kg', 'poultry_house_id');
            $doneFeed = $batches->filter(function ($batch) use ($assignedHouses, $givenByHouse) {
                $houses = $assignedHouses->where('animal_group_id', $batch->id);

                return $houses->isNotEmpty() && $houses->every(fn ($house) =>
                    (float) ($givenByHouse[$house->id] ?? 0) >= $house->bird_quantity * config('farm.feed_kg_per_bird_per_day')
                );
            })->pluck('id');
        }
        $visibleLivestockUnits = collect(['goats','rabbits','dogs'])->filter(fn ($unit) => $user->canAccessFarmUnit($unit))->values();
        $livestockGroups = DB::table('livestock_groups')->whereIn('unit_code',$visibleLivestockUnits)->where('status','active')->orderBy('unit_code')->orderBy('group_code')->get()->groupBy('unit_code');
        $dailyLivestock = [
            'feed' => DB::table('livestock_feed_records')->whereDate('recorded_on', $today)->pluck('livestock_group_id')->unique(),
            'vaccination' => DB::table('livestock_health_records')->whereDate('recorded_on', $today)->where('event_type', 'vaccination')->pluck('livestock_group_id')->unique(),
            'treatment' => DB::table('livestock_health_records')->whereDate('recorded_on', $today)->where('event_type', 'treatment')->pluck('livestock_group_id')->unique(),
            'mortality' => DB::table('livestock_mortality_records')->whereDate('occurred_on', $today)->pluck('livestock_group_id')->unique(),
            'production' => DB::table('livestock_production_records')->whereDate('recorded_on', $today)->pluck('livestock_group_id')->unique(),
        ];
        $gardenActivity = DB::table('farm_unit_records')->where('unit_code','garden')->whereDate('record_date',$today)->count();
        $productionDraftTotal = \App\Models\Budget::where('period_start',now()->startOfWeek()->toDateString())->where('status','draft')->where(function($q){foreach(['poultry','goat','rabbit','dog','garden','kituntu'] as $word)$q->orWhereRaw('lower(category) like ?', ['%'.$word.'%']);})->sum('planned_amount');
        $feedBalances = DB::table('feed_stock_movements')->whereIn('unit_code',collect(['poultry','goats','rabbits','dogs'])->filter(fn($unit)=>$user->canAccessFarmUnit($unit)))->select('unit_code','feed_name',DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance_kg"))->groupBy('unit_code','feed_name')->havingRaw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) > 0")->orderBy('unit_code')->orderBy('feed_name')->get();

        return view('poultry.daily.index', [
            'batches'  => $batches,
            'doneFeed' => $doneFeed,
            'livestockGroups'=>$livestockGroups,
            'dailyLivestock'=>$dailyLivestock,
            'gardenActivity'=>$gardenActivity,
            'productionDraftTotal'=>$productionDraftTotal,
            'visibleFarmUnits'=>collect(['poultry','goats','rabbits','dogs','garden'])->filter(fn ($unit) => $user->canAccessFarmUnit($unit))->values(),
            'showProductionPlanning'=>! $user->isDataEntryOnly() && $user->canAccess('budget'),
            'feedBalances'=>$feedBalances,
            'assignedHouses'=>$assignedHouses,
        ]);
    }

    /* ================================================================
     | EGGS
     | ================================================================ */

    public function eggsForm(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);
        $houses = $this->feedHousesFor($request, $batch);
        abort_if($houses->isEmpty(), 422, 'No poultry house is assigned to this batch.');
        $houseChoices = $this->houseChoicesFor($request, $batch);
        $selectedHouse = $houses->firstWhere('id', (int) $request->query('house')) ?? $houses->first();
        $hour = now()->hour;
        $session = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

        /*
         | Every active size, whatever the flock's age: a young flock still lays some large eggs, and a
         | size that is missing gets recorded under the wrong one. Defect grades are listed separately.
         */
        $likely = EggGrade::where('is_defect', false)->where('is_active', true)->orderBy('sort_order')->get();

        $defects = EggGrade::where('is_defect', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('poultry.daily.eggs', [
            'batch'    => $batch,
            'likely'   => $likely,
            'defects'  => $defects,
            'session'  => $session,
            'houses' => $houses,
            'houseChoices' => $houseChoices,
            'selectedHouse' => $selectedHouse,
        ]);
    }

    public function eggsStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('egg-entry', 'create'), 403);

        $data = $request->validate([
            'trays'       => ['array'],
            'trays.*'     => ['nullable', 'integer', 'min:0', 'max:2000'],
            'loose'       => ['array'],
            'loose.*'     => ['nullable', 'integer', 'min:0', 'max:29'],
            'egg_mass_g' => ['nullable', 'numeric', 'min:0'],
            'bird_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'worker_note' => ['nullable', 'string', 'max:500'],
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
        ]);
        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');

        $grades = EggGrade::where('is_active', true)->get()->keyBy('id');
        $total  = 0;
        $damagedEggs = 0;
        $lines  = [];

        foreach ($grades as $id => $grade) {
            $trays = (int) ($data['trays'][$id] ?? 0);
            $loose = (int) ($data['loose'][$id] ?? 0);

            if ($trays === 0 && $loose === 0) {
                continue;
            }

            $eggs   = ($trays * $grade->eggs_per_tray) + $loose;
            $total += $eggs;
            if ($grade->is_defect) {
                $damagedEggs += $eggs;
            }

            $lines[] = [
                'egg_grade_id' => $id,
                'trays'        => $trays,
                'loose_eggs'   => $loose,
                'total_eggs'   => $eggs,
            ];
        }

        if ($total === 0) {
            return back()
                ->withInput()
                ->withErrors(['trays' => 'Enter how many eggs you collected before saving.']);
        }

        $savedAt = now();
        $session = $savedAt->hour < 12 ? 'morning' : ($savedAt->hour < 17 ? 'afternoon' : 'evening');

        DB::transaction(function () use ($batch, $house, $data, $lines, $total, $damagedEggs, $request, $savedAt, $session) {

            $collection = EggCollection::create([
                    'animal_group_id' => $batch->id,
                    'poultry_house_id' => $house->id,
                    'department_id'   => $batch->department_id,
                    'collected_on'    => $savedAt->toDateString(),
                    'session'         => $session,
                    // What the worker says. recorded_at, set below, is what
                    // the system saw — the two are different facts.
                    'collected_at'  => $savedAt->format('H:i:s'),
                    'total_eggs'    => $total,
                    'egg_mass_g' => $data['egg_mass_g'] ?? null,
                    'damaged_eggs' => $damagedEggs,
                    'bird_weight_kg' => $data['bird_weight_kg'] ?? null,
                    'recorded_by'   => $request->user()->id,
                    'recorded_at'   => $savedAt,
                    'worker_note'   => $data['worker_note'] ?? null,
                    'status'        => 'submitted',
            ]);

            foreach ($lines as $line) {
                $collection->lines()->create($line);
            }

            /*
             | The first eggs from a flock are a milestone: the moment
             | "expected to lay" becomes "laying", and the gap between the
             | two is how you judge a supplier's birds.
             */
            if (! $batch->actual_lay_date) {
                $batch->update(['actual_lay_date' => now()->toDateString()]);
            }

            AuditLog::record('created', $collection,
                "Recorded {$total} eggs for {$batch->code}.", null, null, 'egg-entry');
        });
        app(\App\Services\CommunicationService::class)->recordAwaitingReview('poultry', now(), number_format($total).' eggs from '.$batch->code, $request->user()->id, eggs: true);

        return redirect()
            ->route('daily.index', ['house' => $house->id])
            ->with('done', [
                'what'  => number_format($total) . ' eggs',
                'where' => $batch->code,
            ]);
    }

    /* ================================================================
     | DEATHS — the worker counts, and stops there
     | ================================================================ */

    public function deathsForm(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        $houses = $this->feedHousesFor($request, $batch);
        abort_if($houses->isEmpty(), 422, 'No poultry house is assigned to this batch.');
        $selectedHouse = $houses->firstWhere('id', (int) $request->query('house')) ?? $houses->first();

        return view('poultry.daily.deaths', [
            'batch' => $batch,
            'houses' => $houses,
            'houseChoices' => $this->houseChoicesFor($request, $batch),
            'selectedHouse' => $selectedHouse,
        ]);
    }

    public function deathsStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('mortality-entry', 'create'), 403);

        $data = $request->validate([
            'quantity_died'   => ['required', 'integer', 'min:0', 'max:100000'],
            'quantity_culled' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'worker_note'     => ['nullable', 'string', 'max:500'],
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
        ], [
            'quantity_died.required' => 'Enter how many birds died. Enter 0 if none died.',
        ]);
        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');

        $died   = (int) $data['quantity_died'];
        $culled = (int) ($data['quantity_culled'] ?? 0);
        $total  = $died + $culled;

        // Opened from Monitor → Deaths: go back there; workers go back to My Work.
        $fromMonitor = $request->input('from') === 'monitor';

        if ($total === 0) {
            return ($fromMonitor ? redirect()->route('monitor.health', 'deaths')->with('status', 'No deaths recorded for '.$batch->code.'.') : redirect()->route('daily.index', ['house' => $house->id]))
                ->with('done', ['what' => 'No deaths', 'where' => $batch->code]);
        }

        // Cannot lose more birds than are alive.
        if ($total > $house->bird_quantity) {
            return back()->withInput()->withErrors([
                'quantity_died' => "There are only " . number_format($house->bird_quantity)
                    . " birds in {$house->house_number}. Check the number.",
            ]);
        }

        $record = DB::transaction(function () use ($batch, $house, $died, $culled, $total, $data, $request) {

            $record = MortalityRecord::create([
                'animal_group_id' => $batch->id,
                'poultry_house_id' => $house->id,
                'department_id'   => $batch->department_id,
                'occurred_on'     => now()->toDateString(),
                'quantity_died'   => $died,
                'quantity_culled' => $culled,
                'recorded_by'     => $request->user()->id,
                'recorded_at'     => now(),
                'worker_note'     => $data['worker_note'] ?? null,
                'status'          => 'pending_assessment',
            ]);

            // The live bird count falls immediately. The cause can wait;
            // the count cannot, because everything else reads it.
            $batch->decrement('current_quantity', $total);
            $batch->increment('total_mortality', $died);
            $batch->increment('total_culled', $culled);
            $house->decrement('bird_quantity', $total);

            AuditLog::record('created', $record,
                "Recorded {$total} birds lost from {$batch->code}.", null, null, 'mortality-entry');

            return $record;
        });

        app(CommunicationService::class)->toRole(
            'production-manager',
            "Mortality recorded in {$house->house_number}",
            $request->user()->displayName()." recorded {$died} died and {$culled} culled for batch {$batch->code} in {$house->house_number}. Please assess the cause.",
            $request->user()->id,
            route('mortality.assess.edit', $record, absolute: false),
        );

        if ($fromMonitor) {
            return redirect()->route('monitor.health', 'deaths')->with('status', $total.' '.($total === 1 ? 'bird' : 'birds').' recorded for '.$batch->code.'. A manager will look at why.');
        }

        return redirect()
            ->route('daily.index', ['house' => $house->id])
            ->with('done', [
                'what'  => $total . ' ' . ($total === 1 ? 'bird' : 'birds'),
                'where' => $batch->code,
                'note'  => 'A manager will look at why.',
            ]);
    }

    /* ================================================================
     | FEED — several feeds a day, any bag size, or weighed loose
     | ================================================================ */

    public function feedForm(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        /*
         | Grouped by what the feed is FOR, because that is how a farmer
         | chooses one: a complete ration is fed on its own, everything
         | else is mixed in. Keeps the screen short even with twenty feeds
         | on the list.
         */
        $groups = FeedType::active()->where('code', '!=', 'mixed-feed')
            ->get()
            ->groupBy('category')
            ->sortBy(fn ($g, $k) => array_search($k, ['complete', 'energy', 'protein', 'mineral', 'supplement']));

        // Today's entries, so the form comes back filled in rather than
        // making someone start again to correct one number.
        $houses = $this->feedHousesFor($request, $batch);
        $selectedHouse = $houses->firstWhere('id', (int) $request->query('house')) ?? $houses->first();
        abort_unless($selectedHouse, 422, 'No poultry house is assigned to this batch.');

        $existing = FeedConsumption::where('animal_group_id', $batch->id)
            ->where('poultry_house_id', $selectedHouse->id)
            ->whereDate('used_on', now()->toDateString())
            ->get();
        $servings = DB::table('poultry_feed_servings')
            ->where('poultry_house_id', $selectedHouse->id)
            ->whereDate('fed_on', now()->toDateString())
            ->orderBy('fed_at')->get();

        return view('poultry.daily.feed', [
            'mixedToday' => (float) $existing->where('feed_type', 'mixed-feed')->sum('quantity_kg'),
            'batch'    => $batch,
            'groups'   => $groups,
            'existing' => $existing,
            'houses' => $houses,
            'houseChoices' => $this->houseChoicesFor($request, $batch),
            'selectedHouse' => $selectedHouse,
            'servings' => $servings,
        ]);
    }

    /**
     * A poultry attendant records one figure, like deaths: the mixed feed given to their house today.
     * Saving again the same day replaces the figure. It goes to the supervisor for checking.
     */
    public function feedGivenStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('feed-consumption', 'create'), 403);
        $data = $request->validate([
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
            'quantity_kg' => ['required', 'numeric', 'min:0.1', 'max:20000'],
            'worker_note' => ['nullable', 'string', 'max:500'],
        ], ['quantity_kg.min' => 'Enter how many kilograms of feed you gave.']);
        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');
        $type = FeedType::where('code', 'mixed-feed')->firstOrFail();

        DB::transaction(function () use ($batch, $house, $data, $type, $request) {
            \App\Support\MixedFeedStock::giveToHouse($house, (float) $data['quantity_kg'], $request->user()->id);
            FeedConsumption::where('animal_group_id', $batch->id)->where('poultry_house_id', $house->id)
                ->where('feed_type', 'mixed-feed')->whereDate('used_on', now()->toDateString())->where('status', '!=', 'approved')->delete();
            FeedConsumption::create([
                'animal_group_id' => $batch->id, 'poultry_house_id' => $house->id, 'department_id' => $batch->department_id,
                'feed_type_id' => $type->id, 'feed_type' => $type->code, 'used_on' => now()->toDateString(),
                'bags' => 0, 'kg_per_bag' => 0, 'quantity_kg' => $data['quantity_kg'],
                'recorded_by' => $request->user()->id, 'recorded_at' => now(), 'worker_note' => $data['worker_note'] ?? null, 'status' => 'submitted',
            ]);
        });
        AuditLog::record('created', $batch, 'Recorded '.rtrim(rtrim(number_format((float) $data['quantity_kg'], 1), '0'), '.')." kg of mixed feed given to {$house->house_number}.", null, null, 'feed-consumption');

        return redirect()->route('daily.index', ['house' => $house->id])->with('status', 'Feed for '.$house->house_number.' saved.');
    }

    public function feedServingStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('feed-consumption', 'create'), 403);
        $data = $request->validate([
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
            'fed_at' => ['required', 'date_format:H:i'],
            'quantity_kg' => ['required', 'numeric', 'min:0.01', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');

        DB::table('poultry_feed_servings')->insert([
            'animal_group_id' => $batch->id,
            'poultry_house_id' => $house->id,
            'fed_on' => now()->toDateString(),
            'fed_at' => $data['fed_at'],
            'quantity_kg' => $data['quantity_kg'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('daily.feed', ['batch' => $batch, 'house' => $house->id])
            ->with('status', 'Feed given to '.$house->house_number.' recorded. You can add another feeding time.');
    }

    public function feedStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('feed-consumption', 'create'), 403);

        $data = $request->validate([
            'use'         => ['array'],
            'bags'        => ['array'],
            'bags.*'      => ['nullable', 'integer', 'min:0', 'max:999'],
            'kg'          => ['array'],
            'kg.*'        => ['nullable', 'integer', 'min:1', 'max:500'],
            'loose'       => ['array'],
            'loose_kg'    => ['array'],
            'loose_kg.*'  => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'worker_note' => ['nullable', 'string', 'max:500'],
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
        ]);

        $house = $this->feedHousesFor($request, $batch)->firstWhere('id', (int) $data['poultry_house_id']);
        abort_unless($house, 403, 'This poultry house is not assigned to you or does not contain this batch.');

        $chosen = array_keys($data['use'] ?? []);

        if (! $chosen) {
            return back()->withInput()->withErrors([
                'use' => 'Tap the feed you used before saving.',
            ]);
        }

        $types   = FeedType::whereIn('id', $chosen)->get()->keyBy('id');
        $lines   = [];
        $totalKg = 0;

        foreach ($chosen as $id) {
            $type = $types->get($id);

            if (! $type) {
                continue;
            }

            $isLoose = ! empty($data['loose'][$id]);

            if ($isLoose) {
                /*
                 | Weighed loose. Bags stays 0, which is also how the form
                 | recognises a loose entry when it reopens.
                 */
                $kg = (float) ($data['loose_kg'][$id] ?? 0);

                if ($kg < 0.01) {
                    return back()->withInput()->withErrors([
                        'loose_kg' => "You chose {$type->name} but did not say how many kilograms.",
                    ]);
                }

                $lines[] = [
                    'type'       => $type,
                    'bags'       => 0,
                    'kg_per_bag' => 0,
                    'quantity'   => $kg,
                ];
            } else {
                $bags   = (int) ($data['bags'][$id] ?? 0);
                $perBag = (int) ($data['kg'][$id] ?? $type->default_kg_per_bag);

                if ($bags < 1) {
                    return back()->withInput()->withErrors([
                        'bags' => "You chose {$type->name} but did not say how many bags.",
                    ]);
                }

                $kg = $bags * $perBag;

                $lines[] = [
                    'type'       => $type,
                    'bags'       => $bags,
                    'kg_per_bag' => $perBag,
                    'quantity'   => $kg,
                ];
            }

            $totalKg += $kg;
        }

        DB::transaction(function () use ($batch, $house, $lines, $data, $request) {

            /*
             | Replace today's entries rather than adding to them. Someone
             | correcting a figure taps the same buttons again, and what
             | they see on screen is what ends up stored.
             */
            FeedConsumption::where('animal_group_id', $batch->id)
                ->where('poultry_house_id', $house->id)
                ->whereDate('used_on', now()->toDateString())
                ->delete();
            $stockReference = 'poultry-feed:'.$batch->id.':house-'.$house->id.':'.now()->toDateString();
            DB::table('feed_stock_movements')->where('reference', $stockReference)->delete();

            foreach ($lines as $line) {
                FeedConsumption::create([
                    'animal_group_id' => $batch->id,
                    'poultry_house_id' => $house->id,
                    'department_id'   => $batch->department_id,
                    'feed_type_id'    => $line['type']->id,
                    'feed_type'       => $line['type']->code,
                    'used_on'         => now()->toDateString(),
                    'bags'            => $line['bags'],
                    'kg_per_bag'      => $line['kg_per_bag'],
                    'quantity_kg'     => $line['quantity'],
                    'recorded_by'     => $request->user()->id,
                    'recorded_at'     => now(),
                    'worker_note'     => $data['worker_note'] ?? null,
                    'status'          => 'submitted',
                ]);
                app(FeedStockService::class)->issue([
                    'unit_code'=>'poultry','movement_date'=>now()->toDateString(),'movement_type'=>'consumed',
                    'feed_name'=>$line['type']->name,'quantity_kg'=>$line['quantity'],'reference'=>$stockReference,
                    'notes'=>$data['worker_note']??null,'recorded_by'=>$request->user()->id,
                ]);
            }
        });

        AuditLog::record(
            'created', $batch,
            "Recorded " . round($totalKg) . " kg of ingredients taken for mixing for {$batch->code} across "
                . count($lines) . ' ' . (count($lines) === 1 ? 'feed' : 'feeds') . '.',
            null, null, 'feed-consumption'
        );

        $what = count($lines) === 1
            ? $lines[0]['type']->name . ' (' . round($totalKg) . ' kg)'
            : count($lines) . ' feeds (' . round($totalKg) . ' kg)';
        app(\App\Services\CommunicationService::class)->recordAwaitingReview('poultry', now(), 'Feed for '.$batch->code.': '.$what, $request->user()->id);

        return redirect()
            ->route('daily.index')
            ->with('done', ['what' => $what, 'where' => $batch->code]);
    }

    /* ---------------------------------------------------------------- */

    protected function feedHousesFor(Request $request, AnimalGroup $batch)
    {
        $query = PoultryHouse::where('animal_group_id', $batch->id)->where('is_active', true);
        $user = $request->user();

        if ($user->isDataEntryOnly()) {
            $assignedHouseIds = PoultryHouseAssignment::where('employee_id', $user->employee_id)
                ->whereNull('assigned_until')->pluck('poultry_house_id');
            $query->whereIn('id', $assignedHouseIds);
        }

        return $query->orderBy('house_number')->get();
    }

    protected function houseChoicesFor(Request $request, AnimalGroup $batch)
    {
        $user = $request->user();
        if (! $user->isDataEntryOnly()) {
            return $this->feedHousesFor($request, $batch);
        }

        $assignedHouseIds = PoultryHouseAssignment::where('employee_id', $user->employee_id)
            ->whereNull('assigned_until')->pluck('poultry_house_id');

        return PoultryHouse::whereIn('id', $assignedHouseIds)
            ->where('is_active', true)->whereNotNull('animal_group_id')
            ->orderBy('house_number')->get();
    }

    protected function assertVisible(Request $request, AnimalGroup $batch): void
    {
        $user = $request->user();

        if ($user->isSuperuser()) {
            return;
        }

        if ($user->isDataEntryOnly()) {
            abort_unless($user->employee_id, 403, 'Your login is not linked to a worker record.');

            $isAssigned = PoultryHouseAssignment::where('employee_id', $user->employee_id)
                ->whereNull('assigned_until')
                ->whereHas('house', fn ($query) => $query
                    ->where('animal_group_id', $batch->id)
                    ->where('is_active', true))
                ->exists();

            abort_unless($isAssigned, 403, 'This poultry house is not assigned to you.');

            return;
        }

        abort_unless(
            in_array($batch->department_id, $user->departmentIds(), true),
            403,
            'This flock belongs to a section you are not posted to.'
        );
    }
}
