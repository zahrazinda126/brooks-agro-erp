@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

@php
    // Finance has its own dashboard (partials/finance-dashboard) with its own heading and filters.
    $financeOnly = in_array('finance-dashboard', $panels, true);
    $productionOnly = count($panels) === 1 && in_array('production-overview', $panels, true);
@endphp
@unless($financeOnly || $productionOnly)
<div class="page-head">
    <h2>{{ $greeting }}, {{ $user->firstName() }}</h2>
    <p>{{ now()->format('l, j F Y') }} &middot; {{ $user->roleSummary() }}</p>
</div>

@if($dash)@include('partials.analytics-dashboard')@endif
@endunless

@foreach ($panels as $panel)

    @if ($panel === 'hr-dashboard')
        @php
            $hr = $data['hrDashboard'];
        @endphp
        <section class="hr-dashboard">
            <div class="hr-hero"><div><span class="finance-panel-kicker">People & welfare</span><h3>HR workspace</h3><p>Keep staff records complete, prepare payroll, follow contracts, welfare and employee matters from one place.</p></div><a class="btn" href="{{ route('employees.create') }}">Add worker</a></div>
            <div class="hr-kpis">
                <article><span>&#128101;</span><div><small>Active workers</small><strong>{{ $hr['active'] }}</strong><em>{{ $hr['permanent'] }} permanent &middot; {{ $hr['casual'] }} casual</em></div></article>
                <article><span>UGX</span><div><small>Net payroll this month</small><strong>{{ number_format($hr['monthlyPayroll']) }}</strong><em>{{ $hr['payrollPending'] }} awaiting approval</em></div></article>
                <article><span>&#9203;</span><div><small>Contracts ending</small><strong>{{ $hr['endingSoon'] }}</strong><em>Within the next 30 days</em></div></article>
                <article><span>!</span><div><small>Needs attention</small><strong>{{ $hr['withoutLogin'] + $hr['pendingDeductions'] }}</strong><em>{{ $hr['withoutLogin'] }} without login &middot; {{ $hr['pendingDeductions'] }} deductions</em></div></article>
            </div>
            <div class="hr-work-grid">
                <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Staff register</p><h3>Recently added workers</h3></div><a href="{{ route('employees.index') }}">View all &rarr;</a></div><div class="hr-staff-list">@forelse($hr['recentStaff'] as $employee)<a href="{{ route('employees.show',$employee) }}"><span>{{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}</span><div><strong>{{ $employee->fullName() }}</strong><small>{{ $employee->job_title ?: ($employee->role?->name ?? 'Role not assigned') }} &middot; {{ $employee->department?->name ?? 'No department' }}</small></div><em>{{ Str::headline($employee->employment_type) }}</em></a>@empty<p class="muted">No workers have been registered.</p>@endforelse</div></section>
                <aside class="hr-actions"><section class="exec-panel"><p class="card-label">Payroll cycle</p><h3>{{ $hr['latestPayroll']?->payroll_number ?? 'No payroll yet' }}</h3>@if($hr['latestPayroll'])<p class="muted">{{ $hr['latestPayroll']->period_start->format('d M') }} &ndash; {{ $hr['latestPayroll']->period_end->format('d M Y') }} &middot; {{ Str::headline($hr['latestPayroll']->status) }}</p><strong class="hr-pay-total">@money($hr['latestPayroll']->net_total)</strong>@else<p class="muted">Generate the first payroll when staff pay details are ready.</p>@endif<a class="btn btn--inline" href="{{ route('payroll.index') }}">Open payroll</a></section><section class="exec-panel"><p class="card-label">Common HR work</p><div class="hr-quick-links"><a href="{{ route('hr.kpis.index') }}">HR dashboard & KPIs</a><a href="{{ route('hr.performance.index') }}">Performance records</a><a href="{{ route('hr.welfare-budget.index') }}">Staff welfare budget</a><a href="{{ route('communications.index') }}">Messages</a></div></section></aside>
            </div>
        </section>
    @elseif ($panel === 'finance-dashboard')
        @include('partials.finance-dashboard', ['f' => $data['financeDashboard']])
    @elseif ($panel === 'production-overview')
        @include('partials.production-dashboard')

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
                <p class="muted u-m0-b14">
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

            </div>
        @endif

    {{-- ============================================================
         Approvals
         ============================================================ --}}
    @elseif ($panel === 'executive')
        @include('partials.farm-performance', ['e' => $data['executive']])
    @elseif ($panel === 'executive-legacy')
        @php
            $e = $data['executive'];
        @endphp
        <section class="executive-brief">
            <div class="executive-brief__head">
                <div><p class="card-label">Executive briefing · {{ now()->format('F Y') }}</p><h3>What needs your attention</h3></div>
                <a href="{{ route('executive.index',['screen'=>'kpis']) }}">View full analytics →</a>
            </div>
            <div class="exec-kpi-grid executive-brief__grid">
                <article class="exec-kpi exec-kpi--positive"><span class="exec-kpi__icon">💵</span><div><small>Money received</small><strong>@money($e['income'])</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--negative"><span class="exec-kpi__icon">💸</span><div><small>Money paid</small><strong>@money($e['spending'])</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--{{ $e['pending'] ? 'warning' : 'positive' }}"><span class="exec-kpi__icon">✅</span><div><small>Waiting for your review</small><strong>{{ $e['pending'] }}</strong><em>{{ $e['overdue'] ? $e['overdue'].' overdue' : 'Nothing overdue' }}</em></div></article>
                <article class="exec-kpi"><span class="exec-kpi__icon">📤</span><div><small>Sent to Director</small><strong>{{ $e['sent'] }}</strong><em>Awaiting final decision</em></div></article>
                <article class="exec-kpi exec-kpi--warning"><span class="exec-kpi__icon">📋</span><div><small>Approval exposure</small><strong>@money($e['purchaseExposure'])</strong><em>Submitted purchases</em></div></article>
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

        </div>

    {{-- ============================================================
         HR KPI entry
         ============================================================ --}}
    @elseif ($panel === 'hr-kpis')
        <div class="card">
            <p class="card-label">HR KPI Dashboard</p>
            <p class="muted u-m0-b14">Attendance, performance, contracts, welfare and deductions in one place.</p>
            <a class="btn btn--inline" href="{{ route('hr.kpis.index') }}">Open KPI Dashboard</a>
        </div>

    {{-- ============================================================
         Stock
         ============================================================ --}}
    @elseif ($panel === 'stock')
        <div class="card"><div class="page-head page-head--split u-m0"><div><p class="card-label">Inventory & Stock</p><h3>{{ $data['stock']['items'] }} registered items</h3><p class="muted">{{ $data['stock']['low'] }} at or below reorder level · {{ $data['stock']['feedLines'] }} feed stock lines</p></div><a class="btn" href="{{ route('inventory.index') }}">Open inventory</a></div></div>

    {{-- ============================================================
         Nobody should land on an empty screen.
         ============================================================ --}}
    @elseif ($panel === 'welcome')
        <div class="card">
            <p class="card-label">Nothing assigned yet</p>
            <p class="muted u-m0">
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

@include('directives._status-reason-script')
@endsection




