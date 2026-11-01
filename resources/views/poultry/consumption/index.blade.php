@extends('layouts.app')

@section('title', 'Birds Used on the Farm')

@section('content')

<div class="page-head page-head--split">
    <div>
        <h2>Birds used on the farm</h2>
        <p>Slaughtered for the farm's own table, not sold.</p>
    </div>

    @if ($user->canDo('bird-consumption', 'create'))
        <a class="btn btn--inline" href="{{ route('consumption.create') }}">Record birds taken</a>
    @endif
</div>

<div class="grid">
    <div class="card">
        <p class="card-label">This month</p>
        <span class="figure">{{ number_format($monthQty) }}</span>
        <span class="figure-unit">Birds</span>
    </div>

    <div class="card">
        <p class="card-label">Value given up</p>
        <span class="figure">{{ number_format((float) $monthValue) }}</span>
        <span class="figure-unit">UGX · this month</span>
    </div>

    <div class="card">
        <p class="card-label">Not yet confirmed</p>
        <span class="figure {{ $pending > 0 ? 'figure--alarm' : '' }}">{{ $pending }}</span>
        <span class="figure-unit">{{ $pending > 0 ? 'Need a second person' : 'All confirmed' }}</span>
    </div>

    <div class="card">
        <p class="card-label">Counted as</p>
        <span class="figure figure--muted">—</span>
        <span class="figure-unit">Not mortality · not a sale</span>
    </div>
</div>

@if ($records->isEmpty())
    <div class="card u-mt-12">
        <p class="card-label">Nothing recorded</p>
        <p class="muted u-m0">
            When birds are taken for staff meals or the household, record them
            here. Unrecorded, they look like an unexplained fall in the count.
        </p>
    </div>
@else
    <div class="data-list u-mt-12">
        @foreach ($records as $r)
            <div class="consume-row {{ $r->isConfirmed() ? '' : 'consume-row--pending' }}">

                <span class="consume-qty">{{ $r->quantity }}</span>

                <div class="consume-main">
                    <strong>{{ $r->purposeLabel() }}</strong>
                    <span>
                        {{ $r->batch->code }} · {{ $r->consumed_on->format('j M Y') }}
                    </span>
                    @if ($r->detail)
                        <em>{{ $r->detail }}</em>
                    @endif
                </div>

                <div class="consume-who">
                    <span>Authorised by <strong>{{ $r->whoAuthorised() }}</strong></span>
                    <span class="muted">Entered by {{ $r->recorder->displayName() }}</span>
                    @if ($r->estimated_value > 0)
                        <span class="muted">{{ number_format((float) $r->estimated_value) }} UGX</span>
                    @endif
                </div>

                <div class="consume-state">
                    @if ($r->isConfirmed())
                        <span class="pill">Confirmed</span>
                        <span class="muted">{{ $r->confirmer?->firstName() }}</span>
                    @elseif ($user->canDo('bird-consumption', 'approve') && $r->recorded_by !== $user->id)
                        <form method="POST" action="{{ route('consumption.confirm', $r) }}">
                            @csrf
                            <button type="submit" class="btn btn--quiet btn--inline btn--small">Confirm</button>
                        </form>
                    @else
                        <span class="pill pill--muted">Waiting</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="pager">{{ $records->links() }}</div>
@endif

<div class="card u-mt-18">
    <p class="card-label">Why this is separate from deaths</p>
    <p class="muted u-m0">
        Mortality is a health figure that managers act on. If twenty birds
        eaten at a staff function showed as a mortality spike, somebody would
        start investigating a disease that never existed — and the next real
        spike would be dismissed as "just the kitchen again". These birds
        lower the flock count without touching the mortality rate.
    </p>
</div>

@endsection
