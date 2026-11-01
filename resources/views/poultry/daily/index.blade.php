@extends('layouts.app')

@section('title', 'My Work')

@section('content')

{{--
    A worker's home screen.

    The buttons ARE the cards. There is no container around them, because a
    white box inside a white page is a border doing no work -- it just makes
    the buttons smaller.

    How many appear depends on what this person may do and what this flock
    needs today. Litter is monthly, so it shows only when it falls due; a
    monthly button standing there every day teaches people to tap past
    buttons, and that habit spreads to the daily ones.
--}}

@if (session('done'))
    @php
        $d = session('done');
    @endphp
    <div class="saved">
        <svg viewBox="0 0 24 24" aria-hidden="true" class="saved-tick">
            <circle cx="12" cy="12" r="11" fill="none" stroke="currentColor" stroke-width="2"/>
            <path d="m7 12.5 3.2 3.2L17 9" fill="none" stroke="currentColor"
                  stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div>
            <strong>Saved</strong>
            <span>{{ $d['what'] }} &mdash; {{ $d['where'] }}</span>
            @if (! empty($d['note']))
                <em>{{ $d['note'] }}</em>
            @endif
        </div>
    </div>
@endif

@php
$hour = now()->hour;
$workerGreeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$workerName = $user->firstName();
@endphp
<section class="worker-welcome">
    <div class="worker-welcome__copy">
        <p class="eyebrow">Daily operations</p>
        <h2>{{ $workerGreeting }}, {{ $workerName }}</h2>
        <p>{{ now()->format('l, j F Y') }} &middot; Choose a unit below and record today's work.</p>
    </div>
    <div class="worker-welcome__mark" aria-hidden="true">{{ strtoupper(substr($workerName, 0, 1)) }}</div>
</section>

{{-- Paid tasks the Production Manager has given this worker (shown only while they last). --}}
@php
    $myTasks = \App\Http\Controllers\Operations\FarmTaskController::activeFor(auth()->user());
    $myTaskToday = $myTasks->isEmpty() ? collect() : DB::table('farm_task_entries')->whereIn('farm_task_assignment_id', $myTasks->pluck('id'))->whereDate('done_on', today())->pluck('farm_task_assignment_id');
@endphp
@if($myTasks->isNotEmpty())
<section class="flock"><div class="flock-head"><h3>My tasks</h3><p>Paid tasks given to you. Record the work when you do it.</p></div><div class="jobs">
@foreach($myTasks as $task)
<a class="job {{ $myTaskToday->contains($task->id) ? 'job--done' : '' }}" href="{{ route('my-tasks.record', $task->id) }}"><svg class="job-icon" aria-hidden="true"><use href="#i-clipboard"></use></svg><span class="job-label">{{ $task->task_name }}</span><span class="job-state">{{ $myTaskToday->contains($task->id) ? 'Recorded today - add another' : ($task->measure ? 'Tap to record what you collected' : 'Tap to record what you did') }}</span></a>
@endforeach
</div></section>
@endif

@if($showProductionPlanning)<section class="exec-panel">
    <div class="page-head page-head--split u-m0">
        <div><p class="eyebrow">&#x1F4B0; This week's Finance draft</p><h3>@money($productionDraftTotal)</h3><p>Only Poultry, Goats, Rabbits, Dogs, and Garden requirements.</p></div>
        <a class="btn" href="{{ route('department-budget.index') }}">Open & edit unit budgets</a>
    </div>
</section>@endif

{{-- Unit jump links only when there is more than one unit to jump between. --}}
@if($visibleFarmUnits->count() > 1)<div class="unit-tabs" style="margin-bottom:1.5rem">@if($visibleFarmUnits->contains('poultry'))<a href="#poultry">&#x1F414; Poultry</a>@endif @if($visibleFarmUnits->contains('goats'))<a href="#goats">&#x1F410; Goats</a>@endif @if($visibleFarmUnits->contains('rabbits'))<a href="#rabbits">&#x1F407; Rabbits</a>@endif @if($visibleFarmUnits->contains('dogs'))<a href="#dogs">&#x1F415; Dogs</a>@endif @if($visibleFarmUnits->contains('garden'))<a href="#garden">&#x1F331; Kituntu Garden</a>@endif</div>@endif
@php
    // Farm workers see each button in English and Luganda.
    $say = fn (string $en, string $lg) => $user->isDataEntryOnly() ? $en.' / '.$lg : $en;
@endphp
@if($visibleFarmUnits->contains('poultry'))
<div id="poultry" class="page-head"><h2>&#x1F414; Poultry</h2><p>Eggs, feed, mortality and scheduled flock work.</p></div>

@forelse ($batches as $batch)

    @php
        $feedDone   = $doneFeed->contains($batch->id);
        $needLitter = isset($litterDue) && $litterDue->contains($batch->id);
        $lateLitter = isset($litterLate) && $litterLate->contains($batch->id);

        /*
         | Built as a list rather than written out four times, so adding
         | another job later is one entry here -- not another block of
         | markup to keep in step with the others.
         */
        $jobs = [];

        /*
         | Eggs are always offered.
         |
         | Every earlier version tried to be clever about when a flock
         | "should" be laying and got it wrong in a different way each
         | time. A worker holding an egg needs somewhere to put it, and a
         | prediction has no business standing between them and the record.
         |
         | An unexpected egg at week 14 is information. A worker writing it
         | on paper because the button was hidden is not.
         */
        if ($user->canDo('egg-entry', 'create')) {
            $jobs[] = [
                'label' => $say('🥚 Eggs', 'Amagi'),
                'icon'  => 'egg',
                'url'   => route('daily.eggs', $batch),
                'done'  => false, // collections can be recorded any number of times
                'flag'  => null,
            ];
        }

        if ($user->canDo('mortality-entry', 'create')) {
            $jobs[] = [
                'label' => $say('Deaths', 'Ezifudde'),
                'icon'  => 'dead-hen',
                'url'   => route('daily.deaths', $batch),
                'done'  => false,   // deaths can happen more than once a day
                'flag'  => null,
            ];
        }

        if ($user->canDo('feed-consumption', 'create')) {
            $jobs[] = [
                'label' => $say('Feeds', 'Emmere'),
                'icon'  => 'sack',
                'url'   => route('daily.feed', $batch),
                'done'  => $feedDone,
                'flag'  => null,
            ];
        }

        // Monthly, so it appears only when it falls due.
        if ($needLitter && $user->canDo('litter-removal', 'create')) {
            $jobs[] = [
                'label' => $say('🧹 Litter', 'Kasasiro'),
                'icon'  => 'wrench',
                'url'   => route('daily.litter', $batch),
                'done'  => false,
                'flag'  => $lateLitter ? 'Overdue' : 'Due now',
            ];
        }
    @endphp

    <section class="flock">

        <div class="flock-head">
            <h3>{{ $batch->name ?: $batch->code }}</h3>
            @php
                // The worker's own houses and the birds in them (not the whole batch).
                $workerHouses = ($assignedHouses ?? collect())->where('animal_group_id', $batch->id);
                $workerHouseNames = $workerHouses->pluck('house_number')->map(fn ($name) => str_starts_with(strtolower($name), 'house') ? ucfirst($name) : 'House '.$name);
            @endphp
            <p>{{ $workerHouseNames->isNotEmpty() ? $workerHouseNames->join(' & ') : ($batch->house ?: $batch->code) }} &middot; {{ number_format($workerHouses->isNotEmpty() ? $workerHouses->sum('bird_quantity') : $batch->current_quantity) }} birds</p>
        </div>

        @if (count($jobs))
            <div class="jobs">
                @foreach ($jobs as $job)
                    <a class="job {{ $job['done'] ? 'job--done' : '' }} {{ $job['flag'] === 'Overdue' ? 'job--late' : '' }}"
                       href="{{ $job['url'] }}">

                        @if ($job['flag'])
                            <span class="job-flag">{{ $job['flag'] }}</span>
                        @endif

                        <svg class="job-icon" aria-hidden="true">
                            <use href="#i-{{ $job['icon'] }}"></use>
                        </svg>

                        <span class="job-label">@if(in_array($job['icon'], ['sack', 'dead-hen'], true))<svg class="job-emoji" aria-hidden="true"><use href="#i-{{ $job['icon'] }}-color"></use></svg> @endif{{ $job['label'] }}</span>

                        @if ($job['done'])
                            <span class="job-state">
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="job-tick">
                                    <path d="m5 12.5 4.5 4.5L19 7" fill="none" stroke="currentColor"
                                          stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Done
                            </span>
                        @else
                            <span class="job-state">Tap to enter</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <p class="muted u-m0">
                Nothing to record for this flock today.
            </p>
        @endif

    </section>

@empty

    {{-- The buttons stay on the page; they open once a flock is placed in the worker's house. --}}
    @php
        $waitingJobs = array_filter([
            $user->canDo('egg-entry', 'create') ? [$say('🥚 Eggs', 'Amagi'), 'egg'] : null,
            $user->canDo('mortality-entry', 'create') ? [$say('Deaths', 'Ezifudde'), 'dead-hen'] : null,
            $user->canDo('feed-consumption', 'create') ? [$say('Feeds', 'Emmere'), 'sack'] : null,
        ]);
    @endphp
    <section class="flock">
        <div class="jobs">
            @foreach ($waitingJobs as [$label, $icon])
                <button type="button" class="job" onclick="this.closest('.flock').querySelector('.flock-wait').hidden = false">
                    <svg class="job-icon" aria-hidden="true"><use href="#i-{{ $icon }}"></use></svg>
                    <span class="job-label">@if(in_array($icon, ['sack', 'dead-hen'], true))<svg class="job-emoji" aria-hidden="true"><use href="#i-{{ $icon }}-color"></use></svg> @endif{{ $label }}</span>
                    <span class="job-state">Tap to enter</span>
                </button>
            @endforeach
        </div>
        <div class="notice flock-wait u-mt-12" role="status" hidden>There is no flock in your house yet. Ask your supervisor to put a flock in your house, then record here.</div>
    </section>

@endforelse
@endif

@php
    $unitDetails = [
        'goats' => ['name'=>'Goats','icon'=>"\u{1F410}"],
        'rabbits' => ['name'=>'Rabbits','icon'=>"\u{1F407}"],
        'dogs' => ['name'=>'Dogs','icon'=>"\u{1F415}"],
    ];
    $livestockActions = [
        ['section'=>'feed','label'=>$say('🌾 Daily Feed', 'Emmere'),'icon'=>'grain'],
        ['section'=>'vaccination','label'=>$say('💉 Vaccination', 'Okugema'),'icon'=>'vet'],
        ['section'=>'treatment','label'=>$say('🩺 Daily Health & Treatment', 'Obujjanjabi'),'icon'=>'vet'],
        ['section'=>'mortality','label'=>$say('⚠️ Daily Mortality', 'Ezifudde'),'icon'=>'vet'],
        ['section'=>'production','label'=>$say('🐣 Daily Production & Births', 'Okuzaala'),'icon'=>'egg'],
    ];
@endphp

@foreach($unitDetails as $code=>$detail)
@continue(!$visibleFarmUnits->contains($code))
<div id="{{ $code }}" class="page-head" style="margin-top:2rem"><h2>{{ $detail['icon'] }} {{ $detail['name'] }}</h2><p>Daily records are kept by group or batch, just like poultry flocks.</p></div>
@forelse($livestockGroups->get($code, collect()) as $group)
<section class="flock"><div class="flock-head"><h3>{{ $group->group_code }}</h3><p>{{ $group->breed ?: $detail['name'] }} &middot; {{ number_format($group->current_quantity) }} current</p></div><div class="jobs">
@foreach($livestockActions as $action)
@php $recordedToday = $dailyLivestock[$action['section']]->contains($group->id); @endphp
<a class="job {{ $recordedToday?'job--done':'' }}" href="{{ route('operations.livestock.section',['unit'=>$code,'section'=>$action['section'],'group'=>$group->id,'create'=>1]) }}"><svg class="job-icon" aria-hidden="true"><use href="#i-{{ $action['icon'] }}"></use></svg><span class="job-label">{{ $action['label'] }}</span><span class="job-state">{{ $recordedToday?'Recorded today - add another':'Tap to enter today' }}</span></a>
@endforeach
</div></section>
@empty
@php $groupWord=['goats'=>'herd','rabbits'=>'colony / batch','dogs'=>'pack / litter'][$code]; @endphp
<section class="flock"><div class="flock-head"><h3>{{ $detail['name'] }} daily records</h3><p>Set up the first {{ $groupWord }} as part of the first entry.</p></div><div class="jobs">
@foreach($livestockActions as $action)<a class="job" href="{{ route('operations.livestock.section',['unit'=>$code,'section'=>$action['section'],'create'=>1]) }}"><svg class="job-icon" aria-hidden="true"><use href="#i-{{ $action['icon'] }}"></use></svg><span class="job-label">{{ $action['label'] }}</span><span class="job-state">Open {{ strtolower($action['label']) }}</span></a>@endforeach
</div></section>
@endforelse
@endforeach

@if($visibleFarmUnits->contains('garden'))<div id="garden" class="page-head u-mt-32"><h2>&#x1F331; Kituntu Garden</h2><p>Record inputs, field activity, harvests and crop losses.</p></div>
<section class="flock"><div class="flock-head"><h3>Garden work</h3><p>{{ $gardenActivity }} record{{ $gardenActivity===1?'':'s' }} entered today</p></div><div class="jobs">
@foreach([['view'=>'inputs','label'=>'Inputs & Irrigation','icon'=>'grain'],['view'=>'activity','label'=>'Field Activity','icon'=>'wrench'],['view'=>'production','label'=>'Harvest','icon'=>'egg'],['type'=>'loss','label'=>'Crop Loss','icon'=>'vet']] as $action)
<a class="job" href="{{ route('operations.units.index',array_filter(['unit'=>'garden','view'=>$action['view']??null,'type'=>$action['type']??null])) }}"><svg class="job-icon" aria-hidden="true"><use href="#i-{{ $action['icon'] }}"></use></svg><span class="job-label">{{ $action['label'] }}</span><span class="job-state">Tap to enter</span></a>
@endforeach
</div></section>@endif

@endsection
