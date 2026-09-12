@extends('layouts.app')
@section('title', 'Search')
@section('content')
<div class="page-head"><p class="eyebrow">Search</p><h2>Results for “{{ $query }}”</h2><p>{{ $results->count() }} accessible result(s) found.</p></div>
<section class="exec-panel">
    <div class="sales-ranking">
        @forelse($results as $result)
            <div><b aria-hidden="true">{{ $loop->iteration }}</b><span><strong><a href="{{ $result['url'] }}">{{ $result['title'] }}</a></strong><small>{{ $result['type'] }} · {{ $result['detail'] }}</small></span></div>
        @empty
            <div class="notice">No accessible batches, animals, tasks, customers, products, sales, or pages matched your search.</div>
        @endforelse
    </div>
</section>
@endsection
