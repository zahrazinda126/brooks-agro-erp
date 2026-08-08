<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Models\EggCollection;
use App\Models\EggCollectionLine;
use App\Models\EggGrade;
use App\Models\FeedConsumption;
use App\Models\FeedType;
use App\Models\MortalityRecord;
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

        // What has already been done today, so the screen can say so
        // rather than letting someone enter it twice.
        $doneEggs = EggCollection::whereDate('collected_on', $today)
            ->whereIn('animal_group_id', $batches->pluck('id'))
            ->pluck('animal_group_id')
            ->unique();

        $doneFeed = FeedConsumption::whereDate('used_on', $today)
            ->whereIn('animal_group_id', $batches->pluck('id'))
            ->pluck('animal_group_id')
            ->unique();
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
            'doneEggs' => $doneEggs,
            'doneFeed' => $doneFeed,
            'livestockGroups'=>$livestockGroups,
            'dailyLivestock'=>$dailyLivestock,
            'gardenActivity'=>$gardenActivity,
            'productionDraftTotal'=>$productionDraftTotal,
            'visibleFarmUnits'=>collect(['poultry','goats','rabbits','dogs','garden'])->filter(fn ($unit) => $user->canAccessFarmUnit($unit))->values(),
            'showProductionPlanning'=>! $user->isDataEntryOnly() && $user->canAccess('budget'),
            'feedBalances'=>$feedBalances,
        ]);
    }

    /* ================================================================
     | EGGS
     | ================================================================ */

    public function eggsForm(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        /*
         | Only the sizes this flock's age makes likely, plus the defect
         | grades. A 20-week flock is not laying extra large, and offering
         | the option invites a mis-tap that later reads as real data.
         */
        $likely = $batch->expectedGrades();

        $defects = EggGrade::where('is_defect', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // If the flock is outside any normal window, show everything
        // rather than nothing.
        if ($likely->isEmpty()) {
            $likely = EggGrade::where('is_defect', false)
                ->where('is_active', true)->orderBy('sort_order')->get();
        }

        $existing = EggCollection::with('lines')
            ->where('animal_group_id', $batch->id)
            ->whereDate('collected_on', now()->toDateString())
            ->where('session', $request->query('session', 'morning'))
            ->first();

        return view('poultry.daily.eggs', [
            'batch'    => $batch,
            'likely'   => $likely,
            'defects'  => $defects,
            'session'  => $request->query('session', 'morning'),
            'existing' => $existing,
        ]);
    }

    public function eggsStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('egg-entry', 'create'), 403);

        $data = $request->validate([
            'session'     => ['required', 'in:morning,midday,evening'],
            'collected_at' => ['required', 'date_format:H:i'],
            'trays'       => ['array'],
            'trays.*'     => ['nullable', 'integer', 'min:0', 'max:2000'],
            'loose'       => ['array'],
            'loose.*'     => ['nullable', 'integer', 'min:0', 'max:29'],
            'worker_note' => ['nullable', 'string', 'max:500'],
        ]);

        $grades = EggGrade::where('is_active', true)->get()->keyBy('id');
        $total  = 0;
        $lines  = [];

        foreach ($grades as $id => $grade) {
            $trays = (int) ($data['trays'][$id] ?? 0);
            $loose = (int) ($data['loose'][$id] ?? 0);

            if ($trays === 0 && $loose === 0) {
                continue;
            }

            $eggs   = ($trays * $grade->eggs_per_tray) + $loose;
            $total += $eggs;

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

        DB::transaction(function () use ($batch, $data, $lines, $total, $request) {

            $collection = EggCollection::updateOrCreate(
                [
                    'animal_group_id' => $batch->id,
                    'collected_on'    => now()->toDateString(),
                    'session'         => $data['session'],
                ],
                [
                    'department_id' => $batch->department_id,
                    // What the worker says. recorded_at, set below, is what
                    // the system saw — the two are different facts.
                    'collected_at'  => $data['collected_at'],
                    'total_eggs'    => $total,
                    'recorded_by'   => $request->user()->id,
                    'recorded_at'   => now(),
                    'worker_note'   => $data['worker_note'] ?? null,
                    'status'        => 'submitted',
                ]
            );

            $collection->lines()->delete();

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

        return redirect()
            ->route('daily.index')
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

        return view('poultry.daily.deaths', ['batch' => $batch]);
    }

    public function deathsStore(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('mortality-entry', 'create'), 403);

        $data = $request->validate([
            'quantity_died'   => ['required', 'integer', 'min:0', 'max:100000'],
            'quantity_culled' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'worker_note'     => ['nullable', 'string', 'max:500'],
        ], [
            'quantity_died.required' => 'Enter how many birds died. Enter 0 if none died.',
        ]);

        $died   = (int) $data['quantity_died'];
        $culled = (int) ($data['quantity_culled'] ?? 0);
        $total  = $died + $culled;

        if ($total === 0) {
            return redirect()
                ->route('daily.index')
                ->with('done', ['what' => 'No deaths', 'where' => $batch->code]);
        }

        // Cannot lose more birds than are alive.
        if ($total > $batch->current_quantity) {
            return back()->withInput()->withErrors([
                'quantity_died' => "There are only " . number_format($batch->current_quantity)
                    . " birds in {$batch->code}. Check the number.",
            ]);
        }

        DB::transaction(function () use ($batch, $died, $culled, $total, $data, $request) {

            $record = MortalityRecord::create([
                'animal_group_id' => $batch->id,
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

            AuditLog::record('created', $record,
                "Recorded {$total} birds lost from {$batch->code}.", null, null, 'mortality-entry');
        });

        return redirect()
            ->route('daily.index')
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
        $groups = FeedType::active()
            ->get()
            ->groupBy('category')
            ->sortBy(fn ($g, $k) => array_search($k, ['complete', 'energy', 'protein', 'mineral', 'supplement']));

        // Today's entries, so the form comes back filled in rather than
        // making someone start again to correct one number.
        $existing = FeedConsumption::where('animal_group_id', $batch->id)
            ->whereDate('used_on', now()->toDateString())
            ->get();

        return view('poultry.daily.feed', [
            'batch'    => $batch,
            'groups'   => $groups,
            'existing' => $existing,
        ]);
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
        ]);

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

        DB::transaction(function () use ($batch, $lines, $data, $request) {

            /*
             | Replace today's entries rather than adding to them. Someone
             | correcting a figure taps the same buttons again, and what
             | they see on screen is what ends up stored.
             */
            FeedConsumption::where('animal_group_id', $batch->id)
                ->whereDate('used_on', now()->toDateString())
                ->delete();
            $stockReference = 'poultry-feed:'.$batch->id.':'.now()->toDateString();
            DB::table('feed_stock_movements')->where('reference', $stockReference)->delete();

            foreach ($lines as $line) {
                FeedConsumption::create([
                    'animal_group_id' => $batch->id,
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
                DB::table('feed_stock_movements')->insert([
                    'unit_code'=>'poultry','movement_date'=>now()->toDateString(),'movement_type'=>'consumed',
                    'feed_name'=>$line['type']->name,'quantity_kg'=>$line['quantity'],'reference'=>$stockReference,
                    'notes'=>$data['worker_note']??null,'recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        });

        AuditLog::record(
            'created', $batch,
            "Recorded " . round($totalKg) . " kg of feed for {$batch->code} across "
                . count($lines) . ' ' . (count($lines) === 1 ? 'feed' : 'feeds') . '.',
            null, null, 'feed-consumption'
        );

        $what = count($lines) === 1
            ? $lines[0]['type']->name . ' (' . round($totalKg) . ' kg)'
            : count($lines) . ' feeds (' . round($totalKg) . ' kg)';

        return redirect()
            ->route('daily.index')
            ->with('done', ['what' => $what, 'where' => $batch->code]);
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
