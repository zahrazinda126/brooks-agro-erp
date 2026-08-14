@extends('layouts.app')
@section('title','Manage Unit Budgets')
@section('content')
<div class="card">
    <div class="page-head"><div><p class="card-label">Saved production budgets</p><h3>Drafts and budget history</h3></div>
        <form method="GET" action="{{ route('production.finance-draft.index') }}" class="form-actions">
            <input type="hidden" name="unit" value="{{ $unit }}">
            <label for="production-budget-week">Budget week</label>
            <select id="production-budget-week" name="week" onchange="this.form.submit()">
                <option value="{{ now()->startOfWeek()->toDateString() }}" @selected($start->isSameDay(now()->startOfWeek()))>Current week</option>
                @foreach($availablePeriods as $period)
                    @if(!$period->period_start->isSameDay(now()->startOfWeek()))<option value="{{ $period->period_start->toDateString() }}" @selected($start->isSameDay($period->period_start))>{{ $period->period_start->format('d M Y') }} — {{ ucfirst($period->status) }} ({{ $period->line_count }} lines)</option>@endif
                @endforeach
            </select>
        </form>
    </div>
    @if($availablePeriods->where('status','draft')->isEmpty())<p class="muted">There are no saved production budget drafts.</p>@else<p><strong>{{ $availablePeriods->where('status','draft')->count() }} saved draft period(s)</strong> are available. Choose a week above to review them.</p>@endif
</div>
<div class="page-head page-head--split">
    <div><p class="eyebrow">💰 Production planning</p><h2>{{ $category }} Weekly Budget</h2><p>{{ $start->format('j M Y') }} – {{ $end->format('j M Y') }} · This page contains only {{ $category }} requirements.</p></div>
    <a class="btn btn--quiet" href="{{ route('daily.index') }}">Back to My Work</a>
</div>
<nav class="unit-tabs">@foreach($units as $key=>$label)<a class="{{ $unit===$key?'active':'' }}" href="{{ route('production.finance-draft.index',['unit'=>$key,'week'=>$start->toDateString()]) }}">{{ $label }}</a>@endforeach</nav>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<section class="exec-panel">
    <div class="page-head page-head--split" style="margin-bottom:1rem"><div><p class="eyebrow">Draft total</p><h3>UGX {{ number_format($lines->sum('planned_amount')) }}</h3></div><span class="status-pill">{{ $locked?'Locked for approval':'Editable draft' }}</span></div>
    @if($locked)<p class="muted">Finance has submitted this weekly budget for approval. Production can review these lines, but cannot change them unless Finance returns the budget to draft.</p>@else
    <form method="POST" action="{{ route('production.finance-draft.store') }}" class="form-grid">@csrf
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="hidden" name="week" value="{{ $start->toDateString() }}">
        <div class="field"><label>Unit</label><input value="{{ $category }}" disabled></div>
        <div class="field"><label>Item / requirement</label><input name="item" required></div>
        <div class="field"><label>Measure unit</label><input name="unit" placeholder="kg, litres, bags"></div>
        <div class="field"><label>Quantity</label><input type="number" step="0.01" min="0.01" name="quantity" required></div>
        <div class="field"><label>Unit price (UGX)</label><input type="number" step="0.01" min="0" name="unit_price" required></div>
        <div class="field field--wide"><label>Reason / notes</label><textarea name="notes"></textarea></div>
        <div class="form-actions field--wide"><button class="btn">Add to Finance draft</button></div>
    </form>@endif
</section>
<section class="exec-panel table-wrap"><table class="exec-table"><thead><tr><th>Unit</th><th>Item</th><th>Measure</th><th>Quantity / Price / Notes</th><th>Action</th></tr></thead><tbody>
@forelse($lines as $line)<tr><td><strong>{{ $line->category }}</strong></td><td>{{ $line->item }}</td><td>{{ $line->unit ?: '—' }}</td>
@if(!$locked && $line->status==='draft')<td colspan="2"><form method="POST" action="{{ route('production.finance-draft.update',$line) }}" style="display:grid;grid-template-columns:110px 150px 1fr auto auto;gap:.6rem;align-items:center">@csrf @method('PUT')<input aria-label="Quantity" type="number" step="0.01" min="0" name="quantity" value="{{ $line->quantity }}" required><input aria-label="Unit price" type="number" step="0.01" min="0" name="unit_price" value="{{ $line->unit_price }}" required><input aria-label="Notes" name="notes" value="{{ $line->notes }}"><button class="btn btn--small">Update</button><button class="btn btn--quiet btn--small" formmethod="POST" formaction="{{ route('production.finance-draft.delete',$line) }}" name="_method" value="DELETE" onclick="return confirm('Remove this budget item?')">Delete</button></form></td>
@else<td>Qty {{ number_format($line->quantity,2) }} · UGX {{ number_format($line->unit_price) }} each<br><small>{{ $line->notes ?: 'No notes' }}</small></td><td>{{ ucfirst(str_replace('_',' ',$line->status)) }}</td>@endif</tr>
@empty<tr><td colspan="5">Finance has no {{ $category }} draft lines for this week yet. You can add the first line above.</td></tr>@endforelse
</tbody></table></section>
@endsection
