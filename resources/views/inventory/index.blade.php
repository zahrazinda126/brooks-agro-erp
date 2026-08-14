@extends('layouts.app')
@section('title','Inventory & Stock')
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">Stores</p><h1>Stock Control</h1><p>Purchases add stock automatically from Finance Data Entry. Use this page to issue, consume, return, transfer, count or adjust stock.</p></div><a class="btn" href="{{ route('finance.transactions.create') }}">Record a purchase</a></div>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="notice notice--alarm"><strong>{{ $errors->first() }}</strong></div>@endif

@if($canCreate)
<div class="exec-chart-grid">
<details class="exec-panel" hidden><summary><strong>Add an inventory item</strong></summary>
<form method="POST" action="{{ route('inventory.items.store') }}" class="form-grid" style="margin-top:1rem">@csrf
<div class="field"><label>Item code</label><input name="code" value="{{ old('code') }}" placeholder="e.g. MED-001" required></div>
<div class="field"><label>Item name</label><input name="name" value="{{ old('name') }}" required></div>
<div class="field"><label>Category</label><select name="category_choice" data-category-choice>@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach<option value="custom">Other / type category</option></select></div>
<div class="field" data-custom-category hidden><label>Type category</label><input name="custom_category" value="{{ old('custom_category') }}"></div>
<div class="field"><label>Unit of measure</label><input name="unit" placeholder="litre, kg, piece, roll" value="{{ old('unit') }}" required></div>
<div class="field"><label>Reorder level</label><input type="number" step="0.01" min="0" name="reorder_level" value="{{ old('reorder_level',0) }}" required></div>
<div class="field"><label>Default location</label><input name="default_location" value="{{ old('default_location','Main Store') }}" required></div>
<div class="field field--wide"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div><button class="btn">Add item</button>
</form></details>

<details class="exec-panel" open><summary><strong>Record a stock movement</strong></summary>
<form method="POST" action="{{ route('inventory.movements.store') }}" class="form-grid" style="margin-top:1rem" data-cost-calculator>@csrf
<div class="field"><label>Item</label><select name="inventory_item_id" data-inventory-item required><option value="">Choose item</option>@foreach($items as $item)<option value="{{ $item->id }}" data-location="{{ $item->default_location }}">{{ $item->code }} · {{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
<div class="field"><label>Movement</label><select name="movement_type" data-movement-type required><option value="issued">Issued to person/unit</option><option value="farm_use">Used on the farm</option><option value="return">Returned to store</option><option value="wastage">Damaged / expired / lost</option><option value="transfer">Transfer between locations</option><option value="adjustment_in">Count adjustment increase</option><option value="adjustment_out">Count adjustment decrease</option></select></div>
<div class="field"><label>Date</label><input type="date" name="movement_date" value="{{ now()->toDateString() }}" required></div>
<div class="field"><label>Store/location</label><input name="location" value="Main Store" data-source-location required></div>
<div class="field" data-destination hidden><label>Destination location</label><input name="destination_location"></div>
<div class="field"><label>Quantity</label><input type="number" step="0.01" min="0.01" name="quantity" data-cost-quantity required></div>
<div class="field"><label>Unit cost (UGX)</label><input type="number" step="0.01" min="0" name="unit_cost" data-cost-unit><p class="field-hint">Optional for stock adjustments.</p></div>
<div class="field"><label>Total cost (UGX)</label><input type="number" step="0.01" min="0" name="total_cost" data-cost-total><p class="field-hint">Calculated from unit cost; optional for non-receipts.</p></div>
<div class="field"><label>Supplier / recipient / unit</label><input name="supplier_or_recipient"></div>
<div class="field"><label>Reference</label><input name="reference" placeholder="GRN, issue note, count sheet"></div>
<div class="field field--wide"><label>Reason or notes</label><textarea name="notes"></textarea></div><button class="btn">Record movement</button>
</form></details></div>
@endif

<section class="exec-panel table-wrap"><div class="page-head page-head--split"><div><p class="eyebrow">Live balances</p><h2>General inventory</h2></div><span>{{ $items->count() }} registered items</span></div><table class="exec-table"><thead><tr><th>Code</th><th>Category</th><th>Item</th><th>Unit</th><th>Balance</th><th>Reorder at</th><th>Status</th></tr></thead><tbody>@forelse($items as $item)@php($low=(float)$item->balance<=(float)$item->reorder_level)<tr><td>{{ $item->code }}</td><td>{{ ucfirst($item->category) }}</td><td><strong>{{ $item->name }}</strong></td><td>{{ $item->unit }}</td><td><strong>{{ number_format((float)$item->balance,2) }}</strong></td><td>{{ number_format((float)$item->reorder_level,2) }}</td><td>@if($low)<span class="badge badge--danger">Reorder</span>@else<span class="badge">In stock</span>@endif</td></tr>@empty<tr><td colspan="7">No general inventory items have been registered yet.</td></tr>@endforelse</tbody></table></section>

<div class="exec-chart-grid">
<section class="exec-panel table-wrap"><h2>Balances by location</h2><table class="exec-table"><thead><tr><th>Item</th><th>Location</th><th>Balance</th></tr></thead><tbody>@forelse($byLocation as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->location }}</td><td>{{ number_format($row->balance,2) }} {{ $row->unit }}</td></tr>@empty<tr><td colspan="3">No location balances yet.</td></tr>@endforelse</tbody></table></section>
<section class="exec-panel table-wrap"><h2>Feed stock</h2><p class="muted"><a href="{{ route('operations.feed-stock.index') }}">Open the feed receiving register</a></p><table class="exec-table"><thead><tr><th>Unit</th><th>Feed</th><th>Balance</th></tr></thead><tbody>@forelse($feed as $row)<tr><td>{{ ucfirst($row->unit_code) }}</td><td>{{ $row->feed_name }}</td><td>{{ number_format($row->balance,2) }} kg</td></tr>@empty<tr><td colspan="3">No feed stock recorded.</td></tr>@endforelse</tbody></table></section>
</div>

<section class="exec-panel table-wrap"><div class="page-head page-head--split"><div><h2>Approved egg stock</h2><p>Only supervisor-approved collections are included.</p></div><a class="btn btn--quiet" href="{{ route('collections.index') }}">Open egg inventory</a></div><table class="exec-table"><thead><tr><th>Grade</th><th>Eggs collected</th><th>Equivalent trays</th></tr></thead><tbody>@forelse($eggStock as $row)<tr><td>{{ $row->name }}</td><td>{{ number_format($row->eggs) }}</td><td>{{ number_format($row->eggs/max(1,$row->eggs_per_tray),2) }}</td></tr>@empty<tr><td colspan="3">No approved egg collections yet.</td></tr>@endforelse</tbody></table></section>

<section class="exec-panel table-wrap"><h2>Movement history</h2><table class="exec-table"><thead><tr><th>Date</th><th>Item</th><th>Movement</th><th>Location</th><th>Quantity</th><th>Value</th><th>Reference</th><th>Recorded by</th></tr></thead><tbody>@forelse($movements as $row)<tr><td>{{ $row->movement_date->format('d M Y') }}</td><td>{{ $row->item->name }}</td><td>{{ ucwords(str_replace('_',' ',$row->movement_type)) }}</td><td>{{ $row->location }}</td><td>{{ number_format($row->quantity,2) }} {{ $row->item->unit }}</td><td>{{ $row->total_cost===null?'—':'UGX '.number_format($row->total_cost,2) }}</td><td>{{ $row->reference?:'—' }}</td><td>{{ $row->recorder?->displayName() }}</td></tr>@empty<tr><td colspan="8">No stock movements recorded yet.</td></tr>@endforelse</tbody></table>{{ $movements->links() }}</section>

<script>(function(){var choice=document.querySelector('[data-category-choice]'),custom=document.querySelector('[data-custom-category]');function category(){if(custom)custom.hidden=!choice||choice.value!=='custom'}choice&&choice.addEventListener('change',category);category();var item=document.querySelector('[data-inventory-item]'),location=document.querySelector('[data-source-location]');item&&item.addEventListener('change',function(){var option=item.options[item.selectedIndex];if(option&&option.dataset.location)location.value=option.dataset.location});var type=document.querySelector('[data-movement-type]'),destination=document.querySelector('[data-destination]');function movement(){if(destination)destination.hidden=!type||type.value!=='transfer'}type&&type.addEventListener('change',movement);movement()})();</script>
@endsection
