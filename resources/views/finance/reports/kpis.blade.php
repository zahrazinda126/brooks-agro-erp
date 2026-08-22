@extends('layouts.app')
@section('title','Finance KPI Dashboard')
@section('content')
<div class="page-head"><div><h2>Finance KPI Dashboard</h2><p>Collection, payment, cash movement and budget-control indicators.</p></div><a class="btn" href="{{ route('finance.reports.export',['report'=>'kpis','from'=>$from,'to'=>$to]) }}">Download branded Excel</a></div>
@include('finance.reports._filter')
<div class="summary-grid">
@foreach(['collection_rate'=>'Collection rate','payment_rate'=>'Supplier payment rate','net'=>'Net cash movement','budget'=>'Approved/planned budget','budget_variance'=>'Budget less cash paid','payables'=>'Outstanding payables'] as $key=>$label)
<div class="summary-card"><span>{{ $label }}</span><strong>{{ in_array($key,['collection_rate','payment_rate']) ? number_format($m[$key],1).'%' : 'UGX '.number_format($m[$key]) }}</strong></div>
@endforeach
</div>
@endsection
