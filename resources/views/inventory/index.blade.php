@extends('layouts.app')
@section('title','Inventory')
@section('content')
@php
/*
 | Inventory on the Brooks Farm Hub "Warehouse" model: summary tiles, store chips, then one tab per job.
 | Existing screens (record movement, house items, history, add item, store access) are kept as tabs.
 */
$defaultScreen=$isStoreManager?'movement':'stock';
$screen=request('screen',$defaultScreen);
$screens=$isStoreManager
    ? ['movement'=>'Receive / issue','requests'=>'Requests','receipts'=>'Receipts','batches'=>'Batches','adjustments'=>'Adjustments','ledger'=>'Ledger','daily'=>'Daily report']
    : ['stock'=>'Stock','requests'=>'Requests','receipts'=>'Receipts','batches'=>'Batches','adjustments'=>'Adjustments','ledger'=>'Ledger','daily'=>'Daily report','movement'=>'Record movement','houses'=>'House items','history'=>'History'];
if(!$canCreate)unset($screens['movement']);
if(!$isStoreManager&&$access['can_receive'])$screens['item']='Add item';
if($isDirector)$screens['access']='Store access';
if(!isset($screens[$screen]))$screen=$defaultScreen;
if($allStoresView && $screen!=='stock')$screen='stock';
$storeAction=$isStoreManager&&in_array(request('action'),['received','issued','farm_use'],true)?request('action'):'received';
$showValue=$access['can_view_value'];
$tabUrl=fn($key)=>route('inventory.index',['store'=>$allStoresView?'all':$selectedStore->id,'screen'=>$key]);
$storeUrl=fn($id)=>route('inventory.index',['store'=>$id,'screen'=>$screen==='access'?'stock':$screen]);
@endphp

<div class="page-head page-head--split">
    <div>
        <p class="eyebrow">Department</p>
        <h1>Inventory</h1>
        <p>Stock = opening + receipts + adjustments − issues − losses. Adjustments and losses count once the Finance Manager approves them.</p>
    </div>
    <div class="button-row">
        <a class="btn btn--quiet btn--inline" href="{{ route('inventory.requests.index') }}">Stock requests</a>
        @unless($allStoresView)<a class="btn btn--quiet btn--inline" href="{{ $tabUrl('daily') }}">Daily report</a>@endunless
    </div>
</div>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="notice notice--alarm"><strong>{{ $errors->first() }}</strong></div>@endif

{{-- Summary tiles --}}
<div class="summary-grid inv-tiles">
    <div class="summary-card"><span>Items in stock</span><strong>{{ $summary['inStock'] }} of {{ $summary['items'] }}</strong><small>{{ $allStoresView ? 'Across your '.$stores->count().' stores' : $selectedStore->name }}</small></div>
    <div class="summary-card {{ $summary['critical'] ? 'inv-tile--alarm' : '' }}"><span>Critical</span><strong>{{ $summary['critical'] }}</strong><small>Out of stock</small></div>
    <div class="summary-card {{ $summary['low'] ? 'inv-tile--warn' : '' }}"><span>Low</span><strong>{{ $summary['low'] }}</strong><small>At or below reorder level</small></div>
    <div class="summary-card {{ ($summary['expiring']+$summary['expired']) ? 'inv-tile--warn' : '' }}"><span>Expiry alerts</span><strong>{{ $summary['expiring']+$summary['expired'] }}</strong><small>{{ $summary['expired'] }} expired · {{ $summary['expiring'] }} within 30 days</small></div>
    <div class="summary-card"><span>Waiting</span><strong>{{ $summary['awaitingApproval']+$summary['openRequests'] }}</strong><small>{{ $summary['awaitingApproval'] }} adjustment(s) · {{ $summary['openRequests'] }} open request(s)</small></div>
    @if($showValue)<div class="summary-card"><span>Stock value</span><strong>UGX {{ number_format($summary['value']) }}</strong><small>At the latest purchase price</small></div>@endif
</div>

{{-- Store chips --}}
<nav class="inv-chips" aria-label="Stores" data-keep-link>
    @if($stores->count() > 1)<a href="{{ route('inventory.index',['store'=>'all','screen'=>'stock']) }}" @if($allStoresView) aria-current="page" @endif>All</a>@endif
    @foreach($stores as $store)<a href="{{ $storeUrl($store->id) }}" @if(!$allStoresView && $store->id===$selectedStore->id) aria-current="page" @endif>{{ $store->name }}</a>@endforeach
</nav>

{{-- Tabs --}}
<nav class="view-switch inv-tabs" aria-label="Inventory tasks" data-keep-link>
    @foreach($screens as $key=>$label)
        @continue($allStoresView && $key!=='stock')
        <a href="{{ $tabUrl($key) }}" @if($screen===$key) aria-current="page" @endif>{{ $label }}@if($key==='adjustments' && $summary['awaitingApproval']) <span class="inv-count">{{ $summary['awaitingApproval'] }}</span>@endif @if($key==='receipts' && $pendingReceipts->isNotEmpty()) <span class="inv-count">{{ $pendingReceipts->count() }}</span>@endif</a>
    @endforeach
</nav>

@if($screen==='stock')
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><h2>{{ $allStoresView ? 'All stores' : $selectedStore->name }}</h2></div><strong>{{ $items->count() }} items</strong></div>
    <table class="exec-table"><thead><tr><th>Item</th>@if($allStoresView)<th>Store</th>@endif<th>Category</th><th class="num">Balance</th><th class="num">Reorder at</th><th>Status</th></tr></thead><tbody>
    @forelse($items as $item)
        @php($bal=(float)$item->balance)
        @php($state=$bal<=0?['Critical','badge--danger']:($bal<=(float)$item->reorder_level?['Low','badge--warn']:['In stock','']))
        <tr><td><strong>{{ $item->name }}</strong><small class="data-sub">{{ $item->code }}</small></td>@if($allStoresView)<td>{{ $item->store?->name }}</td>@endif<td>{{ ucfirst($item->category) }}</td><td class="num"><strong>{{ number_format($bal,2) }}</strong> {{ $item->unit }}</td><td class="num">{{ number_format((float)$item->reorder_level,2) }}</td><td><span class="badge {{ $state[1] }}">{{ $state[0] }}</span>@unless($allStoresView)<a class="inv-ledger-link" href="{{ route('inventory.index',['store'=>$selectedStore->id,'screen'=>'ledger','item'=>$item->id]) }}">Ledger</a>@endunless</td></tr>
    @empty
        <tr><td colspan="{{ $allStoresView?6:5 }}">No items are registered {{ $allStoresView ? 'in your stores' : 'in this store' }} yet.@if(!$allStoresView && !$isStoreManager && $access['can_receive']) <a href="{{ $tabUrl('item') }}">Add the first item</a> with its opening balance.@endif</td></tr>
    @endforelse
    </tbody></table>
</section>
@if(!$allStoresView && $feed->isNotEmpty())<section class="exec-panel table-wrap"><div class="section-heading"><div><p class="eyebrow">Poultry feed ledger</p><h2>Verified feed balance</h2></div></div><table class="exec-table"><thead><tr><th>Unit</th><th>Feed</th><th class="num">Balance</th></tr></thead><tbody>@foreach($feed as $row)<tr><td>{{ ucfirst($row->unit_code) }}</td><td>{{ $row->feed_name }}</td><td class="num"><strong>{{ number_format($row->balance,2) }} kg</strong></td></tr>@endforeach</tbody></table></section>@endif

@elseif($screen==='requests')
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><h2>Stock requests to {{ $selectedStore->name }}</h2><p>Request → Approval → Prepared → Dispatched → Received → Acknowledged. Prepare, dispatch and close them under Stock requests.</p></div><a class="btn btn--small" href="{{ route('inventory.requests.index',['show'=>'mine']) }}">Open stock requests</a></div>
    <table class="exec-table"><thead><tr><th>Request</th><th>Requested by</th><th>Items</th><th>Needed</th><th>Status</th></tr></thead><tbody>
    @forelse($storeRequests as $req)
        <tr><td><strong>{{ $req->number }}</strong><small class="data-sub">{{ $req->created_at->format('d M Y') }}</small></td><td>{{ $req->requester?->displayName() }}</td><td>{{ $req->lines->map(fn($l)=>$l->item?->name.' × '.rtrim(rtrim(number_format((float)$l->quantity_requested,2),'0'),'.'))->join(', ') }}</td><td>{{ $req->needed_on?->format('d M Y') ?? '—' }}</td><td><x-status-pill :status="in_array($req->status,['acknowledged'])?'approved':(in_array($req->status,['rejected','cancelled'])?'rejected':'pending')" :label="$req->statusLabel()" /></td></tr>
    @empty
        <tr><td colspan="5">No requests have been made to this store.</td></tr>
    @endforelse
    </tbody></table>
</section>

@elseif($screen==='receipts')
@if($access['can_receive'] && $pendingReceipts->isNotEmpty())
<section class="exec-panel table-wrap"><div class="section-heading"><div><p class="eyebrow">Awaiting store confirmation</p><h2>Deliveries recorded by Finance</h2><p>Check what physically arrived. Stock increases only after you confirm it.@if($tracksExpiry) Give the batch number and expiry date on each package.@endif</p></div><strong>{{ $pendingReceipts->count() }} pending</strong></div>
<table class="exec-table"><thead><tr><th>Finance date</th><th>Item</th><th>Supplier / reference</th><th>Expected</th><th>Confirm actual receipt</th></tr></thead><tbody>
@foreach($pendingReceipts as $receipt)<tr><td>{{ $receipt->movement_date->format('d M Y') }}<small class="data-sub">By {{ $receipt->recorder?->displayName() }}</small></td><td><strong>{{ $receipt->item->name }}</strong><small class="data-sub">{{ $receipt->location }}</small></td><td>{{ $receipt->supplier_or_recipient ?: '—' }}<small class="data-sub">{{ $receipt->reference ?: 'No reference' }}</small></td><td>{{ number_format((float)$receipt->quantity,2) }} {{ $receipt->item->unit }}</td><td><form method="POST" action="{{ route('inventory.receipts.confirm',$receipt) }}" class="form-grid">@csrf<div class="field"><label>Quantity received <span class="req">*</span></label><input type="number" name="quantity_received" min="0.01" step="0.01" value="{{ number_format((float)$receipt->quantity,2,'.','') }}" required></div><div class="field"><label>Received on <span class="req">*</span></label><input type="date" name="received_on" value="{{ now()->toDateString() }}" required></div>@if($tracksExpiry)<div class="field"><label>Batch / lot number <span class="req">*</span></label><input name="batch_code" required></div><div class="field"><label>Expiry date <span class="req">*</span></label><input type="date" name="expiry_date" required></div>@endif<div class="field"><label>Note if quantity differs</label><input name="receipt_note"></div><button class="btn btn--small">Confirm receipt</button></form></td></tr>@endforeach
</tbody></table></section>
@endif
<section class="exec-panel table-wrap"><div class="section-heading"><div><h2>Stock receipts</h2><p>Deliveries confirmed into {{ $selectedStore->name }}.</p></div></div>
<table class="exec-table"><thead><tr><th>Date</th><th>Item</th><th class="num">Quantity</th>@if($tracksExpiry)<th>Batch · expiry</th>@endif<th>Supplier / reference</th>@if($showValue)<th class="num">Value</th>@endif<th>Confirmed by</th></tr></thead><tbody>
@forelse($recentReceipts as $row)<tr><td>{{ $row->movement_date->format('d M Y') }}</td><td><strong>{{ $row->item->name }}</strong></td><td class="num">{{ number_format((float)$row->quantity,2) }} {{ $row->item->unit }}</td>@if($tracksExpiry)<td>{{ $row->batch ? $row->batch->batch_code.' · '.$row->batch->expiry_date->format('d M Y') : '—' }}</td>@endif<td>{{ $row->supplier_or_recipient ?: '—' }}<small class="data-sub">{{ $row->reference ?: '' }}</small></td>@if($showValue)<td class="num">{{ $row->total_cost===null?'—':'UGX '.number_format($row->total_cost) }}</td>@endif<td>{{ $row->confirmer?->displayName() ?? $row->recorder?->displayName() }}</td></tr>@empty<tr><td colspan="7">No receipts recorded.</td></tr>@endforelse
</tbody></table></section>

@elseif($screen==='batches')
<section class="exec-panel table-wrap"><div class="section-heading"><div><h2>Batch register</h2><p>@if($tracksExpiry)First to expire, first out. "Expiring soon" means within 30 days. Expired batches stay on the register until written off with an approved loss, and are never issued.@else Items in {{ $selectedStore->name }} are not kept in batches. Batches with expiry dates are kept for the Feed, Veterinary, Kitchen & Food and Farm Supplies stores.@endif</p></div></div>
<table class="exec-table"><thead><tr><th>Item</th><th>Batch</th><th>Received</th><th>Expiry</th><th class="num">Received qty</th><th class="num">Left</th><th>Status</th></tr></thead><tbody>
@forelse($batches as $batch)
    @php($state=$batch->balance<=0?['Used up','']:($batch->isExpired()?['Expired · write off','badge--danger']:($batch->isExpiringSoon()?['Expiring soon','badge--warn']:['Good',''])))
    <tr><td><strong>{{ $batch->item?->name }}</strong></td><td>{{ $batch->batch_code }}</td><td>{{ $batch->received_on->format('d M Y') }}</td><td>{{ $batch->expiry_date->format('d M Y') }}</td><td class="num">{{ number_format((float)$batch->quantity_received,2) }}</td><td class="num"><strong>{{ number_format($batch->balance,2) }}</strong> {{ $batch->item?->unit }}</td><td><span class="badge {{ $state[1] }}">{{ $state[0] }}</span></td></tr>
@empty
    <tr><td colspan="7">No stock held in batches.</td></tr>
@endforelse
</tbody></table></section>

@elseif($screen==='adjustments')
<section class="exec-panel table-wrap"><div class="section-heading"><div><h2>Stock adjustments and losses</h2><p>Only approved adjustments move the balance. Nothing in the ledger is ever deleted; a rejected adjustment stays, marked rejected.@if($canCreate && ($access['can_adjust']||$access['can_issue'])) Record a new one under <a href="{{ $tabUrl('movement') }}">{{ $isStoreManager ? 'Receive / issue' : 'Record movement' }}</a>.@endif</p></div></div>
<table class="exec-table"><thead><tr><th>Date</th><th>Item</th><th>Type · reason</th><th class="num">Quantity</th><th>Recorded by</th><th>Status</th>@if($canDecideAdjustments)<th>Finance decision</th>@endif</tr></thead><tbody>
@forelse($adjustments as $row)
    @php($label=['wastage'=>'Loss','adjustment_in'=>'Count increase','adjustment_out'=>'Count decrease'][$row->movement_type]??$row->movement_type)
    <tr><td>{{ $row->movement_date->format('d M Y') }}</td><td><strong>{{ $row->item->name }}</strong>@if($row->batch)<small class="data-sub">Batch {{ $row->batch->batch_code }}</small>@endif</td><td>{{ $label }}@if($row->adjustment_reason) · {{ $adjustmentReasons[$row->adjustment_reason] ?? $row->adjustment_reason }}@endif<small class="data-sub">{{ $row->notes }}</small></td><td class="num">{{ $row->movement_type==='adjustment_in'?'+':'−' }}{{ number_format((float)$row->quantity,2) }} {{ $row->item->unit }}</td><td>{{ $row->recorder?->displayName() }}</td>
    <td>@if($row->receipt_status==='awaiting_approval')<x-status-pill status="pending" label="Waiting for Finance" />@elseif($row->receipt_status==='rejected')<x-status-pill status="rejected" label="Rejected" />@else<x-status-pill status="approved" label="Approved" /><small class="data-sub">{{ $row->confirmer?->displayName() }}</small>@endif</td>
    @if($canDecideAdjustments)<td>@if($row->receipt_status==='awaiting_approval')<form method="POST" action="{{ route('inventory.adjustments.decide',$row) }}" class="inv-decide">@csrf<input name="decision_note" placeholder="Note (required to reject)" aria-label="Decision note"><button class="btn btn--small" name="decision" value="approve">Approve</button><button class="btn btn--small btn--quiet" name="decision" value="reject">Reject</button></form>@else — @endif</td>@endif</tr>
@empty
    <tr><td colspan="{{ $canDecideAdjustments?7:6 }}">No adjustments.</td></tr>
@endforelse
</tbody></table></section>

@elseif($screen==='ledger')
<form method="GET" class="card filters"><input type="hidden" name="store" value="{{ $selectedStore->id }}"><input type="hidden" name="screen" value="ledger"><div class="field"><label for="inv-ledger-item">Item</label><select id="inv-ledger-item" name="item" onchange="this.form.submit()"><option value="">Choose an item</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected($ledgerItem?->id===$item->id)>{{ $item->name }} ({{ $item->code }})</option>@endforeach</select></div></form>
@if($ledgerItem)
<section class="exec-panel table-wrap"><div class="section-heading"><div><h2>{{ $ledgerItem->name }} · stock ledger</h2><p>Every movement in order with the running balance. Receipts waiting for the store and adjustments waiting for Finance are listed but do not count yet.</p></div><strong>{{ number_format($ledgerRows->last()?->running ?? 0,2) }} {{ $ledgerItem->unit }}</strong></div>
<table class="exec-table"><thead><tr><th>Date</th><th>Movement</th><th>Batch</th><th class="num">In</th><th class="num">Out</th><th class="num">Balance</th><th>Reference</th><th>By</th></tr></thead><tbody>
@foreach($ledgerRows as $row)<tr @class(['inv-ledger--pending'=>!$row->counted])><td>{{ $row->movement_date->format('d M Y') }}</td><td>{{ ucwords(str_replace('_',' ',$row->movement_type)) }}@if(!$row->counted)<small class="data-sub">{{ $row->receipt_status==='rejected'?'Rejected':($row->receipt_status==='awaiting_approval'?'Waiting for Finance':'Waiting for the store') }}</small>@endif</td><td>{{ $row->batch?->batch_code ?? '—' }}</td><td class="num">{{ $row->direction==='in'?number_format((float)$row->quantity,2):'' }}</td><td class="num">{{ $row->direction==='out'?number_format((float)$row->quantity,2):'' }}</td><td class="num"><strong>{{ number_format($row->running,2) }}</strong></td><td>{{ $row->reference ?: '—' }}@if($row->poultryHouse)<small class="data-sub">House {{ $row->poultryHouse->displayNumber() }}</small>@endif</td><td>{{ $row->recorder?->displayName() }}</td></tr>@endforeach
</tbody></table></section>
@else
<div class="card empty-state"><strong>Choose an item</strong><p>The ledger lists every receipt, issue, transfer, loss and adjustment of one item with its running balance.</p></div>
@endif

@elseif($screen==='daily')
<form method="GET" class="card filters"><input type="hidden" name="store" value="{{ $selectedStore->id }}"><input type="hidden" name="screen" value="daily"><div class="field"><label for="inv-daily-date">Report date</label><input id="inv-daily-date" type="date" name="date" value="{{ $dailyDate }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()"></div></form>
<section class="exec-panel"><div class="section-heading"><div><p class="eyebrow">Calculated from inventory records</p><h2>{{ $selectedStore->name }} · daily report · {{ \Carbon\Carbon::parse($dailyDate)->format('d M Y') }}</h2></div></div>
<div class="summary-grid summary-grid--compact">
    <x-stat-card label="Items in stock">{{ $summary['inStock'] }} of {{ $summary['items'] }}</x-stat-card>
    <x-stat-card label="Critical items">{{ $summary['critical'] }}</x-stat-card>
    <x-stat-card label="Low items">{{ $summary['low'] }}</x-stat-card>
    <x-stat-card label="Expiring within 30 days">{{ $summary['expiring'] }}</x-stat-card>
    <x-stat-card label="Expired batches held">{{ $summary['expired'] }}</x-stat-card>
    <x-stat-card label="Adjustments waiting for Finance">{{ $summary['awaitingApproval'] }}</x-stat-card>
    <x-stat-card label="Open requests">{{ $summary['openRequests'] }}</x-stat-card>
</div></section>
<section class="exec-panel table-wrap"><div class="section-heading"><div><h3>Received</h3></div></div><table class="exec-table"><thead><tr><th>Item</th><th class="num">Quantity</th></tr></thead><tbody>@forelse($daily['received'] as $row)<tr><td>{{ $row->name }}</td><td class="num">{{ number_format((float)$row->quantity,2) }} {{ $row->unit }}</td></tr>@empty<tr><td colspan="2">Nothing received on this date.</td></tr>@endforelse</tbody></table></section>
<section class="exec-panel table-wrap"><div class="section-heading"><div><h3>Issued and used</h3></div></div><table class="exec-table"><thead><tr><th>Item</th><th class="num">Quantity</th></tr></thead><tbody>@forelse($daily['issued'] as $row)<tr><td>{{ $row->name }}</td><td class="num">{{ number_format((float)$row->quantity,2) }} {{ $row->unit }}</td></tr>@empty<tr><td colspan="2">Nothing issued on this date.</td></tr>@endforelse</tbody></table></section>
<section class="exec-panel table-wrap"><div class="section-heading"><div><h3>Adjustments and losses</h3></div></div><table class="exec-table"><thead><tr><th>Item</th><th>Type · reason</th><th class="num">Quantity</th><th>Status</th></tr></thead><tbody>@forelse($daily['adjustments'] as $row)<tr><td>{{ $row->item->name }}</td><td>{{ ['wastage'=>'Loss','adjustment_in'=>'Count increase','adjustment_out'=>'Count decrease'][$row->movement_type] ?? $row->movement_type }}@if($row->adjustment_reason) · {{ $adjustmentReasons[$row->adjustment_reason] ?? '' }}@endif</td><td class="num">{{ number_format((float)$row->quantity,2) }} {{ $row->item->unit }}</td><td>{{ ['awaiting_approval'=>'Waiting for Finance','rejected'=>'Rejected','confirmed'=>'Approved'][$row->receipt_status] ?? $row->receipt_status }}</td></tr>@empty<tr><td colspan="4">No adjustments on this date.</td></tr>@endforelse</tbody></table></section>

@elseif($screen==='movement')
@if($access['can_receive'] && $pendingReceipts->isNotEmpty())<div class="notice"><strong>{{ $pendingReceipts->count() }} {{ Str::plural('delivery',$pendingReceipts->count()) }} recorded by Finance {{ $pendingReceipts->count()===1?'is':'are' }} waiting for you.</strong> <a href="{{ $tabUrl('receipts') }}">Confirm under Receipts</a>.</div>@endif
<section class="exec-panel inventory-form-panel"><div class="section-heading"><div><p class="eyebrow">Stock transaction</p><h2>Record movement</h2><p>Receive, issue, consume, transfer, or adjust an item in {{ $selectedStore->name }}. Losses and count corrections go to the Finance Manager for approval.</p></div></div>
@if(!$canCreate)<div class="notice">You have view-only access to this store.</div>@elseif($items->isEmpty())<div class="notice"><strong>No item is available.</strong><p>{{ $isStoreManager ? 'Ask the Inventory Officer or an authorised manager to register the item first.' : 'Add the item first, then return here to record its stock.' }}</p>@if(!$isStoreManager&&$access['can_receive'])<a class="btn" href="{{ $tabUrl('item') }}">Add first item</a>@endif</div>@else
<form method="POST" action="{{ route('inventory.movements.store') }}" class="form-grid" data-cost-calculator>@csrf
<div class="field field--wide"><label>Item <span class="req">*</span></label><select name="inventory_item_id" data-inventory-item required><option value="">Choose item</option>@foreach($items as $item)<option value="{{ $item->id }}" data-location="{{ $item->default_location }}">{{ $item->code }} · {{ $item->name }} ({{ number_format((float)$item->balance,2) }} {{ $item->unit }} available)</option>@endforeach</select></div>
<div class="field"><label>Movement <span class="req">*</span></label><select name="movement_type" data-movement-type required>@if($isStoreManager)<option value="{{ $storeAction }}">{{ match($storeAction){'received'=>'Received into store','farm_use'=>'Farm consumption (no payment)',default=>'Issued to person/unit'} }}</option>@else @if($access['can_receive'])<option value="received">Received into store</option><option value="return">Returned to store</option>@endif @if($access['can_issue'])<option value="issued">Issued to person/unit</option><option value="farm_use">Farm consumption (no payment)</option><option value="wastage">Loss: damaged, expired or lost (Finance approves)</option><option value="transfer">Move within store</option>@endif @if($access['can_adjust'])<option value="adjustment_in">Count increase (Finance approves)</option><option value="adjustment_out">Count decrease (Finance approves)</option>@endif @endif</select></div>
@if($selectedStore->code==='welfare')<div class="field field--wide" data-welfare-recipient @if(($storeAction ?? '')!=='issued' && !request()->isMethod('post')) hidden @endif><label>Worker receiving supplies <span class="req">*</span></label><select name="employee_id" data-welfare-employee><option value="">Choose worker</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id')==$employee->id)>{{ $employee->staff_no }} · {{ $employee->fullName() }}</option>@endforeach</select><span class="field-hint">Required for a Staff Welfare Store issue. HR can report issues by worker.</span></div>@endif
<div class="field" data-adjustment hidden><label>Reason <span class="req">*</span></label><select name="adjustment_reason"><option value="">Choose reason</option>@foreach($adjustmentReasons as $key=>$label)<option value="{{ $key }}" @selected(old('adjustment_reason')===$key)>{{ $label }}</option>@endforeach</select></div>
@if($tracksExpiry)
<div class="field" data-adjustment hidden><label>Batch (for an expired or damaged batch)</label><select name="inventory_batch_id"><option value="">Not a specific batch</option>@foreach($batches->where('balance','>',0) as $batch)<option value="{{ $batch->id }}">{{ $batch->item?->name }} · {{ $batch->batch_code }} · exp. {{ $batch->expiry_date->format('d M Y') }}</option>@endforeach</select></div>
<div class="field" data-receipt-batch><label>Batch / lot number <span class="req">*</span></label><input name="batch_code" value="{{ old('batch_code') }}"><span class="field-hint">As printed on the package.</span></div>
<div class="field" data-receipt-batch><label>Expiry date <span class="req">*</span></label><input type="date" name="expiry_date" value="{{ old('expiry_date') }}"></div>
@endif
<div class="field"><label>Date <span class="req">*</span></label><input type="date" name="movement_date" value="{{ now()->toDateString() }}" required></div><div class="field"><label>Store location <span class="req">*</span></label><input name="location" value="{{ $selectedStore->name }}" data-source-location required></div><div class="field" data-destination hidden><label>Destination location</label><input name="destination_location"></div><div class="field"><label>Quantity <span class="req">*</span></label><input type="number" name="quantity" min="0.01" step="0.01" required></div>
@if($selectedStore->code === 'building')<div class="field field--wide"><label for="mv-project">Construction project (when issuing)</label><select id="mv-project" name="construction_project_id"><option value="">Choose project</option>@foreach(\App\Http\Controllers\ConstructionController::activeProjects() as $project)<option value="{{ $project->id }}" @selected(old('construction_project_id') == $project->id)>{{ $project->name }}</option>@endforeach</select><span class="field-hint">Materials issued from this store must go to a project.</span></div>@else<div class="field field--wide"><label>Poultry house (when issued to or returned from a house)</label><select name="poultry_house_id"><option value="">Not connected to a poultry house</option>@foreach($houses as $house)<option value="{{ $house->id }}">House {{ $house->displayNumber() }}{{ $house->batch ? ' · '.$house->batch->code : '' }}</option>@endforeach</select><span class="field-hint">Choose the house for feeders, drinkers, tools, medicine, or any other item assigned there.</span></div>@endif
@if($showValue)<div class="field"><label>Unit cost (UGX)</label><input type="number" name="unit_cost" min="0" step="0.01"></div><div class="field"><label>Total cost (UGX)</label><input type="number" name="total_cost" min="0" step="0.01"></div>@endif
<div class="field"><label>{{ $storeAction==='farm_use' ? 'Department / taken by' : 'Supplier / recipient / unit' }}</label><input name="supplier_or_recipient" @required($storeAction==='farm_use')></div><div class="field"><label>Reference</label><input name="reference"></div><div class="field field--wide"><label>Reason / notes</label><textarea name="notes"></textarea></div><div class="form-actions">@unless($isStoreManager)<a class="btn btn--quiet" href="{{ $tabUrl('stock') }}">Cancel</a>@endunless<button class="btn">{{ $isStoreManager ? match($storeAction){'received'=>'Receive stock','farm_use'=>'Record farm consumption',default=>'Issue stock'} : 'Record movement' }}</button></div></form>@endif</section>

@elseif($screen==='houses')
<section class="exec-panel table-wrap"><div class="section-heading"><div><p class="eyebrow">House equipment register</p><h2>Items currently assigned to poultry houses</h2><p>Issues to a house, less items returned from that same house.</p></div><strong>{{ $houseIssues->count() }} allocations</strong></div><table class="exec-table"><thead><tr><th>House</th><th>Item</th><th>Quantity at house</th><th>Last movement</th></tr></thead><tbody>@forelse($houseIssues as $row)<tr><td><strong>House {{ preg_replace('/^house\s*/i','',$row->house_number) }}</strong></td><td>{{ $row->name }}</td><td><strong>{{ number_format((float)$row->quantity,2) }}</strong> {{ $row->unit }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->last_movement)->format('d M Y') }}</td></tr>@empty<tr><td colspan="4">No items have been issued to a poultry house from this store.</td></tr>@endforelse</tbody></table></section>

@elseif($screen==='item')
<section class="exec-panel inventory-form-panel"><div class="section-heading"><div><p class="eyebrow">Store catalogue</p><h2>Add an item</h2><p>Create an item once. Add its quantity afterwards under Record movement.</p></div></div><form method="POST" action="{{ route('inventory.items.store') }}" class="form-grid">@csrf<input type="hidden" name="inventory_store_id" value="{{ $selectedStore->id }}"><div class="field"><label>Item code <span class="req">*</span></label><input name="code" required placeholder="e.g. FOOD-001"></div><div class="field"><label>Item name <span class="req">*</span></label><input name="name" required></div><div class="field"><label>Category <span class="req">*</span></label><select name="category_choice">@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach<option value="custom">Other category</option></select></div><div class="field"><label>Other category</label><input name="custom_category"></div><div class="field"><label>Unit <span class="req">*</span></label><input name="unit" required placeholder="kg, litre, piece, bag"></div><div class="field"><label>Reorder at <span class="req">*</span></label><input type="number" name="reorder_level" min="0" step="0.01" value="0" required></div><div class="field"><label>Location inside store <span class="req">*</span></label><input name="default_location" value="{{ $selectedStore->name }}" required></div><div class="field field--wide"><label>Notes</label><textarea name="notes"></textarea></div><div class="form-actions"><a class="btn btn--quiet" href="{{ $tabUrl('stock') }}">Cancel</a><button class="btn">Add item</button></div></form></section>

@elseif($screen==='history')
<section class="exec-panel table-wrap"><div class="section-heading"><div><p class="eyebrow">Audit trail</p><h2>Movement history</h2><p>Every receipt, issue, use, transfer, loss, and adjustment in {{ $selectedStore->name }}.</p></div></div><table class="exec-table"><thead><tr><th>Date</th><th>Item</th><th>Movement</th><th>House / location</th><th>Quantity</th>@if($showValue)<th>Value</th>@endif<th>Reference</th><th>Recorded by</th></tr></thead><tbody>@forelse($movements as $row)<tr><td>{{ $row->movement_date->format('d M Y') }}</td><td>{{ $row->item->name }}</td><td>{{ ucwords(str_replace('_',' ',$row->movement_type)) }}@if($row->receipt_status!=='confirmed')<small class="data-sub">{{ ['pending'=>'Waiting for the store','awaiting_approval'=>'Waiting for Finance','rejected'=>'Rejected'][$row->receipt_status] ?? $row->receipt_status }}</small>@endif</td><td>{{ $row->poultryHouse ? 'House '.$row->poultryHouse->displayNumber() : $row->location }}</td><td>{{ number_format($row->quantity,2) }} {{ $row->item->unit }}</td>@if($showValue)<td>{{ $row->total_cost===null?'—':'UGX '.number_format($row->total_cost,2) }}</td>@endif<td>{{ $row->reference?:'—' }}</td><td>{{ $row->recorder?->displayName() }}</td></tr>@empty<tr><td colspan="{{ $showValue?8:7 }}">No movements recorded in this store.</td></tr>@endforelse</tbody></table>{{ $movements->links() }}</section>

@elseif($screen==='access' && $isDirector)
<section class="exec-panel u-m0-b14"><div class="section-heading"><div><p class="eyebrow">Director control</p><h2>Store access by person</h2><p>Give one person specific stores, for example a store manager who handles Feed and Veterinary only. A person with stores ticked here uses these instead of their role's stores; untick every View box to return them to their role's access.</p></div></div>
<form method="GET" action="{{ route('inventory.index') }}" class="form-grid"><input type="hidden" name="store" value="{{ $selectedStore->id }}"><input type="hidden" name="screen" value="access"><div class="field"><label>Person</label><select name="person" onchange="this.form.submit()"><option value="">Choose a person</option>@foreach($people as $person)<option value="{{ $person->id }}" @selected($accessPerson?->id === $person->id)>{{ $person->displayName() }} · {{ $person->role?->name ?? 'No role' }}@if($personAccess->has($person->id)) · {{ $personAccess->get($person->id)->count() }} assigned @endif</option>@endforeach</select></div></form>
@if($accessPerson)
@php($assigned = $personAccess->get($accessPerson->id, collect())->keyBy('inventory_store_id'))
<form method="POST" action="{{ route('inventory.store-access.person', $accessPerson) }}">@csrf @method('PUT')
<div class="table-wrap"><table class="exec-table"><thead><tr><th>Store</th><th>View</th><th>Receive</th><th>Issue/use</th><th>Adjust</th><th>See values</th></tr></thead><tbody>
@foreach($allStores as $store)@php($grant = $assigned->get($store->id))<tr><td>{{ $store->name }}</td>@foreach(['can_view','can_receive','can_issue','can_adjust','can_view_value'] as $right)<td><input type="checkbox" name="stores[{{ $store->id }}][{{ $right }}]" value="1" @checked($grant?->{$right} ?? false)></td>@endforeach</tr>@endforeach
</tbody></table></div>
<div class="form-actions"><button class="btn">Save {{ $accessPerson->displayName() }}'s stores</button></div>
</form>
@endif
</section>
<section class="exec-panel"><div class="section-heading"><div><p class="eyebrow">Director control</p><h2>Store access rights</h2><p>Users without View permission cannot see that store or its balances.</p></div></div><div class="table-wrap"><table class="exec-table"><thead><tr><th>Store</th><th>Role</th><th>View</th><th>Receive</th><th>Issue/use</th><th>Adjust</th><th>See values</th><th></th></tr></thead><tbody>@foreach($stores as $store)@foreach($roles as $role)@php($grant=$roleAccess->get($store->id.'-'.$role->id))<tr><form method="POST" action="{{ route('inventory.store-access.update',[$store,$role]) }}">@csrf @method('PUT')<td>{{ $store->name }}</td><td>{{ $role->name }}</td>@foreach(['can_view','can_receive','can_issue','can_adjust','can_view_value'] as $right)<td><input type="checkbox" name="{{ $right }}" value="1" @checked($grant?->{$right}??false)></td>@endforeach<td><button class="btn btn--small">Save</button></td></form></tr>@endforeach @endforeach</tbody></table></div></section>
@endif

@pushOnce('styles')<style>
.inv-tiles{grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px}
.inv-tiles .summary-card small{display:block;margin-top:4px}
.inv-tile--alarm strong{color:var(--fh-danger,#c0392b)!important}
.inv-tile--warn strong{color:var(--fh-warning,#a66a12)!important}
.inv-chips{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
.inv-chips a{padding:7px 14px;border-radius:999px;border:1px solid var(--fh-glass-border,#eee);background:var(--fh-glass,#fff);color:var(--fh-ink,#222);font-size:.88rem;font-weight:500;text-decoration:none}
.inv-chips a[aria-current="page"]{background:var(--fh-primary,#9b292d);border-color:var(--fh-primary,#9b292d);color:#fff}
.inv-tabs{margin:0 0 16px}
.inv-count{display:inline-grid;place-items:center;min-width:18px;height:18px;margin-left:4px;padding:0 5px;border-radius:99px;background:var(--fh-danger,#c0392b);color:#fff;font-size:.68rem;font-weight:700}
.badge--warn{background:var(--fh-warning-soft,#fbf0d8);color:var(--fh-warning,#a66a12)}
.inv-ledger-link{margin-left:8px;font-size:.8rem}
.inv-ledger--pending td{color:var(--fh-muted,#666);font-style:italic}
.inv-decide{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.inv-decide input{min-width:150px;padding:6px 8px;border:1px solid var(--fh-border,#ddd);border-radius:8px}
.inventory-form-panel{max-width:1050px;margin-inline:auto}.inventory-form-panel .form-actions{grid-column:1/-1}
</style>@endPushOnce
<script>(function(){var item=document.querySelector('[data-inventory-item]'),location=document.querySelector('[data-source-location]');item&&item.addEventListener('change',function(){var o=item.options[item.selectedIndex];if(o&&o.dataset.location)location.value=o.dataset.location});var type=document.querySelector('[data-movement-type]'),destination=document.querySelector('[data-destination]');function move(){var v=type?type.value:'';if(destination)destination.hidden=v!=='transfer';document.querySelectorAll('[data-welfare-recipient]').forEach(function(f){f.hidden=v!=='issued';var s=f.querySelector('[data-welfare-employee]');if(s)s.required=v==='issued'});document.querySelectorAll('[data-adjustment]').forEach(function(f){f.hidden=['wastage','adjustment_in','adjustment_out'].indexOf(v)===-1;f.querySelectorAll('select').forEach(function(s){if(s.name==='adjustment_reason')s.required=!f.hidden})});document.querySelectorAll('[data-receipt-batch]').forEach(function(f){f.hidden=v!=='received';f.querySelectorAll('input').forEach(function(i){i.required=!f.hidden})})}type&&type.addEventListener('change',move);move()})();</script>
@endsection
