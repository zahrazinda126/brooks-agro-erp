@extends('layouts.app')
@section('title','My Salary Balance')
@section('content')
<div class="page-head"><p class="eyebrow">My account</p><h1>Salary balance</h1><p>{{ $employee->fullName() }} &middot; {{ $employee->staff_no }}</p></div>
@php($salaryView = request('view') === 'payroll' ? 'payroll' : 'balance')
<nav class="view-switch" aria-label="Salary pages" data-keep-link>
    <a href="{{ route('payroll.my-balance') }}" @if($salaryView === 'balance') aria-current="page" @endif>Balance &amp; history</a>
    <a href="{{ route('payroll.my-balance', ['view' => 'payroll']) }}" @if($salaryView === 'payroll') aria-current="page" @endif>Payroll calculations</a>
</nav>
@if($salaryView === 'payroll')
<form method="GET" class="card filter-row-card"><input type="hidden" name="view" value="payroll"><div class="filter-row">
    <div class="field"><label for="year">Year</label><select id="year" name="year" onchange="this.form.submit()"><option value="">Latest 12 payrolls</option>@foreach($payYears as $year)<option value="{{ $year }}" @selected((int)($filters['year'] ?? 0) === $year)>{{ $year }}</option>@endforeach</select></div>
    <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('payroll.my-balance', ['view' => 'payroll']) }}">Reset</a></div>
</div></form>
<section class="card table-wrap"><table><thead><tr><th>Payroll period</th><th>Gross pay</th><th>PAYE</th><th>NSSF</th><th>Performance shortfall</th><th>Penalty</th><th>Net pay</th></tr></thead><tbody>@forelse($payrollLines as $line)<tr><td>{{ $line->run?->period_start?->format('d M Y') }} – {{ $line->run?->period_end?->format('d M Y') }}<small>{{ $line->run?->payroll_number }}</small></td><td>@money($line->gross_pay)</td><td>@money($line->paye)</td><td>@money($line->nssf_employee)</td><td>@money($line->performance_adjustment)</td><td><strong class="{{ (float)$line->penalties > 0 ? 'text-alarm' : '' }}">@money($line->penalties)</strong>@if((float)$line->penalties > 0 && $line->notes)<small>{{ $line->notes }}</small>@endif</td><td><strong>@money($line->net_pay)</strong></td></tr>@empty<tr><td colspan="7">No payroll calculation has been recorded yet.</td></tr>@endforelse</tbody></table></section>
@else
<div class="summary-grid"><x-stat-card label="Salary credited">@money($credited)</x-stat-card><x-stat-card label="Collected">@money($withdrawn)</x-stat-card><div class="summary-card"><span>Available balance</span><strong>@money($balance)</strong><small>Request collection through Finance</small></div></div>
<form method="GET" class="card filter-row-card"><div class="filter-row">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="field"><label for="entry">Entry</label><select id="entry" name="entry"><option value="">All entries</option><option value="credit" @selected(($filters['entry'] ?? '') === 'credit')>Salary credited</option><option value="withdrawal" @selected(($filters['entry'] ?? '') === 'withdrawal')>Collected</option></select></div>
    <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('payroll.my-balance') }}">Reset</a></div>
</div></form>
<section class="card table-wrap"><table><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Method</th><th>Reference</th><th>Recorded by</th></tr></thead><tbody>@forelse($transactions as $entry)<tr><td>{{ $entry->transaction_date->format('d M Y') }}</td><td>{{ Str::headline($entry->type) }}</td><td><strong>{{ $entry->type==='withdrawal' ? '-' : '+' }} @money($entry->amount)</strong></td><td>{{ $entry->payment_method ? Str::headline($entry->payment_method) : 'Internal credit' }}</td><td>{{ $entry->reference ?: 'Not provided' }}</td><td>{{ $entry->recorder?->displayName() ?? 'System' }}</td></tr>@empty<tr><td colspan="6">No salary has been credited to your staff balance yet.</td></tr>@endforelse</tbody></table></section>
@endif
@endsection
