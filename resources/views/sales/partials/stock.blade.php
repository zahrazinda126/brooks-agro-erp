{{--
    Sellable stock for Sales: what is on hand on a chosen day, or how each item moved day by day.
    Worked out from collections, harvests, births, deaths and sales (App\Services\SellableStock).
--}}
@php
    $isMovement = $stock['view'] === 'movement';
    $f = $stock['filters'];
    $keep = array_filter(['screen' => 'inventory', 'unit' => $f['unit'], 'q' => $f['q'], 'hide_empty' => $f['hide_empty'] ? 1 : null]);
@endphp
<nav class="view-switch" aria-label="Stock view" data-keep-link>
    <a href="{{ route('sales.index', $keep) }}" @unless($isMovement) aria-current="page" @endunless>Stock on hand</a>
    <a href="{{ route('sales.index', $keep + ['stock_view' => 'movement']) }}" @if($isMovement) aria-current="page" @endif>Daily movement</a>
</nav>

<form method="GET" action="{{ route('sales.index') }}" class="card filter-row-card">
    <input type="hidden" name="screen" value="inventory">
    @if($isMovement)<input type="hidden" name="stock_view" value="movement">@endif
    <div class="filter-row">
        @if($isMovement)
            <div class="field"><label for="st-from">From</label><input id="st-from" type="date" name="from" value="{{ $stock['from']->toDateString() }}" max="{{ now()->toDateString() }}"></div>
            <div class="field"><label for="st-to">To</label><input id="st-to" type="date" name="to" value="{{ $stock['to']->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        @else
            <div class="field"><label for="st-on">Stock on</label><input id="st-on" type="date" name="on" value="{{ $stock['on']->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        @endif
        <div class="field"><label for="st-unit">Unit</label><select id="st-unit" name="unit"><option value="">All units</option>@foreach($stock['units'] as $key => $label)<option value="{{ $key }}" @selected($f['unit'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="st-q">Search</label><input id="st-q" type="search" name="q" value="{{ $f['q'] }}" placeholder="Product or group"></div>
        @unless($isMovement)<div class="field"><label class="check-label"><input type="checkbox" name="hide_empty" value="1" @checked($f['hide_empty'])> Hide items with no stock</label></div>@endunless
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('sales.index', ['screen' => 'inventory'] + ($isMovement ? ['stock_view' => 'movement'] : [])) }}">Reset</a></div>
    </div>
</form>

<div class="card table-wrap">
@if(! $isMovement)
    <table class="exec-table stock-table">
        <thead><tr><th>Unit</th><th>Product / group</th><th class="num">In stock{{ $stock['on']->isToday() ? '' : ' on '.$stock['on']->format('d M Y') }}</th></tr></thead>
        <tbody>
        @forelse($stock['rows'] as $row)
            <tr @if($row['quantity'] <= 0) class="muted" @endif>
                <td>{{ $stock['units'][$row['unit']] }}</td>
                <td><strong>{{ $row['item'] }}</strong></td>
                <td class="num"><strong>{{ $row['shown'] }}</strong>@if($row['per_tray'])<small class="table-sub">{{ number_format($row['quantity']) }} eggs</small>@endif</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No products recorded yet. Products appear here once they are collected, harvested, born or received.</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    <table class="exec-table">
        <thead><tr><th>Date</th><th>Unit</th><th>Product / group</th><th class="num">Opening</th><th class="num">Added</th><th class="num">Sold</th><th class="num">Lost / used</th><th class="num">Closing</th></tr></thead>
        <tbody>
        @forelse($stock['rows'] as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                <td>{{ $stock['units'][$row['unit']] }}</td>
                <td><strong>{{ $row['item'] }}</strong><small class="table-sub">{{ $row['measure'] }}</small></td>
                <td class="num">{{ number_format($row['opening']) }}</td>
                <td class="num">@if($row['added']){{ number_format($row['added']) }}<small class="table-sub">{{ $row['added_from'] }}</small>@else — @endif</td>
                <td class="num">{{ $row['sold'] ? number_format($row['sold']) : '—' }}</td>
                <td class="num">@if($row['lost']){{ number_format($row['lost']) }}<small class="table-sub">{{ $row['lost_to'] }}</small>@else — @endif</td>
                <td class="num"><strong>{{ number_format($row['closing']) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No stock changes between {{ $stock['from']->format('d M Y') }} and {{ $stock['to']->format('d M Y') }}.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div>
