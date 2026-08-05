@extends('layouts.app')

@section('title', 'My Work')

@section('content')

{{--
    A worker's home screen.

    The buttons ARE the cards. There is no container around them, because a
    white box inside a white page is a border doing no work — it just makes
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
            <span>{{ $d['what'] }} — {{ $d['where'] }}</span>
            @if (! empty($d['note']))
                <em>{{ $d['note'] }}</em>
            @endif
        </div>
    </div>
@endif

<div class="page-head">
    <h2>{{ now()->format('l') }}</h2>
    <p>{{ now()->format('j F Y') }}</p>
</div>

@forelse ($batches as $batch)

    @php
        $eggsDone   = $doneEggs->contains($batch->id);
        $feedDone   = $doneFeed->contains($batch->id);
        $needLitter = isset($litterDue) && $litterDue->contains($batch->id);
        $lateLitter = isset($litterLate) && $litterLate->contains($batch->id);

        /*
         | Built as a list rather than written out four times, so adding
         | another job later is one entry here — not another block of
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
                'label' => 'Eggs',
                'icon'  => 'egg',
                'url'   => route('daily.eggs', $batch),
                'done'  => $eggsDone,
                'flag'  => null,
            ];
        }

        if ($user->canDo('mortality-entry', 'create')) {
            $jobs[] = [
                'label' => 'Deaths',
                'icon'  => 'vet',
                'url'   => route('daily.deaths', $batch),
                'done'  => false,   // deaths can happen more than once a day
                'flag'  => null,
            ];
        }

        if ($user->canDo('feed-consumption', 'create')) {
            $jobs[] = [
                'label' => 'Feed',
                'icon'  => 'grain',
                'url'   => route('daily.feed', $batch),
                'done'  => $feedDone,
                'flag'  => null,
            ];
        }

        // Monthly, so it appears only when it falls due.
        if ($needLitter && $user->canDo('litter-removal', 'create')) {
            $jobs[] = [
                'label' => 'Litter',
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
            <p>{{ $batch->house ?: $batch->code }} · {{ number_format($batch->current_quantity) }} birds</p>
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

@endsection