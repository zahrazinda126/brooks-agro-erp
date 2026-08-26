@extends('layouts.app')
@section('title', $employee->fullName().' salary account')
@section('content')
<div class="page-head page-head--split">
    <div><p class="eyebrow">Employee salary account</p><h1>{{ $employee->fullName() }}</h1><p>{{ $employee->staff_no }} &middot; {{ $employee->department?->name ?? 'No department' }}</p></div>
    <a class="btn btn--quiet" href="{{ route('payroll.index') }}">Back to payroll</a>
</div>

<div class="summary-grid">
    <div class="summary-card"><span>Total salary credited</span><strong>UGX {{ number_format($credited) }}</strong><small>Only selected, approved payroll lines</small></div>
    <div class="summary-card"><span>Total collected</span><strong>UGX {{ number_format($withdrawn) }}</strong><small>Money actually handed over or transferred</small></div>
    <div class="summary-card"><span>Available balance</span><strong>UGX {{ number_format($balance) }}</strong><small>Held until the employee requests it</small></div>
    <div class="summary-card"><span>Approved but waiting</span><strong>UGX {{ number_format($waiting) }}</strong><small>Not yet transferred into this account</small></div>
</div>

<section class="card table-wrap">
    <div class="section-heading"><div><p class="card-label">Salary history</p><h2>Payroll periods</h2></div></div>
    <table><thead><tr><th>Period</th><th>Gross</th><th>Deductions</th><th>Net salary</th><th>Credited</th><th>Collected</th><th>Available</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($lines as $line) @php($available=(float)$line->wallet_credited-(float)$line->wallet_withdrawn)
        <tr><td><a href="{{ route('payroll.show',$line->run) }}"><strong>{{ $line->run->payroll_number }}</strong></a><small>{{ $line->run->period_start->format('d M') }} &ndash; {{ $line->run->period_end->format('d M Y') }}</small></td><td>UGX {{ number_format($line->gross_pay) }}</td><td>UGX {{ number_format($line->total_deductions) }}</td><td><strong>UGX {{ number_format($line->net_pay) }}</strong></td><td>UGX {{ number_format($line->wallet_credited) }}</td><td>UGX {{ number_format($line->wallet_withdrawn) }}</td><td><strong>UGX {{ number_format($available) }}</strong></td><td>{{ Str::headline($line->payment_status) }}</td><td>@if($isFinance && $available>0)<a class="btn btn--quiet btn--small" href="{{ route('payroll.lines.withdraw-form',[$line->run,$line]) }}">Record withdrawal</a>@elseif((float)$line->wallet_credited===0.0 && in_array($line->run->status,['approved','partly_funded','partly_paid'],true))<a href="{{ route('payroll.show',$line->run) }}">Fund from payroll</a>@endif</td></tr>
    @empty<tr><td colspan="9">This employee has not appeared on a payroll yet.</td></tr>@endforelse</tbody></table>
</section>

<section class="card table-wrap">
    <div class="section-heading"><div><p class="card-label">Account activity</p><h2>Credits and withdrawals</h2></div></div>
    <table><thead><tr><th>Date</th><th>Activity</th><th>Payroll</th><th>Amount</th><th>Company account / method</th><th>Reference</th><th>Recorded by</th></tr></thead>
    <tbody>@forelse($transactions as $entry)<tr><td>{{ $entry->transaction_date->format('d M Y') }}</td><td>{{ $entry->type==='credit' ? 'Salary credited' : 'Salary collected' }}</td><td>@if($entry->payrollRun)<a href="{{ route('payroll.show',$entry->payrollRun) }}">{{ $entry->payrollRun->payroll_number }}</a>@else &mdash; @endif</td><td><strong>{{ $entry->type==='withdrawal' ? '-' : '+' }} UGX {{ number_format($entry->amount) }}</strong></td><td>{{ $entry->type==='credit' ? ($entry->company_account ?: 'Company account') : Str::headline($entry->payment_method ?: 'Not recorded') }}</td><td>{{ $entry->reference ?: 'Not provided' }}</td><td>{{ $entry->recorder?->displayName() ?? 'System' }}</td></tr>@empty<tr><td colspan="7">No salary-account activity has been recorded.</td></tr>@endforelse</tbody></table>
</section>
@endsection
