@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="page-head">
    <h2>{{ $greeting }}, {{ $user->firstName() }}</h2>
    <p>{{ now()->format('l, j F Y') }} · {{ $user->roleSummary() }}</p>
</div>

@foreach ($panels as $panel)

    {{-- ============================================================
         A worker's own work. First, because it is the only thing most
         workers need from this screen.
         ============================================================ --}}
    @if ($panel === 'my-work')
        <div class="card">
            <p class="card-label">Your work today</p>
            <p class="muted" style="margin:0;">
                Task assignment is not built yet. When it is, what you have been
                asked to do today will appear here.
            </p>
        </div>

    {{-- ============================================================
         Flocks. Trouble first, then the totals — a supervisor opening
         this at 6am wants to know what needs them, not the headline.
         ============================================================ --}}
    @elseif ($panel === 'flocks')
        @php
            $f = $data['flocks'];
        @endphp

        @if ($f['count'] === 0)
            <div class="card">
                <p class="card-label">Flocks</p>
                <p class="muted" style="margin:0 0 14px;">
                    No batches are open. Start one when your next delivery of birds
                    arrives — everything else in Poultry hangs off it.
                </p>
                @if ($user->canDo('batches', 'create'))
                    <a class="btn btn--inline" href="{{ route('batches.create') }}">New batch</a>
                @endif
            </div>
        @else
            @if ($f['attention']->isNotEmpty())
                <div class="card card--alarm">
                    <p class="card-label">Needs attention</p>

                    @foreach ($f['attention'] as $b)
                        <a class="attention-row" href="{{ route('batches.show', $b) }}">
                            <span>
                                <strong class="mono">{{ $b->code }}</strong>
                                @if ($b->name) <em>{{ $b->name }}</em> @endif
                            </span>
                            <span class="attention-why">
                                @if ($b->layingIsOverdue())
                                    Laying overdue by {{ abs($b->weeksToLay()) }} weeks
                                @else
                                    Mortality {{ $b->mortalityRate() }}% at {{ $b->ageInWeeks() }} weeks
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($f['comingSoon']->isNotEmpty())
                <div class="card">
                    <p class="card-label">Coming into lay</p>
                    @foreach ($f['comingSoon'] as $b)
                        <a class="attention-row" href="{{ route('batches.show', $b) }}">
                            <span>
                                <strong class="mono">{{ $b->code }}</strong>
                                @if ($b->name) <em>{{ $b->name }}</em> @endif
                            </span>
                            <span class="attention-why attention-why--soon">
                                First eggs in {{ $b->weeksToLay() }} {{ Str::plural('week', $b->weeksToLay()) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="grid">
                <div class="card">
                    <p class="card-label">Birds</p>
                    <span class="figure">{{ number_format($f['birds']) }}</span>
                    <span class="figure-unit">Alive now</span>
                </div>

                <div class="card">
                    <p class="card-label">Flocks</p>
                    <span class="figure">{{ $f['count'] }}</span>
                    <span class="figure-unit">Open batches</span>
                </div>

                <div class="card">
                    <p class="card-label">In lay</p>
                    <span class="figure">{{ $f['laying'] }}</span>
                    <span class="figure-unit">of {{ $f['count'] }} flocks</span>
                </div>

                <div class="card">
                    <p class="card-label">Eggs today</p>
                    <span class="figure figure--muted">—</span>
                    <span class="figure-unit">Collection not built yet</span>
                </div>
            </div>
        @endif

    {{-- ============================================================
         Approvals
         ============================================================ --}}
    @elseif ($panel === 'approvals')
        <div class="card">
            <p class="card-label">Waiting for you</p>
            <p class="muted" style="margin:0;">
                Nothing to approve. Records submitted by workers will appear here
                once daily entry is built.
            </p>
        </div>

    {{-- ============================================================
         Staff
         ============================================================ --}}
    @elseif ($panel === 'staff')
        @php
            $s = $data['staff'];
        @endphp
        <div class="grid">
            <div class="card">
                <p class="card-label">Staff</p>
                <span class="figure">{{ $s['active'] }}</span>
                <span class="figure-unit">On the register</span>
            </div>

            <div class="card">
                <p class="card-label">With a login</p>
                <span class="figure">{{ $s['withLogin'] }}</span>
                <span class="figure-unit">of {{ $s['active'] }} staff</span>
            </div>

            <div class="card">
                <p class="card-label">Never signed in</p>
                <span class="figure {{ $s['neverSignedIn'] > 0 ? 'figure--alarm' : '' }}">{{ $s['neverSignedIn'] }}</span>
                <span class="figure-unit">
                    @if ($s['neverSignedIn'] > 0)
                        May need their password again
                    @else
                        Everyone has signed in
                    @endif
                </span>
            </div>

            <div class="card">
                <p class="card-label">Payroll due</p>
                <span class="figure figure--muted">—</span>
                <span class="figure-unit">Payroll not built yet</span>
            </div>
        </div>

    {{-- ============================================================
         Money
         ============================================================ --}}
    @elseif ($panel === 'money')
        <div class="grid">
            <div class="card">
                <p class="card-label">Sales today</p>
                <span class="figure figure--muted">—</span>
                <span class="figure-unit">UGX · Sales not built yet</span>
            </div>
            <div class="card">
                <p class="card-label">Owed to us</p>
                <span class="figure figure--muted">—</span>
                <span class="figure-unit">UGX · Not built yet</span>
            </div>
            <div class="card">
                <p class="card-label">Expenses this month</p>
                <span class="figure figure--muted">—</span>
                <span class="figure-unit">UGX · Not built yet</span>
            </div>
            <div class="card">
                <p class="card-label">Cash available</p>
                <span class="figure figure--muted">—</span>
                <span class="figure-unit">UGX · Not built yet</span>
            </div>
        </div>

    {{-- ============================================================
         Stock
         ============================================================ --}}
    @elseif ($panel === 'stock')
        <div class="card">
            <p class="card-label">Stock</p>
            <p class="muted" style="margin:0;">
                Feed cover and low-stock warnings appear here once Inventory is built.
            </p>
        </div>

    {{-- ============================================================
         Nobody should land on an empty screen.
         ============================================================ --}}
    @elseif ($panel === 'welcome')
        <div class="card">
            <p class="card-label">Nothing assigned yet</p>
            <p class="muted" style="margin:0;">
                Your account has no screens attached to it. Ask HR to check your
                role and which section you are posted to.
            </p>
        </div>

    {{-- ============================================================
         Shortcuts — the screens this person actually opens.
         ============================================================ --}}
    @elseif ($panel === 'shortcuts' && $data['shortcuts']->isNotEmpty())
        <h3 class="section-heading">Go to</h3>

        <div class="shortcut-grid">
            @foreach ($data['shortcuts'] as $m)
                <a class="shortcut" href="{{ $m->url() }}">
                    <svg class="shortcut-icon" aria-hidden="true">
                        <use href="#i-{{ $m->iconName() }}"></use>
                    </svg>
                    <span>{{ $m->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

@endforeach

@endsection
