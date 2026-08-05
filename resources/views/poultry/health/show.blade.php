@extends('layouts.app')

@section('title', 'Health — ' . $batch->code)

@section('content')

<div class="page-head page-head--split">
    <div>
        <h2>{{ $batch->name ?: $batch->code }}</h2>
        <p>{{ $batch->house ?: $batch->code }} · {{ $batch->ageInWeeks() }} weeks · {{ number_format($batch->current_quantity) }} birds</p>
    </div>

    @if ($user->canDo('health', 'create'))
        <a class="btn btn--inline" href="{{ route('health.create', ['batch' => $batch->id]) }}">Record something</a>
    @endif
</div>

@if ($safeFrom)
    <div class="withdrawal-banner">
        <p class="withdrawal-title">Do not sell eggs from this flock</p>
        <div class="withdrawal-row">
            <div>
                <strong>Safe from {{ $safeFrom->format('l j F') }}</strong>
                <span>Eggs collected before then may be eaten on the farm but not sold.</span>
            </div>
            <div class="withdrawal-when">
                <span class="figure figure--alarm">{{ $daysLeft }}</span>
                <span class="figure-unit">{{ Str::plural('day', $daysLeft) }} left</span>
            </div>
        </div>
    </div>
@endif

@if ($due->isNotEmpty())
    <div class="card">
        <p class="card-label">Due for this flock</p>

        @foreach ($due as $item)
            <a class="attention-row"
               href="{{ route('health.create', ['batch' => $batch->id, 'medication' => $item['medication']->id]) }}">
                <span>
                    <strong>{{ $item['medication']->name }}</strong>
                    <em>{{ $item['medication']->routeLabel() }} · {{ $item['plan']->ageLabel() }}</em>
                </span>
                <span class="attention-why {{ $item['overdue'] ? '' : 'attention-why--soon' }}">
                    @if ($item['overdue'])
                        {{ $item['days_late'] }} days late
                    @elseif ($item['days_until'] === 0)
                        today
                    @else
                        {{ $item['due_date']->format('j M') }}
                    @endif
                </span>
            </a>
        @endforeach
    </div>
@endif

<h3 class="section-heading">History</h3>

@if ($events->isEmpty())
    <div class="card">
        <p class="muted" style="margin:0;">Nothing recorded for this flock yet.</p>
    </div>
@else
    <div class="timeline">
        @foreach ($events as $e)
            <div class="tl-item">
                <div class="tl-date">
                    <strong>{{ $e->occurred_on->format('j M') }}</strong>
                    <span>{{ $e->occurred_on->format('Y') }}</span>
                </div>

                <div class="tl-body">
                    <strong>{{ $e->medication?->name ?? $e->typeLabel() }}</strong>
                    <span class="tl-kind">{{ $e->typeLabel() }}</span>

                    <div class="tl-facts">
                        @if ($e->medication?->route)
                            <span>{{ $e->medication->routeLabel() }}</span>
                        @endif
                        @if ($e->birds_treated)
                            <span>{{ number_format($e->birds_treated) }} birds</span>
                        @endif
                        @if ($e->days_of_course > 1)
                            <span>{{ $e->days_of_course }}-day course</span>
                        @endif
                        @if ($e->dose_given)
                            <span>{{ $e->dose_given }}</span>
                        @endif
                        @if ($e->cost > 0)
                            <span>{{ number_format((float) $e->cost) }} UGX</span>
                        @endif
                    </div>

                    @if ($e->reason)
                        <p class="tl-note"><strong>Why:</strong> {{ $e->reason }}</p>
                    @endif

                    @if ($e->findings)
                        <p class="tl-note">{{ $e->findings }}</p>
                    @endif

                    @if ($e->withdrawal_ends_eggs)
                        <p class="tl-withdrawal {{ $e->holdsEggs() ? 'tl-withdrawal--active' : '' }}">
                            Eggs held until {{ $e->withdrawal_ends_eggs->format('j M Y') }}
                            {{ $e->holdsEggs() ? '— still in force' : '— now clear' }}
                        </p>
                    @endif

                    <p class="tl-who">
                        {{ $e->whoGave() }}
                        @if ($e->vet_involved) · vet involved @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@endif

<p style="margin-top:18px;">
    <a class="btn btn--quiet btn--inline" href="{{ route('health.index') }}">Back to health</a>
</p>

@endsection
