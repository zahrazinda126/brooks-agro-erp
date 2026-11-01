@extends('layouts.app')
@section('title','Cash Book')
@section('content')
<div class="page-head"><h2>Cash Book</h2><p>Cash received, paid and moved between accounts, with the running balance.</p></div>
@include('finance.transactions._book-filters', ['routeName' => 'finance.cash-book.index', 'allLabel' => 'All cash accounts'])
@include('finance.transactions._book-charts')
@include('finance.transactions._book-table', ['accountHeader' => 'Cash account', 'emptyText' => 'No cash entries match these filters.'])
@endsection
