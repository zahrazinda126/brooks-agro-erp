@extends('layouts.app')
@section('title','Transaction Register')
@section('content')
<div class="page-head page-head--split"><div><h2>Finance Data Entry & Register</h2><p>All revenue, purchases, expenses, banking and accounting entries are recorded here.</p></div>@if($user->canDo('transactions','create'))<a class="btn btn--inline" href="{{ route('finance.transactions.create') }}">New entry</a>@endif</div>
<nav class="finance-workspace-grid" aria-label="Finance workspace">
@foreach([
    ['cash-book','Cash Book',route('finance.cash-book.index')],
    ['bank-book','Bank Book',route('finance.bank-book.index')],
    ['general-ledger','General Ledger',route('finance.general-ledger.index')],
    ['receivables','Customer Balances',route('finance.receivables.index')],
    ['receivables','Customer Prepayments',route('finance.receivables.index').'#prepayments'],
    ['payables','Supplier Balances',route('finance.payables.index')],
    ['assets','Fixed Assets',route('finance.assets.index')],
    ['purchases','Farm Purchases',route('finance.farm.purchases')],
    ['production','Production Costs',route('finance.farm.production')],
    ['veterinary','Veterinary Costs',route('finance.farm.veterinary')],
] as [$permission,$label,$href])
    @if($user->canAccess($permission))<a class="finance-workspace-card" href="{{ $href }}"><span>Open</span><strong>{{ $label }}</strong></a>@endif
@endforeach
</nav>
<form method="GET" class="card filters spending-filter">
    <div class="field"><label>Money flow</label><select name="type"><option value="">All entries</option><option value="money_paid" @selected(request('type')==='money_paid')>Money spent</option><option value="money_received" @selected(request('type')==='money_received')>Money received</option><option value="transfer" @selected(request('type')==='transfer')>Transfers</option></select></div>
    <div class="field"><label>Specific item</label><select name="item"><option value="">All items</option>@foreach($expenseItems as $expenseItem)<option value="{{ $expenseItem }}" @selected(request('item')===$expenseItem)>{{ $expenseItem }}</option>@endforeach</select></div>
    <div class="field"><label>Search item or description</label><input name="search" value="{{ request('search') }}" placeholder="e.g. feeds, layers mash"></div>
    <div class="field"><label>Department</label><select name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
    <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
    <div class="field field--actions"><button class="btn">Apply filters</button><a class="btn btn--quiet" href="{{ route('finance.transactions.index') }}">Clear</a></div>
</form>
<div class="summary-grid summary-grid--compact"><article class="summary-card"><span>Filtered total</span><strong>UGX {{ number_format((float)$filteredTotal) }}</strong><small>{{ $transactions->total() }} matching {{ Str::plural('entry',$transactions->total()) }}</small></article></div>
<div class="data-list"><div class="data-head"><span>Date / recorded at</span><span>Type</span><span>Account</span><span>Amount</span><span>Reference</span></div>@forelse($transactions as $t)<div class="data-row"><span class="data-cell" data-label="Date / recorded at">{{ $t->transaction_date->format('j M Y') }}<em class="data-sub">Entered {{ $t->created_at->format('j M Y, H:i') }} by {{ $t->recorder?->displayName() ?? 'System' }}</em>@if($t->updated_at->gt($t->created_at->copy()->addSecond()))<em class="data-sub">Updated {{ $t->updated_at->format('j M Y, H:i') }}</em>@endif</span><span class="data-cell" data-label="Type">{{ Str::headline($t->type) }}</span><span class="data-cell" data-label="Account">{{ $t->account }}<em class="data-sub">{{ $t->description }}</em></span><span class="data-cell" data-label="Amount">UGX {{ number_format($t->amount) }}</span><span class="data-cell" data-label="Reference">{{ $t->reference ?: '—' }}</span></div>@empty <div class="card empty"><p class="muted" style="margin:0">No transactions recorded yet.</p></div>@endforelse</div><div class="pager">{{ $transactions->links() }}</div>
@endsection
