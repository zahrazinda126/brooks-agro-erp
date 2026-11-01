@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
<x-report-header eyebrow="Human Resources & Finance" title="Payroll Register" description="HR prepares payroll; Finance Manager, CEO and Director approve it before Finance records payment." :period="now()->format('F Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
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
<div class="card table-card pr-runs"><div class="pr-runs__head"><span class="pr-runs__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9.5 12h5M9.5 16h5"/><circle cx="8.2" cy="12" r=".6"/><circle cx="8.2" cy="16" r=".6"/></svg></span><div><h2>Payroll runs</h2><p>List of payroll runs and their status.</p></div></div><table><thead><tr><th>Payroll</th><th>Period</th><th>Frequency</th><th>Employees</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td><a href="{{ route('payroll.show',$run) }}"><strong>{{ $run->payroll_number }}</strong></a></td><td>{{ $run->period_start->format('d M Y') }} – {{ $run->period_end->format('d M Y') }}</td><td>{{ ucfirst($run->frequency) }}</td><td>{{ $run->lines_count }}</td><td>@money($run->gross_total, 2)</td><td>@money($run->deduction_total, 2)</td><td><strong>@money($run->net_total, 2)</strong></td><td>{{ ucfirst(str_replace('_',' ',$run->status)) }}</td></tr>
@empty<tr><td colspan="8" class="pr-empty"><span class="pr-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9.5 12h5M9.5 16h5"/><circle cx="8.2" cy="12" r=".6"/><circle cx="8.2" cy="16" r=".6"/></svg></span><strong>No payroll has been generated yet.</strong><small>Payroll runs created by HR will appear here.</small></td></tr>@endforelse
</tbody></table>{{ $runs->links() }}</div>
@endsection
