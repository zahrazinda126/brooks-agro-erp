@extends('layouts.app')
@section('title','Bank Book')
@section('content')
<div class="page-head"><h2>Bank Book</h2><p>Bank-account money in, out and transfers, with the running balance.</p></div>
@include('finance.transactions._book-filters', ['routeName' => 'finance.bank-book.index', 'allLabel' => 'All bank accounts'])
@include('finance.transactions._book-charts')
@include('finance.transactions._book-table', ['accountHeader' => 'Bank account', 'emptyText' => 'No bank transactions match these filters.'])
@pushOnce('styles')<style>.bank-book-totals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.bank-book-table{overflow-x:auto}.bank-book-table table{min-width:1450px;font-size:12px}.bank-book-table th,.bank-book-table td{vertical-align:top}.bank-book-table small{display:block;margin-top:4px;color:var(--ink-soft);line-height:1.35}.bank-book-table .num{text-align:right;white-space:nowrap}.bank-row--warning td{background:#fff2dd}.bank-row--warning td:nth-child(2){color:#9a2c24}@media(max-width:700px){.bank-book-totals{grid-template-columns:1fr}}</style>@endPushOnce
@endsection
