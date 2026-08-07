@extends('layouts.app')
@section('title','Cash Book')
@section('content')
<div class="page-head"><h2>Cash Book</h2><p>Cash transactions and their running balance.</p></div><div class="card"><p class="card-label">Current recorded balance</p><span class="figure">UGX {{ number_format($balance) }}</span></div><div class="data-list"><div class="data-head"><span>Date</span><span>Description</span><span>Money in</span><span>Money out</span><span>Balance</span></div>@foreach($rows as $t)<div class="data-row"><span class="data-cell">{{ $t->transaction_date->format('j M Y') }}</span><span class="data-cell">{{ $t->description }}</span><span class="data-cell">{{ $t->type==='money_received'?'UGX '.number_format($t->amount):'—' }}</span><span class="data-cell">{{ $t->type==='money_paid'?'UGX '.number_format($t->amount):'—' }}</span><span class="data-cell">UGX {{ number_format($t->running_balance) }}</span></div>@endforeach</div>
@endsection
