@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
<x-report-header eyebrow="Human Resources & Finance" title="Payroll Register" description="HR prepares payroll; Finance Manager, CEO and Director approve it before Finance records payment." :period="now()->format('F Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
<div class="summary-grid">
 <div class="summary-card"><span>This month's gross</span><strong>UGX {{ number_format($currentGross, 2) }}</strong></div>
 <div class="summary-card"><span>This month's net</span><strong>UGX {{ number_format($currentNet, 2) }}</strong></div>
 <div class="summary-card"><span>Awaiting approval</span><strong>{{ $pending }}</strong></div>
</div>
@php $roles=auth()->user()->roles->pluck('code')->push(auth()->user()->role?->code); $isHr=$roles->contains('hr-manager'); @endphp
@if($isHr && auth()->user()->canDo('payroll','create'))
<form class="card" method="POST" action="{{ route('payroll.generate') }}">@csrf
 <h2>Generate a payroll run</h2><p class="muted">Employees are included by the pay frequency in their HR profile. Approved advances and disciplinary deductions are included automatically.</p>
 <div class="form-grid">
  <div class="field"><label for="frequency">Pay frequency</label><select id="frequency" name="frequency" required><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="daily">Daily</option></select></div>
  <div class="field"><label for="period_start">Period start</label><input id="period_start" type="date" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required></div>
  <div class="field"><label for="period_end">Period end</label><input id="period_end" type="date" name="period_end" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" required></div>
  <div class="field"><label for="notes">Notes</label><input id="notes" name="notes" value="{{ old('notes') }}" placeholder="Optional payroll note"></div>
 </div>
 @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
 <div class="form-actions"><button class="btn" type="submit">Generate draft payroll</button></div>
</form>
@endif
<div class="card table-card"><h2>Payroll runs</h2><table><thead><tr><th>Payroll</th><th>Period</th><th>Frequency</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td><a href="{{ route('payroll.show',$run) }}"><strong>{{ $run->payroll_number }}</strong></a></td><td>{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}</td><td>{{ ucfirst($run->frequency) }}</td><td>{{ $run->lines_count }}</td><td>UGX {{ number_format($run->gross_total,2) }}</td><td>UGX {{ number_format($run->deduction_total,2) }}</td><td><strong>UGX {{ number_format($run->net_total,2) }}</strong></td><td>{{ ucfirst(str_replace('_',' ',$run->status)) }}</td></tr>
@empty<tr><td colspan="8">No payroll has been generated yet.</td></tr>@endforelse
</tbody></table>{{ $runs->links() }}</div>
@endsection
