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

@if($showProductionPlanning)<section class="exec-panel">
    <div class="page-head page-head--split" style="margin:0">
        <div><p class="eyebrow">&#x1F4B0; This week's Finance draft</p><h3>UGX {{ number_format($productionDraftTotal) }}</h3><p>Only Poultry, Goats, Rabbits, Dogs, and Garden requirements.</p></div>
        <a class="btn" href="{{ route('production.finance-draft.index') }}">Open & edit unit budgets</a>
    </div>
</section>@endif

<div class="unit-tabs" style="margin-bottom:1.5rem">@if($visibleFarmUnits->contains('poultry'))<a href="#poultry">&#x1F414; Poultry</a>@endif @if($visibleFarmUnits->contains('goats'))<a href="#goats">&#x1F410; Goats</a>@endif @if($visibleFarmUnits->contains('rabbits'))<a href="#rabbits">&#x1F407; Rabbits</a>@endif @if($visibleFarmUnits->contains('dogs'))<a href="#dogs">&#x1F415; Dogs</a>@endif @if($visibleFarmUnits->contains('garden'))<a href="#garden">&#x1F331; Kituntu Garden</a>@endif</div>
@if($feedBalances->isNotEmpty())<section class="exec-panel"><div class="page-head page-head--split" style="margin:0"><div><p class="eyebrow">Feed available</p><h3>Feed received for your units</h3></div><a class="btn btn--quiet" href="{{ route('operations.feed-stock.index') }}">View feed register</a></div><div class="production-unit-grid">@foreach($feedBalances as $feed)<article class="production-unit-card"><span>&#x1F33E;</span><div><small>{{ ucfirst($feed->unit_code) }} &middot; {{ $feed->feed_name }}</small><strong>{{ number_format($feed->balance_kg,2) }}</strong><em>kg available</em></div></article>@endforeach</div></section>@endif
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
                'label' => '🥚 Eggs',
                'icon'  => 'egg',
                'url'   => route('daily.eggs', $batch),
                'done'  => false, // collections can be recorded any number of times
                'flag'  => null,
            ];
        }

        if ($user->canDo('mortality-entry', 'create')) {
            $jobs[] = [
                'label' => '⚠️ Deaths',
                'icon'  => 'vet',
                'url'   => route('daily.deaths', $batch),
                'done'  => false,   // deaths can happen more than once a day
                'flag'  => null,
            ];
        }

        if ($user->canDo('feed-consumption', 'create')) {
            $jobs[] = [
                'label' => '🌾 Feed mixing',
                'icon'  => 'grain',
                'url'   => route('daily.feed', $batch),
                'done'  => $feedDone,
                'flag'  => null,
            ];
        }

        // Monthly, so it appears only when it falls due.
        if ($needLitter && $user->canDo('litter-removal', 'create')) {
            $jobs[] = [
                'label' => '🧹 Litter',
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
                $workerHouseNumbers = ($assignedHouses ?? collect())
                    ->where('animal_group_id', $batch->id)
                    ->pluck('house_number');
            @endphp
            <p>{{ $workerHouseNumbers->isNotEmpty() ? 'House '.$workerHouseNumbers->join(' & ') : ($batch->house ?: $batch->code) }} &middot; {{ number_format($batch->current_quantity) }} birds</p>
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

                        <span class="job-label">{{ $job['label'] }}</span>

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
            <p class="muted" style="margin:0;">
                Nothing to record for this flock today.
            </p>
        @endif

    </section>

@empty

    <div class="card">
        <p class="card-label">Nothing to record</p>
        <p class="muted" style="margin:0;">
            There are no flocks in your section yet. Speak to your supervisor.
        </p>
    </div>

@endforelse
@endif

@php
    $unitDetails = [
        'goats' => ['name'=>'Goats','icon'=>"\u{1F410}"],
        'rabbits' => ['name'=>'Rabbits','icon'=>"\u{1F407}"],
        'dogs' => ['name'=>'Dogs','icon'=>"\u{1F415}"],
    ];
    $livestockActions = [
        ['section'=>'feed','label'=>'🌾 Daily Feed','icon'=>'grain'],
        ['section'=>'vaccination','label'=>'💉 Vaccination','icon'=>'vet'],
        ['section'=>'treatment','label'=>'🩺 Daily Health & Treatment','icon'=>'vet'],
        ['section'=>'mortality','label'=>'⚠️ Daily Mortality','icon'=>'vet'],
        ['section'=>'production','label'=>'🐣 Daily Production & Births','icon'=>'egg'],
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

@if($visibleFarmUnits->contains('garden'))<div id="garden" class="page-head" style="margin-top:2rem"><h2>&#x1F331; Kituntu Garden</h2><p>Record inputs, field activity, harvests and crop losses.</p></div>
<section class="flock"><div class="flock-head"><h3>Garden work</h3><p>{{ $gardenActivity }} record{{ $gardenActivity===1?'':'s' }} entered today</p></div><div class="jobs">
@foreach([['view'=>'inputs','label'=>'Inputs & Irrigation','icon'=>'grain'],['view'=>'activity','label'=>'Field Activity','icon'=>'wrench'],['view'=>'production','label'=>'Harvest','icon'=>'egg'],['type'=>'loss','label'=>'Crop Loss','icon'=>'vet']] as $action)
<a class="job" href="{{ route('operations.units.index',array_filter(['unit'=>'garden','view'=>$action['view']??null,'type'=>$action['type']??null])) }}"><svg class="job-icon" aria-hidden="true"><use href="#i-{{ $action['icon'] }}"></use></svg><span class="job-label">{{ $action['label'] }}</span><span class="job-state">Tap to enter</span></a>
@endforeach
</div></section>@endif

@endsection
