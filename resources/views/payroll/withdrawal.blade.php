@extends('layouts.app')
@section('title','Salary withdrawal · '.$line->employee->fullName())
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">Staff salary holding account</p><h2>{{ $line->employee->fullName() }}</h2><p>{{ $line->employee->staff_no }} · {{ $payroll->payroll_number }}</p></div><a class="btn btn--quiet" href="{{ route('payroll.show',$payroll) }}">Back to payroll</a></div>
<section class="card table-wrap"><h3>Balance history</h3><table><thead><tr><th>Date</th><th>Entry</th><th>Amount</th><th>Method</th><th>Reference</th><th>Recorded by</th></tr></thead><tbody>@forelse($history as $entry)<tr><td>{{ $entry->transaction_date->format('d M Y') }}</td><td>{{ Str::headline($entry->type) }}</td><td>{{ $entry->type==='withdrawal'?'-':'+' }} @money($entry->amount)</td><td>{{ $entry->payment_method ? Str::headline($entry->payment_method) : 'Internal credit' }}</td><td>{{ $entry->reference ?: '—' }}</td><td>{{ $entry->recorder?->displayName() ?? 'System' }}</td></tr>@empty<tr><td colspan="6">No salary balance entries yet.</td></tr>@endforelse</tbody></table></section>
@endsection
