@extends('layouts.app')
@section('title','Transaction Register')
@section('content')
<div class="page-head page-head--split"><div><h2>Finance Data Entry & Register</h2><p>All revenue, purchases, expenses, banking and accounting entries are recorded here.</p></div>@if($user->canDo('transactions','create'))<a class="btn btn--inline" href="{{ route('finance.transactions.create') }}">New entry</a>@endif</div>
<form method="GET" class="card finance-register-filters">
    <div class="finance-filter-primary">
        <div class="field finance-filter-search"><label>Search</label><input name="search" value="{{ request('search') }}" placeholder="Item, account or description"></div>
        <div class="field"><label>Money flow</label><select name="type"><option value="">All entries</option><option value="money_paid" @selected(request('type')==='money_paid')>Money spent</option><option value="money_received" @selected(request('type')==='money_received')>Money received</option><option value="transfer" @selected(request('type')==='transfer')>Transfers</option></select></div>
        <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <button class="btn">Apply</button><a class="btn btn--quiet" href="{{ route('finance.transactions.index') }}">Clear</a>
    </div>
    <details class="finance-more-filters" @if(request('item')||request('department')) open @endif><summary>More filters</summary><div>
        <div class="field"><label>Specific item</label><select name="item"><option value="">All items</option>@foreach($expenseItems as $expenseItem)<option value="{{ $expenseItem }}" @selected(request('item')===$expenseItem)>{{ $expenseItem }}</option>@endforeach</select></div>
        <div class="field"><label>Department</label><select name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
    </div></details>
</form>
<div class="summary-grid summary-grid--compact"><article class="summary-card"><span>Filtered total</span><strong>UGX {{ number_format((float)$filteredTotal) }}</strong><small>{{ $transactions->total() }} matching {{ Str::plural('entry',$transactions->total()) }}</small></article></div>
<div class="data-list"><div class="data-head"><span>Date / recorded at</span><span>Type</span><span>Account</span><span>Amount</span><span>Reference</span></div>@forelse($transactions as $t)<div class="data-row"><span class="data-cell" data-label="Date / recorded at">{{ $t->transaction_date->format('j M Y') }}<em class="data-sub">Entered {{ $t->created_at->format('j M Y, H:i') }} by {{ $t->recorder?->displayName() ?? 'System' }}</em>@if($t->updated_at->gt($t->created_at->copy()->addSecond()))<em class="data-sub">Updated {{ $t->updated_at->format('j M Y, H:i') }}</em>@endif</span><span class="data-cell" data-label="Type">{{ Str::headline($t->type) }}</span><span class="data-cell" data-label="Account">{{ $t->account }}<em class="data-sub">{{ $t->description }}</em></span><span class="data-cell" data-label="Amount">UGX {{ number_format($t->amount) }}</span><span class="data-cell" data-label="Reference">{{ $t->reference ?: '—' }}</span></div>@empty <div class="card empty"><p class="muted" style="margin:0">No transactions recorded yet.</p></div>@endforelse</div><div class="pager">{{ $transactions->links() }}</div>
<style>
.finance-register-filters{padding:14px;margin-bottom:14px}.finance-filter-primary{display:grid;grid-template-columns:minmax(220px,1.6fr) minmax(150px,.8fr) minmax(140px,.7fr) minmax(140px,.7fr) auto auto;align-items:end;gap:9px}.finance-register-filters .field{margin:0}.finance-register-filters .btn{min-height:43px}.finance-more-filters{margin-top:10px;border-top:1px solid #eee1d5;padding-top:9px}.finance-more-filters summary{width:max-content;color:#8b1e2d;cursor:pointer;font-size:12px;font-weight:800}.finance-more-filters>div{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;margin-top:10px}@media(max-width:1050px){.finance-filter-primary{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-filter-search{grid-column:1/-1}}@media(max-width:580px){.finance-filter-primary,.finance-more-filters>div{grid-template-columns:1fr}.finance-filter-search{grid-column:auto}}
</style>
@endsection
