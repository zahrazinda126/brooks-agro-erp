@extends('layouts.app')

@section('title', 'Collections')

@section('content')

{{--
    What was recorded, by whom, and when.

    Every record already carried the person and the exact moment it was
    typed — they were captured automatically from the signed-in account and
    never shown to anybody. This is the screen that shows them.

    Two times are given side by side because they answer different
    questions:

      Collected  what the worker says happened, and when
      Recorded   what the system observed, and cannot be edited

    A gap between them is not suspicious in itself — signal is poor in the
    houses and people enter at the end of a round. A large or unusual gap
    is worth a question, which is why it is visible rather than buried.
--}}

<div class="page-head page-head--split">
    <div>
        <h2>Egg collections</h2>
        <p>{{ $collections->total() }} {{ Str::plural('record', $collections->total()) }}</p>
    </div>
</div>

<form method="GET" action="{{ route('collections.index') }}" class="card filters">
    <div class="field">
        <label for="from">From</label>
        <input id="from" name="from" type="date" value="{{ $filters['from'] }}">
    </div>

    <div class="field">
        <label for="to">To</label>
        <input id="to" name="to" type="date" value="{{ $filters['to'] }}">
    </div>

    <div class="field">
        <label for="batch">Flock</label>
        <select id="batch" name="batch">
            <option value="">All flocks</option>
            @foreach ($batches as $b)
                <option value="{{ $b->id }}" @selected((int) $filters['batch'] === $b->id)>
                    {{ $b->code }} — {{ $b->name ?: $b->house }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field field--actions">
        <button type="submit" class="btn btn--inline">Apply</button>
    </div>
</form>

<div class="grid">
    <div class="card">
        <p class="card-label">Eggs in range</p>
        <span class="figure">{{ number_format($totals['eggs']) }}</span>
        <span class="figure-unit">{{ number_format($totals['eggs'] / 30, 1) }} trays</span>
    </div>

    <div class="card">
        <p class="card-label">Records</p>
        <span class="figure">{{ number_format($totals['records']) }}</span>
        <span class="figure-unit">Collections</span>
    </div>

    <div class="card">
        <p class="card-label">People recording</p>
        <span class="figure">{{ $totals['people'] }}</span>
        <span class="figure-unit">Different workers</span>
    </div>

    <div class="card">
        <p class="card-label">Entered late</p>
        <span class="figure {{ $totals['late'] > 0 ? 'figure--alarm' : '' }}">{{ $totals['late'] }}</span>
        <span class="figure-unit">More than 6 hours after</span>
    </div>
</div>

@if ($collections->isEmpty())
    <div class="card" style="margin-top:12px;">
        <p class="card-label">Nothing recorded</p>
        <p class="muted" style="margin:0;">
            No collections in this range.
        </p>
    </div>
@else
    <div class="collection-list">
        @foreach ($collections as $c)
            @php
                $collectedAt = $c->collected_at
                    ? \Illuminate\Support\Carbon::parse($c->collected_on->format('Y-m-d') . ' ' . $c->collected_at)
                    : null;

                // How long after collecting was it typed in?
                $gapHours = $collectedAt
                    ? round($collectedAt->diffInMinutes($c->recorded_at, false) / 60, 1)
                    : null;
            @endphp

            <div class="collection {{ ($gapHours !== null && $gapHours > 6) ? 'collection--late' : '' }}">

                <div class="collection-head">
                    <div>
                        <strong>{{ $c->batch->name ?: $c->batch->code }}</strong>
                        <span>{{ ucfirst($c->session) }} · {{ $c->collected_on->format('D j M Y') }}</span>
                    </div>

                    <div class="collection-total">
                        <span class="figure">{{ number_format($c->total_eggs) }}</span>
                        <span class="figure-unit">
                            eggs
                            @if ($c->layPercent() !== null)
                                · {{ $c->layPercent() }}%
                            @endif
                        </span>
                    </div>
                </div>

                {{-- The grade breakdown. --}}
                @if ($c->lines->isNotEmpty())
                    <div class="collection-grades">
                        @foreach ($c->lines as $line)
                            <span class="grade-chip {{ $line->grade->is_defect ? 'grade-chip--defect' : '' }}">
                                {{ $line->grade->name }}
                                <strong>{{ number_format($line->total_eggs) }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Collection, recording, and approval audit times. --}}
                <div class="collection-times">
                    <div>
                        <span class="time-label">Collected</span>
                        <strong>
                            {{ $collectedAt ? $collectedAt->format('H:i') : '—' }}
                        </strong>
                        <em>as stated by the worker</em>
                    </div>

                    <div>
                        <span class="time-label">Recorded</span>
                        <strong>{{ $c->recorded_at->format('H:i') }}</strong>
                        <em>
                            {{ $c->recorded_at->format('j M') }}
                            @if ($gapHours !== null && $gapHours >= 0.5)
                                · {{ $gapHours }}h later
                            @endif
                        </em>
                    </div>

                    <div>
                        <span class="time-label">By</span>
                        <strong>{{ $c->recorder->displayName() }}</strong>
                        <em>{{ $c->recorder->username }}</em>
                    </div>

                    <div>
                        <span class="time-label">Supervisor approved</span>
                        <strong>{{ $c->approved_at?->format('H:i:s') ?? '—' }}</strong>
                        <em>{{ $c->approved_at?->format('j M Y') ?? 'Not approved' }}{{ $c->approver ? ' · '.$c->approver->displayName() : '' }}</em>
                    </div>
                </div>

                @if ($c->worker_note)
                    <p class="collection-note">“{{ $c->worker_note }}”</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="pager">{{ $collections->links() }}</div>
@endif

<div class="card" style="margin-top:18px;">
    <p class="card-label">About these times</p>
    <p class="muted" style="margin:0;">
        <strong>Collected</strong> is what the worker entered — their statement
        of when they did the round. <strong>Recorded</strong> is when the form
        was submitted, taken from the system rather than typed, so it cannot be
        adjusted. <strong>Supervisor approved</strong> is the exact system time
        when the supervisor accepted the entry and identifies who approved it.
        <br><br>
        A gap between them is normal — signal is poor in the houses and people
        often enter at the end of a round. A large or repeated gap is worth
        asking about, which is why it is shown rather than buried.
    </p>
</div>

@endsection
