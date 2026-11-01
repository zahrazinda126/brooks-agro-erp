@extends('layouts.app')
@section('title','Fixed Assets')
@section('content')
<form method="GET" action="{{ route('finance.assets.index') }}" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="fa-q">Search</label><input id="fa-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Asset, tag or location"></div>
        <div class="field"><label for="fa-category">Category</label><select id="fa-category" name="category"><option value="">All categories</option>@foreach(array_keys($categories) as $category)<option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></div>
        <div class="field"><label for="fa-status">Status</label><select id="fa-status" name="status"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
        <div class="field"><label for="fa-from">Acquired from</label><input id="fa-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="field"><label for="fa-to">To</label><input id="fa-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('finance.assets.index') }}">Reset</a>@if(auth()->user()->canDo('assets','create'))<a class="btn btn--small" href="{{ route('finance.assets.create') }}">+ Register asset</a>@endif</div>
    </div>
</form>
@if($categoryTotals->isNotEmpty())<div class="card table-card"><h3>Assets by category</h3><table><thead><tr><th>Category</th><th>Assets</th><th class="num">Cost</th></tr></thead><tbody>@foreach($categoryTotals as $total)<tr><td>{{ $total->category }}</td><td>{{ $total->asset_count }}</td><td class="num">@money($total->total_cost)</td></tr>@endforeach</tbody></table></div>@endif
<div class="card table-card"><table><thead><tr><th>Asset</th><th>Category</th><th>Tag</th><th>Date acquired</th><th>Location</th><th class="num">Cost</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->category }}</td><td>{{ $item->asset_tag ?: '—' }}</td><td>{{ $item->purchase_date->format('d M Y') }}</td><td>{{ $item->location ?: '—' }}</td><td class="num">@money($item->purchase_cost)</td></tr>@empty<tr><td colspan="6" class="empty">No fixed assets registered yet.</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
@endsection
