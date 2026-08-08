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

    @elseif ($panel === 'production-overview')
        @php $po=$data['productionOverview']; $unitMax=max(1,$po['units']->max('value')); @endphp
        <section class="production-overview">
            <div class="executive-brief__head"><div><p class="card-label">All farm units</p><h3>Production overview</h3></div><span>Updated from current registers</span></div>
            <div class="production-unit-grid">@foreach($po['units'] as $unit)<a href="{{ $unit['code']==='poultry' ? route('batches.index') : route('operations.units.index',['unit'=>$unit['code']]) }}" class="production-unit-card"><span>{{ $unit['icon'] }}</span><div><small>{{ $unit['label'] }}</small><strong>{{ number_format($unit['value']) }}</strong><em>{{ $unit['measure'] }} · {{ $unit['activity'] }} records in 30 days</em></div></a>@endforeach</div>
            <div class="exec-chart-grid"><div class="exec-panel"><p class="card-label">Registered stock and plots</p><h3>Unit comparison</h3><div class="unit-comparison">@foreach($po['units'] as $unit)<div class="unit-comparison__row"><span>{{ $unit['icon'] }} {{ $unit['label'] }}</span><div><i style="width:{{ max(2,round($unit['value']/$unitMax*100)) }}%"></i></div><strong>{{ number_format($unit['value']) }}</strong></div>@endforeach</div></div><div class="exec-panel"><p class="card-label">Last 30 days</p><h3>Management activity</h3><div class="approval-ring" style="--progress:{{ min(100,$po['activity']*5) }}"><strong>{{ $po['activity'] }}</strong><span>records</span></div><p class="exec-insight">{{ $po['needsAttention'] ? $po['needsAttention'].' unit record(s) need attention.' : 'No unit records are marked as needing attention.' }}</p></div></div>
        </section>

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
    @elseif ($panel === 'executive')
        @php($e=$data['executive'])
        <section class="executive-brief">
            <div class="executive-brief__head">
                <div><p class="card-label">Executive briefing · {{ now()->format('F Y') }}</p><h3>What needs your attention</h3></div>
                <a href="{{ route('executive.index',['screen'=>'kpis']) }}">View full analytics →</a>
            </div>
            <div class="exec-kpi-grid executive-brief__grid">
                <article class="exec-kpi exec-kpi--positive"><span class="exec-kpi__icon">💵</span><div><small>Money received</small><strong>UGX {{ number_format($e['income']) }}</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--negative"><span class="exec-kpi__icon">💸</span><div><small>Money paid</small><strong>UGX {{ number_format($e['spending']) }}</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--{{ $e['pending'] ? 'warning' : 'positive' }}"><span class="exec-kpi__icon">✅</span><div><small>Waiting for your review</small><strong>{{ $e['pending'] }}</strong><em>{{ $e['overdue'] ? $e['overdue'].' overdue' : 'Nothing overdue' }}</em></div></article>
                <article class="exec-kpi"><span class="exec-kpi__icon">📤</span><div><small>Sent to Director</small><strong>{{ $e['sent'] }}</strong><em>Awaiting final decision</em></div></article>
                <article class="exec-kpi exec-kpi--warning"><span class="exec-kpi__icon">📋</span><div><small>Approval exposure</small><strong>UGX {{ number_format($e['purchaseExposure']) }}</strong><em>Submitted purchases</em></div></article>
                <article class="exec-kpi exec-kpi--{{ $e['withdrawals'] ? 'danger' : 'positive' }}"><span class="exec-kpi__icon">🥚</span><div><small>Egg withdrawals</small><strong>{{ $e['withdrawals'] }}</strong><em>{{ $e['withdrawals'] ? 'Active restrictions' : 'No active restrictions' }}</em></div></article>
            </div>
            <div class="executive-brief__actions">
                <div><strong>{{ $e['pending'] ? 'Decisions are waiting.' : 'Your approval queue is clear.' }}</strong><span>{{ $e['pending'] ? 'Review supporting details, comment, approve or reject.' : 'There are no items requiring CEO action right now.' }}</span></div>
                <div class="form-actions"><a class="btn" href="{{ route('approvals.index') }}">Review approvals</a><a class="btn btn--quiet" href="{{ route('approvals.index',['status'=>'sent']) }}">Track Director queue</a></div>
            </div>
        </section>
    @elseif ($panel === 'approvals')
        <div class="card">
            <p class="card-label">Waiting for you</p>
            <span class="figure {{ ($data['approvals']['pending'] ?? 0) ? 'figure--alarm' : '' }}">{{ $data['approvals']['pending'] ?? 0 }}</span>
            <span class="figure-unit">pending approvals</span>
            @if(($data['approvals']['overdue'] ?? 0) > 0)<p>{{ $data['approvals']['overdue'] }} overdue</p>@endif
            <p><a class="btn" href="{{ route('approvals.index') }}">Open approval queue</a></p>
        </div>

    {{-- ============================================================
         Staff
         ============================================================ --}}
    @elseif ($panel === 'staff')
        <div class="grid">
            <div class="card">
                <p class="card-label">Staff</p>
                <span class="figure">{{ $data['staff']['active'] ?? 0 }}</span>
                <span class="figure-unit">On the register</span>
            </div>

            <div class="card">
                <p class="card-label">With a login</p>
                <span class="figure">{{ $data['staff']['withLogin'] ?? 0 }}</span>
                <span class="figure-unit">of {{ $data['staff']['active'] ?? 0 }} staff</span>
            </div>

            <div class="card">
                <p class="card-label">Never signed in</p>
                <span class="figure {{ ($data['staff']['neverSignedIn'] ?? 0) > 0 ? 'figure--alarm' : '' }}">{{ $data['staff']['neverSignedIn'] ?? 0 }}</span>
                <span class="figure-unit">
                    @if (($data['staff']['neverSignedIn'] ?? 0) > 0)
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
         HR KPI entry
         ============================================================ --}}
    @elseif ($panel === 'hr-kpis')
        <div class="card">
            <p class="card-label">HR KPI Dashboard</p>
            <p class="muted" style="margin:0 0 14px;">Attendance, performance, contracts, welfare and deductions in one place.</p>
            <a class="btn btn--inline" href="{{ route('hr.kpis.index') }}">Open KPI Dashboard</a>
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
                <a class="shortcut" href="{{ $m->link ?? $m->url() }}">
                    <svg class="shortcut-icon" aria-hidden="true">
                        <use href="#i-{{ $m->icon ?? $m->iconName() }}"></use>
                    </svg>
                    <span>{{ $m->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

@endforeach

@endsection
