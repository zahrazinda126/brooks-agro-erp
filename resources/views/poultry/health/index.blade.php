@extends('layouts.app')

@section('title', 'Flock Health')

@section('content')

<div class="page-head page-head--split">
    <div>
        <h2>Flock health</h2>
        <p>Vaccinations, treatments, and which eggs must not be sold.</p>
    </div>

    @if ($user->canDo('health', 'create'))
        <a class="btn btn--inline" href="{{ route('health.create') }}">Record something</a>
    @endif
</div>

{{--
    Withdrawal first, above everything.

    A missed vaccination costs a flock. Selling eggs during withdrawal
    costs the farm its name in the market and breaks the law. So it is not
    a card among cards — it is a banner, and it is red.
--}}
@if ($withdrawal->isNotEmpty())
    <div class="withdrawal-banner">
        <p class="withdrawal-title">Do not sell these eggs</p>

        @foreach ($withdrawal as $w)
            <div class="withdrawal-row">
                <div>
                    <strong>{{ $w['batch']->name ?: $w['batch']->code }}</strong>
                    <span>{{ $w['batch']->house ?: $w['batch']->code }}</span>
                </div>
                <div class="withdrawal-when">
                    <span class="figure figure--alarm">{{ $w['days_left'] }}</span>
                    <span class="figure-unit">
                        {{ Str::plural('day', $w['days_left']) }} left ·
                        safe from {{ $w['until']->format('j M') }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($overdue->isNotEmpty())
    <div class="card card--alarm">
        <p class="card-label">Overdue</p>

        @foreach ($overdue as $item)
            <a class="attention-row" href="{{ route('health.create', ['batch' => $item['batch']->id, 'medication' => $item['medication']->id]) }}">
                <span>
                    <strong>{{ $item['medication']->name }}</strong>
                    <em>{{ $item['batch']->code }} · was due {{ $item['plan']->ageLabel() }}</em>
                </span>
                <span class="attention-why">{{ $item['days_late'] }} days late</span>
            </a>
        @endforeach
    </div>
@endif

@if ($due->isNotEmpty())
    <div class="card">
        <p class="card-label">Coming up</p>

        @foreach ($due as $item)
            <a class="attention-row" href="{{ route('health.create', ['batch' => $item['batch']->id, 'medication' => $item['medication']->id]) }}">
                <span>
                    <strong>{{ $item['medication']->name }}</strong>
                    <em>
                        {{ $item['batch']->code }} ·
                        {{ $item['medication']->routeLabel() }}
                    </em>
                </span>
                <span class="attention-why attention-why--soon">
                    @if ($item['days_until'] === 0)
                        today
                    @elseif ($item['days_until'] < 0)
                        {{ abs($item['days_until']) }} days ago
                    @else
                        in {{ $item['days_until'] }} {{ Str::plural('day', $item['days_until']) }}
                    @endif
                </span>
            </a>
        @endforeach
    </div>
@endif

@if ($overdue->isEmpty() && $due->isEmpty() && $withdrawal->isEmpty())
    <div class="card">
        <p class="card-label">Nothing outstanding</p>
        <p class="muted" style="margin:0;">
            No vaccinations due, nothing overdue, and no flock is under
            withdrawal.
        </p>
    </div>
@endif

<h3 class="section-heading">Flocks</h3>

<div class="batch-list">
    @foreach ($batches as $b)
        @php
            $held = $withdrawal->firstWhere('batch.id', $b->id);
        @endphp
        <a class="batch-card {{ $held ? 'batch-card--held' : '' }}" href="{{ route('health.show', $b) }}">
            <div class="batch-card-head">
                <div>
                    <strong class="mono">{{ $b->code }}</strong>
                    @if ($b->name)<span class="batch-name">{{ $b->name }}</span>@endif
                </div>
                @if ($held)
                    <span class="stage" style="background:var(--danger-100);color:var(--danger-600);">
                        Eggs held
                    </span>
                @endif
            </div>

            <div class="batch-figures">
                <div>
                    <span class="figure">{{ number_format($b->current_quantity) }}</span>
                    <span class="figure-unit">Birds</span>
                </div>
                <div>
                    <span class="figure">{{ $b->ageInWeeks() }}</span>
                    <span class="figure-unit">Weeks</span>
                </div>
                <div>
                    <span class="figure {{ $b->mortalityIsHigh() ? 'figure--alarm' : '' }}">{{ $b->mortalityRate() }}%</span>
                    <span class="figure-unit">Mortality</span>
                </div>
            </div>
        </a>
    @endforeach
</div>

@if ($recent->isNotEmpty())
    <h3 class="section-heading">Recently recorded</h3>

    <div class="card">
        @foreach ($recent as $e)
            <div class="done-row">
                <span>
                    <strong>{{ $e->medication?->name ?? $e->typeLabel() }}</strong>
                    {{ $e->batch->code }}
                </span>
                <span class="done-cause">
                    {{ $e->occurred_on->format('j M') }}
                    @if ($e->holdsEggs())
                        <em class="tag-spread">eggs held</em>
                    @endif
                </span>
                <span class="done-who">{{ $e->whoGave() }}</span>
            </div>
        @endforeach
    </div>
@endif

@endsection
