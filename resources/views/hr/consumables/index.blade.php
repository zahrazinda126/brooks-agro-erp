@extends('layouts.app')
@section('title', 'Staff Consumables Issue Register')
@section('content')
<div class="page-head page-head--split">
    <div><p class="eyebrow">Human Resources · {{ $store->name }}</p><h2>Staff consumables issue register</h2><p>Issues are recorded by the store manager and linked to the worker receiving the supplies.</p></div>
    <a class="btn btn--quiet btn--inline" href="{{ route('employees.index') }}">Back to staff</a>
</div>
@if(auth()->user()->canAccess('inventory'))<div class="form-actions accommodation-nav"><a class="btn btn--quiet btn--inline" href="{{ route('inventory.index', ['store' => $store->id]) }}">Open Staff Welfare Store</a></div>@endif
<form method="GET" action="{{ route('hr.consumables.index') }}" class="card filters">
    <div class="field"><label>Worker</label><select name="employee"><option value="">All workers</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)($filters['employee'] ?? '') === (string)$employee->id)>{{ $employee->staff_no }} · {{ $employee->fullName() }}</option>@endforeach</select></div>
    <div class="field"><label>Supply</label><select name="item"><option value="">All supplies</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string)($filters['item'] ?? '') === (string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
    <div class="field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div class="field field--actions"><button class="btn btn--inline">Filter</button><a class="btn btn--quiet btn--inline" href="{{ route('hr.consumables.index') }}">Clear</a></div>
</form>
<section class="card table-card">
    <div class="section-heading"><div><p class="card-label">Issued from store stock</p><h3>Worker supply issues</h3></div><strong>{{ $issues->total() }} records</strong></div>
    <table class="exec-table"><thead><tr><th>Date</th><th>Worker</th><th>Supply</th><th>Quantity</th>@if($canViewValue)<th>Issue value</th>@endif<th>Reference</th><th>Recorded by</th></tr></thead><tbody>
        @forelse($issues as $issue)
            <tr><td>{{ $issue->movement_date->format('d M Y') }}</td><td><strong>{{ $issue->employee?->fullName() ?? $issue->supplier_or_recipient ?? 'Worker record unavailable' }}</strong>@if($issue->employee?->staff_no)<small class="data-sub">{{ $issue->employee->staff_no }}</small>@endif</td><td>{{ $issue->item?->name ?? 'Supply removed' }}</td><td>{{ number_format((float)$issue->quantity, 2) }} {{ $issue->item?->unit }}</td>@if($canViewValue)<td>{{ $issue->total_cost === null ? '—' : 'UGX '.number_format((float)$issue->total_cost, 2) }}</td>@endif<td>{{ $issue->reference ?: '—' }}</td><td>{{ $issue->recorder?->displayName() ?? '—' }}</td></tr>
        @empty<tr><td colspan="{{ $canViewValue ? 7 : 6 }}">No staff supply issues match these filters.</td></tr>@endforelse
    </tbody></table>
    {{ $issues->links() }}
</section>
@endsection
