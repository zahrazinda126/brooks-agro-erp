@extends('layouts.app')
@section('title','Weekly Budget')
@section('content')
<div class="page-head"><h2>Weekly budget</h2><p>Choose a unit for its details, or view the combined plan.</p></div>
<div class="card">
    <p class="card-label">Weekly budget</p>
    <h3>{{ $periodStart ? $periodStart->format('d M Y').' – '.$periodEnd->format('d M Y') : 'Current week' }}</h3>
    <p><strong>Status: {{ ucfirst($budgetStatus) }}</strong></p>
    @if($budgetStatus==='draft' && $items->isNotEmpty())
        <form method="POST" action="{{ route('finance.budgets.submit') }}" class="form-actions">@csrf <button class="btn">Submit for approval</button></form>
    @elseif($budgetStatus==='submitted')
        <p>This budget was submitted {{ $approval?->submitted_at?->format('d M Y, H:i') }}.</p>
        <p><strong>Stage {{ $approval?->level ?? 1 }} of {{ $approval?->required_levels ?? 2 }}:</strong> awaiting {{ ucfirst($approval?->currentStep()?->role_code ?? 'approval') }}.</p>
        @foreach($approval?->steps ?? [] as $step)
            @if($step->status === 'approved')<p>Stage {{ $step->stage }} approved by <strong>{{ $step->decisionMaker?->displayName() }}</strong> on {{ $step->decided_at->format('d M Y, H:i') }}.</p>@endif
        @endforeach
    @elseif($budgetStatus==='approved')
        <p>Approved by <strong>{{ $approval?->decisionMaker?->displayName() ?? 'Authorised approver' }}</strong>@if($approval?->decided_at) on <strong>{{ $approval->decided_at->format('d M Y, H:i') }}</strong>@endif.</p>
        @if($approval?->decision_note)<p><strong>Decision note:</strong> {{ $approval->decision_note }}</p>@endif
    @elseif($budgetStatus==='rejected')
        <p>Rejected by <strong>{{ $approval?->decisionMaker?->displayName() ?? 'Authorised approver' }}</strong>@if($approval?->decided_at) on <strong>{{ $approval->decided_at->format('d M Y, H:i') }}</strong>@endif.</p>
        @if($approval?->decision_note)<p><strong>Reason:</strong> {{ $approval->decision_note }}</p>@endif
        <p>The rejected submission remains in the approval history. A revision must pass through the CEO and Director again.</p>
        @if(auth()->user()->canDo('budget','edit'))
            <form method="POST" action="{{ route('finance.budgets.revise') }}" class="form-actions">@csrf<button class="btn">Open budget for revision</button></form>
        @endif
    @endif
</div>
<nav class="form-actions" aria-label="Budget units"><a class="btn {{ $unit==='all'?'':'btn--quiet' }}" href="{{ route('finance.budgets.index') }}">All units</a>@foreach(['poultry'=>'Poultry','kituntu'=>'Kituntu Garden','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs','administration'=>'Administration'] as $key=>$label)<a class="btn {{ $unit===$key?'':'btn--quiet' }}" href="{{ route('finance.budgets.index',['unit'=>$key]) }}">{{ $label }}</a>@endforeach</nav>
<div class="summary-grid"><div class="summary-card"><span>{{ $unit==='all' ? 'Combined weekly budget' : 'This unit’s budget' }}</span><strong>UGX {{ number_format($unit==='all'?$allTotal:$plannedTotal) }}</strong></div></div>
<form method="POST" action="{{ route('finance.budgets.update-weekly') }}">@csrf @method('PUT')<div class="card table-card"><table><thead><tr><th>Section</th><th>Item</th><th>Unit</th><th>Quantity</th><th>Unit price</th><th>Total</th>@if($budgetStatus==='draft')<th></th>@endif</tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->category }}</td><td>{{ $item->item }}</td><td>{{ $item->unit }}</td><td>@if($budgetStatus==='draft')<input name="lines[{{ $item->id }}][quantity]" type="number" step="0.01" value="{{ $item->quantity }}">@else{{ number_format((float)$item->quantity, 2) }}@endif</td><td>@if($budgetStatus==='draft')<input name="lines[{{ $item->id }}][unit_price]" type="number" value="{{ $item->unit_price }}">@elseUGX {{ number_format((float)$item->unit_price, 2) }}@endif</td><td>UGX {{ number_format($item->planned_amount) }}</td>@if($budgetStatus==='draft')<td><button formmethod="POST" formaction="{{ route('finance.budgets.destroy',$item) }}" name="_method" value="DELETE">Remove</button></td>@endif</tr>@empty<tr><td colspan="7" class="empty">No budget lines for this unit yet.</td></tr>@endforelse</tbody></table></div>@if($items->isNotEmpty() && $budgetStatus==='draft')<div class="form-actions"><button class="btn">Save weekly budget</button></div>@endif</form>
@if(auth()->user()->canDo('budget','create') && $budgetStatus==='draft')<div class="form-actions"><button type="button" class="btn" id="show-add-item">Add item</button></div><form id="add-item" method="POST" action="{{ route('finance.budgets.store') }}" class="card" hidden>@csrf <h3>Add budget item</h3><div class="form-grid"><div class="field"><label>Section</label><select name="category"><option>Poultry</option><option>Kituntu Garden</option><option>Goat Unit</option><option>Rabbits Unit</option><option>Dogs Unit</option><option>Administration</option></select></div><div class="field"><label>Item</label><input name="item" required></div><div class="field"><label>Unit</label><input name="unit"></div><div class="field"><label>Quantity</label><input name="quantity" type="number" step="0.01" required></div><div class="field"><label>Unit price</label><input name="unit_price" type="number" required></div><input name="period_start" type="hidden" value="{{ now()->startOfWeek()->format('Y-m-d') }}"><input name="period_end" type="hidden" value="{{ now()->endOfWeek()->format('Y-m-d') }}"></div><div class="form-actions"><button class="btn">Add item</button></div></form><script>document.getElementById('show-add-item').addEventListener('click',()=>{const f=document.getElementById('add-item');f.hidden=false;f.scrollIntoView({behavior:'smooth'});});</script>@endif
@endsection
