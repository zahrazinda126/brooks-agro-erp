@extends('layouts.app')

@section('title', 'Batches')

@section('content')

    <div class="page-head page-head--split">
        <div>
            <h2>Batches</h2>
            <p>{{ number_format($totals['birds']) }} birds across {{ $totals['batches'] }} active {{ Str::plural('batch', $totals['batches']) }}</p>
        </div>

        @if ($user->canDo('batches', 'create'))
            <a class="btn btn--inline" href="{{ route('batches.create') }}">New batch</a>
        @endif
    </div>

    <form method="GET" action="{{ route('batches.index') }}" class="card filters">
        <div class="field">
            <label for="q">Search</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}"
                   placeholder="Code, name or house">
        </div>

        <div class="field">
            <label for="department">Section</label>
            <select id="department" name="department">
                <option value="">All sections</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected((int) $filters['department'] === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="closed" @selected($filters['status'] === 'closed')>Closed</option>
                <option value="all" @selected($filters['status'] === 'all')>All</option>
            </select>
        </div>
        <div class="field"><label for="from">Received from</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}"></div>
        <div class="field"><label for="to">Received to</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}"></div>

        <div class="field field--actions">
            <button type="submit" class="btn btn--inline">Apply</button>
        </div>
    </form>

    @if ($batches->isEmpty())
        <div class="card">
            <p class="card-label">No batches</p>
            <p class="muted" style="margin:0 0 14px;">
                @if ($filters['q'] || $filters['department'])
                    Nothing matches those filters.
                @else
                    No batches have been opened yet. Start one when your next delivery of birds arrives.
                @endif
            </p>
            @if ($user->canDo('batches', 'create'))
                <a class="btn btn--inline" href="{{ route('batches.create') }}">New batch</a>
            @endif
        </div>
    @else
        <div class="batch-list">
            @foreach ($batches as $batch)
                @php
                    $weeks = $batch->ageInWeeks();
                @endphp
                <a class="batch-card" href="{{ route('batches.show', $batch) }}">

                    <div class="batch-card-head">
                        <div>
                            <strong class="mono">{{ $batch->code }}</strong>
                            @if ($batch->name)
                                <span class="batch-name">{{ $batch->name }}</span>
                            @endif
                        </div>
                        <span class="stage stage--{{ Str::slug($batch->stage()) }}">{{ $batch->stage() }}</span>
                    </div>

                    <div class="batch-figures">
                        <div>
                            <span class="figure">{{ number_format($batch->current_quantity) }}</span>
                            <span class="figure-unit">Birds alive</span>
                        </div>
                        <div>
                            <span class="figure">{{ $weeks }}</span>
                            <span class="figure-unit">Weeks old</span>
                        </div>
                        <div>
                            <span class="figure {{ $batch->mortalityIsHigh() ? 'figure--alarm' : '' }}">{{ $batch->mortalityRate() }}%</span>
                            <span class="figure-unit">Mortality</span>
                        </div>
                    </div>

                    <p class="batch-meta">
                        {{ $batch->breed?->name ?? 'Breed not stated' }}
                        @if ($batch->house) · {{ $batch->house }} @endif
                        · {{ $batch->department?->name }}
                    </p>

                    @if ($batch->layingIsOverdue())
                        <p class="flag flag--alarm">
                            Should have started laying {{ abs($batch->weeksToLay()) }} weeks ago.
                        </p>
                    @elseif ($batch->isActive() && ! $batch->shouldBeLaying() && $batch->weeksToLay() !== null)
                        <p class="flag">
                            First eggs expected in {{ $batch->weeksToLay() }} {{ Str::plural('week', $batch->weeksToLay()) }}
                            @if ($batch->expected_lay_date)
                                — around {{ $batch->expected_lay_date->format('j M') }}
                            @endif
                        </p>
                    @elseif ($batch->mortalityIsHigh())
                        <p class="flag flag--alarm">
                            Mortality is high for a flock of this age.
                        </p>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="pager">{{ $batches->links() }}</div>
    @endif

@endsection
