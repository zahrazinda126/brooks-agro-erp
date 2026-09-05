@extends('layouts.app')

@section('title','Weekly Budget')
@section('content')
@php
    $unfundedItems=$items->where('funding_status','unfunded');
    $cancelledItems=$items->where('funding_status','cancelled');
    $fundedItems=$items->whereIn('funding_status',['funded','partial']);
@endphp

<div class="page-head page-head--split">
    <div><p class="eyebrow">Finance</p><h2>Weekly budget</h2><p>Prepare the current plan or review a previous budget and its funding decisions.</p></div>
    <form method="GET" action="{{ route('finance.budgets.index') }}" class="period-picker">
        <label for="budget-week">Budget week</label>
        <select id="budget-week" name="week" onchange="this.form.submit()">
            <option value="{{ now()->startOfWeek()->toDateString() }}" @selected($selectedWeek===now()->startOfWeek()->toDateString())>Current week</option>
            @foreach($availablePeriods as $period)
                @if($period->period_start->toDateString()!==now()->startOfWeek()->toDateString())<option value="{{ $period->period_start->toDateString() }}" @selected($selectedWeek===$period->period_start->toDateString())>{{ $period->period_start->format('d M Y') }} · {{ ucfirst($period->status) }}</option>@endif
            @endforeach
        </select>
    </form>
</div>

<section class="card">
    <div class="section-heading"><div><p class="card-label">Department submissions</p><h3>Combined weekly draft</h3></div></div>
    <div class="summary-grid summary-grid--compact">
        @forelse($departmentTotals as $department=>$summary)<div class="summary-card"><span>{{ $department }}</span><strong>UGX {{ number_format((float)$summary['total']) }}</strong><small>{{ $summary['lines'] }} line(s)</small></div>@empty<div class="empty">No department has entered a draft for this week.</div>@endforelse
    </div>
    <p class="muted">Departments prepare their requests. Finance may adjust the working draft, and the CEO may adjust it during CEO review. Every adjustment is recorded.</p>
</section>

<section class="status-banner status-banner--{{ $budgetStatus }}">
    <div><span class="status-pill">{{ ucfirst($budgetStatus) }}</span><h3>{{ $periodStart ? $periodStart->format('d M').' – '.$periodEnd->format('d M Y') : 'No budget started for this week' }}</h3>
        @if($budgetStatus==='submitted')<p>Awaiting {{ ucwords(str_replace('-',' ',$approval?->currentStep()?->role_code ?? 'approval')) }}.</p>
        @elseif($budgetStatus==='approved')<p>Approved for implementation. Funding decisions are shown below.</p>
        @elseif($budgetStatus==='rejected')<p>{{ $approval?->decision_note ?: 'Returned for correction.' }}</p>
        @else<p>{{ $items->count() }} line(s) in the working draft.</p>@endif
    </div>
    <div class="form-actions form-actions--flush">
        @if($budgetStatus==='draft' && $items->isNotEmpty())<form method="POST" action="{{ route('finance.budgets.submit') }}">@csrf<input type="hidden" name="week" value="{{ $selectedWeek }}"><button class="btn btn--inline">Submit for approval</button></form>@endif
        @if($budgetStatus==='rejected' && auth()->user()->canDo('budget','edit'))<form method="POST" action="{{ route('finance.budgets.revise') }}">@csrf<input type="hidden" name="week" value="{{ $selectedWeek }}"><button class="btn btn--inline">Open revision</button></form>@endif
    </div>
</section>

<nav class="unit-tabs" aria-label="Budget units">
    <a class="{{ $unit==='all'?'active':'' }}" href="{{ route('finance.budgets.index',['week'=>$selectedWeek]) }}">All</a>
    @foreach(['poultry'=>'Poultry','kituntu'=>'Kituntu · All','kituntu-tomatoes'=>'Kituntu · Tomatoes','kituntu-passion'=>'Kituntu · Passion Fruit','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs','staff-welfare'=>'Staff Welfare','administration'=>'Administration'] as $key=>$label)<a class="{{ $unit===$key?'active':'' }}" href="{{ route('finance.budgets.index',['unit'=>$key,'week'=>$selectedWeek]) }}">{{ $label }}</a>@endforeach
</nav>

<div class="summary-grid summary-grid--compact">
    <div class="summary-card"><span>Requested</span><strong>UGX {{ number_format((float)$plannedTotal) }}</strong></div>
    @if($budgetStatus!=='draft')<div class="summary-card"><span>Funded</span><strong>UGX {{ number_format((float)$fundedItems->sum('funded_amount')) }}</strong></div><div class="summary-card"><span>Awaiting funds</span><strong>{{ $unfundedItems->count() }}</strong></div><div class="summary-card"><span>Cancelled</span><strong>{{ $cancelledItems->count() }}</strong></div>@endif
</div>

@if($unfundedItems->isNotEmpty())
<details class="card disclosure" open><summary><span><strong>Unfunded — keep for later</strong><small>Can be released if money arrives midweek</small></span><span class="pill">{{ $unfundedItems->count() }}</span></summary><div class="disclosure__body table-wrap"><table class="exec-table"><thead><tr><th>Item</th><th class="num">Requested</th><th>Reason</th></tr></thead><tbody>@foreach($unfundedItems as $item)<tr><td><strong>{{ $item->item }}</strong><small>{{ $item->category }}</small></td><td class="num">UGX {{ number_format((float)$item->planned_amount) }}</td><td>{{ $item->funding_note }}</td></tr>@endforeach</tbody></table></div></details>
@endif
@if($cancelledItems->isNotEmpty())
<details class="card disclosure"><summary><span><strong>Cancelled — do not carry out</strong><small>Items removed by executive decision</small></span><span class="pill">{{ $cancelledItems->count() }}</span></summary><div class="disclosure__body table-wrap"><table class="exec-table"><thead><tr><th>Item</th><th class="num">Requested</th><th>Reason</th></tr></thead><tbody>@foreach($cancelledItems as $item)<tr><td><strong>{{ $item->item }}</strong><small>{{ $item->category }}</small></td><td class="num">UGX {{ number_format((float)$item->planned_amount) }}</td><td>{{ $item->funding_note }}</td></tr>@endforeach</tbody></table></div></details>
@endif

<details class="card disclosure" @if($budgetStatus==='draft') open @endif>
    <summary><span><strong>Department budget lines</strong><small>{{ $items->count() }} items for {{ $unit==='all'?'all departments':ucfirst($unit) }}</small></span></summary>
    <div class="disclosure__body">
        @if($items->isEmpty())<p class="empty">No budget lines for this selection.</p>@else
        <div class="table-wrap"><table class="exec-table"><thead><tr><th>Department / section</th><th>Item</th><th>Unit</th><th class="num">Quantity</th><th class="num">Unit price</th><th class="num">Total</th>@if($canAdjustBudget)<th>Adjustment</th>@endif</tr></thead><tbody>
        @foreach($items->groupBy(fn($line)=>($line->department?->name ?? 'Unassigned').'|'.$line->category) as $sectionItems)
            @foreach($sectionItems as $item)
                <tr>
                    <td><strong>{{ $item->department?->name ?? 'Unassigned' }}</strong><small>{{ $item->category }}</small></td>
                    <td>{{ $item->item }}</td><td>{{ $item->unit }}</td>
                    @if($canAdjustBudget)
                        <td class="num"><input class="budget-adjust-input" form="adjust-budget-{{ $item->id }}" aria-label="Quantity for {{ $item->item }}" name="quantity" type="number" min="0" step="0.01" value="{{ $item->quantity }}" required></td>
                        <td class="num"><input class="budget-adjust-input" form="adjust-budget-{{ $item->id }}" aria-label="Unit price for {{ $item->item }}" name="unit_price" type="number" min="0" step="0.01" value="{{ $item->unit_price }}" required></td>
                    @else
                        <td class="num">{{ number_format((float)$item->quantity,2) }}</td><td class="num">UGX {{ number_format((float)$item->unit_price,2) }}</td>
                    @endif
                    <td class="num"><strong>UGX {{ number_format((float)$item->planned_amount) }}</strong></td>
                    @if($canAdjustBudget)<td><form id="adjust-budget-{{ $item->id }}" method="POST" action="{{ route('finance.budgets.update',$item) }}">@csrf @method('PUT')<input name="adjustment_note" placeholder="Reason for adjustment" required><button class="btn btn--small btn--inline">Save</button></form></td>@endif
                </tr>
            @endforeach
            <tr class="budget-section-subtotal"><th colspan="5">{{ $sectionItems->first()->department?->name ?? 'Unassigned' }} · {{ $sectionItems->first()->category }} subtotal</th><th class="num">UGX {{ number_format((float)$sectionItems->sum('planned_amount')) }}</th>@if($canAdjustBudget)<th></th>@endif</tr>
        @endforeach
        </tbody></table></div>@endif
    </div>
</details>

@endsection
