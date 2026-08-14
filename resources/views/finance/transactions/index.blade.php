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
    ['payables','Supplier Balances',route('finance.payables.index')],
    ['assets','Fixed Assets',route('finance.assets.index')],
    ['purchases','Farm Purchases',route('finance.farm.purchases')],
    ['production','Production Costs',route('finance.farm.production')],
    ['veterinary','Veterinary Costs',route('finance.farm.veterinary')],
] as [$permission,$label,$href])
    @if($user->canAccess($permission))<a class="finance-workspace-card" href="{{ $href }}"><span>Open</span><strong>{{ $label }}</strong></a>@endif
@endforeach
</nav>
<div class="data-list"><div class="data-head"><span>Date</span><span>Type</span><span>Account</span><span>Amount</span><span>Reference</span></div>@forelse($transactions as $t)<div class="data-row"><span class="data-cell" data-label="Date">{{ $t->transaction_date->format('j M Y') }}</span><span class="data-cell" data-label="Type">{{ Str::headline($t->type) }}</span><span class="data-cell" data-label="Account">{{ $t->account }}<em class="data-sub">{{ $t->description }}</em></span><span class="data-cell" data-label="Amount">UGX {{ number_format($t->amount) }}</span><span class="data-cell" data-label="Reference">{{ $t->reference ?: '—' }}</span></div>@empty <div class="card empty"><p class="muted" style="margin:0">No transactions recorded yet.</p></div>@endforelse</div><div class="pager">{{ $transactions->links() }}</div>
@endsection
