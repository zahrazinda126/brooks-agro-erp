@extends('layouts.app')
@section('title','Stock requests')
@section('content')
<div class="page-head page-head--split">
    <div>
        <p class="eyebrow">Inventory</p>
        <h1>Stock requests</h1>
        <p>Request → Approval → Prepared → Dispatched → Received → Acknowledged. The head of your department approves; the store prepares and dispatches; you confirm what arrived.</p>
    </div>
    <button type="button" class="btn btn--inline" data-open-request>New request</button>
</div>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="notice notice--alarm"><strong>{{ $errors->first() }}</strong></div>@endif

<nav class="view-switch sr-filter" aria-label="Show" data-keep-link>
    @foreach(['mine'=>'Needs me','open'=>'Open','closed'=>'Closed','all'=>'All'] as $key=>$label)
        <a href="{{ route('inventory.requests.index',['show'=>$key]) }}" @if($filter===$key) aria-current="page" @endif>{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</nav>

{{-- New request --}}
<dialog class="sr-dialog" id="sr-new" @if($errors->any()) open @endif>
<form method="POST" action="{{ route('inventory.requests.store') }}" class="sr-form">@csrf
    <header><p class="eyebrow">Inventory</p><h2>New stock request</h2><button type="button" class="sr-close" data-close-request aria-label="Close">×</button></header>
    <div class="form-grid">
        <div class="field"><label for="sr-store">Store <span class="req">*</span></label><select id="sr-store" name="inventory_store_id" required data-sr-store><option value="">Choose store</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected(old('inventory_store_id')==$store->id)>{{ $store->name }}</option>@endforeach</select></div>
        <div class="field"><label for="sr-needed">Needed by</label><input id="sr-needed" type="date" name="needed_on" min="{{ today()->toDateString() }}" value="{{ old('needed_on') }}"></div>
        <div class="field field--wide"><label for="sr-purpose">What it is for <span class="req">*</span></label><input id="sr-purpose" name="purpose" maxlength="500" value="{{ old('purpose') }}" required placeholder="e.g. Vaccination of House 4 on Friday"></div>
    </div>
    <div class="sr-lines" data-sr-lines>
        @foreach(old('lines',[['inventory_item_id'=>'','quantity'=>'']]) as $i=>$line)
        <div class="sr-line" data-sr-line>
            <div class="field"><label>Item <span class="req">*</span></label><select name="lines[{{ $i }}][inventory_item_id]" required data-sr-item><option value="">Choose item</option>@foreach($items as $item)<option value="{{ $item->id }}" data-store="{{ $item->inventory_store_id }}" @selected(($line['inventory_item_id']??'')==$item->id)>{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
            <div class="field"><label>Quantity <span class="req">*</span></label><input type="number" name="lines[{{ $i }}][quantity]" min="0.01" step="0.01" value="{{ $line['quantity']??'' }}" required></div>
            <button type="button" class="btn btn--small btn--quiet" data-sr-remove aria-label="Remove item">Remove</button>
        </div>
        @endforeach
    </div>
    <button type="button" class="btn btn--small btn--quiet" data-sr-add>Add another item</button>
    <p class="field-hint">Your request goes to {{ $approverName(\App\Models\InventoryRequest::approverRoleFor($user)) }} for approval.</p>
    <footer class="form-actions"><button type="button" class="btn btn--quiet" data-close-request>Cancel</button><button class="btn">Send for approval</button></footer>
</form>
</dialog>

<div class="sr-list">
@forelse($list as $req)
    @php
        $step = $stepFor($req);
        $order = ['requested','approved','prepared','dispatched','received','acknowledged'];
        $reached = array_search($req->status, $order, true);
    @endphp
    <article class="card sr-card">
        <header class="sr-card__head">
            <div><strong>{{ $req->number }}</strong> · {{ $req->store?->name }}<small class="data-sub">By {{ $req->requester?->displayName() }} · {{ $req->created_at->format('d M Y, H:i') }}@if($req->needed_on) · needed by {{ $req->needed_on->format('d M Y') }}@endif</small></div>
            <x-status-pill :status="$req->status==='acknowledged'?'approved':(in_array($req->status,['rejected','cancelled'])?'rejected':'pending')" :label="$req->statusLabel()" />
        </header>
        <p class="sr-purpose">{{ $req->purpose }}</p>
        <ol class="sr-steps" aria-label="Progress">
            @foreach(['requested'=>'Requested','approved'=>'Approved','prepared'=>'Prepared','dispatched'=>'Dispatched','received'=>'Received','acknowledged'=>'Acknowledged'] as $key=>$label)
                @php($i = array_search($key, $order, true))
                <li @class(['is-done'=>$reached!==false && $i <= $reached, 'is-now'=>$reached!==false && $i === $reached + 1 && ! in_array($req->status,['rejected','cancelled'])])>{{ $label }}</li>
            @endforeach
        </ol>
        <div class="table-wrap"><table class="exec-table"><thead><tr><th>Item</th><th class="num">Requested</th><th class="num">Issued</th><th class="num">Received</th></tr></thead><tbody>
            @foreach($req->lines as $line)<tr><td>{{ $line->item?->name }}</td><td class="num">{{ number_format((float)$line->quantity_requested,2) }} {{ $line->item?->unit }}</td><td class="num">{{ $line->quantity_issued===null?'—':number_format((float)$line->quantity_issued,2) }}</td><td class="num">{{ $line->quantity_received===null?'—':number_format((float)$line->quantity_received,2) }}</td></tr>@endforeach
        </tbody></table></div>
        @if($req->decision_note)<p class="field-hint">{{ $req->status==='rejected'?'Reason':'Approver note' }}: {{ $req->decision_note }}</p>@endif
        @if($req->receipt_note)<p class="field-hint">On receipt: {{ $req->receipt_note }}</p>@endif

        {{-- The step that is this person's turn --}}
        @if($step==='approve')
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'approve']) }}" class="sr-action">@csrf<input name="decision_note" placeholder="Note (required to reject)" aria-label="Decision note"><button class="btn btn--small">Approve</button><button class="btn btn--small btn--quiet" formaction="{{ route('inventory.requests.step',[$req,'reject']) }}">Reject</button></form>
        @elseif($step==='prepare')
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'prepare']) }}" class="sr-action sr-action--grid">@csrf
                @foreach($req->lines as $line)<label>{{ $line->item?->name }}<input type="number" name="issue[{{ $line->id }}]" min="0" max="{{ (float)$line->quantity_requested }}" step="0.01" value="{{ (float)$line->quantity_requested }}"></label>@endforeach
                <button class="btn btn--small">Mark prepared</button>
            </form>
        @elseif($step==='dispatch')
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'dispatch']) }}" class="sr-action">@csrf<span class="field-hint">Dispatching takes the items out of the store (first to expire, first out).</span><button class="btn btn--small">Dispatch</button></form>
        @elseif($step==='receive')
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'receive']) }}" class="sr-action sr-action--grid">@csrf
                @foreach($req->lines as $line)@if((float)$line->quantity_issued > 0)<label>{{ $line->item?->name }} received<input type="number" name="received[{{ $line->id }}]" min="0" step="0.01" value="{{ (float)$line->quantity_issued }}"></label>@endif @endforeach
                <label class="sr-wide">Note (if something is missing or extra)<input name="receipt_note"></label>
                <button class="btn btn--small">Confirm received</button>
            </form>
        @elseif($step==='acknowledge')
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'acknowledge']) }}" class="sr-action">@csrf<button class="btn btn--small">Acknowledge and close</button></form>
        @elseif($req->status==='requested' && $req->requested_by===$user->id)
            <form method="POST" action="{{ route('inventory.requests.step',[$req,'cancel']) }}" class="sr-action">@csrf<span class="field-hint">Waiting for {{ $approverName($req->approver_role) }}.</span><button class="btn btn--small btn--quiet">Cancel request</button></form>
        @endif
    </article>
@empty
    <div class="card empty-state"><strong>{{ $filter==='mine' ? 'Nothing waiting for you' : 'No requests here' }}</strong><p>{{ $filter==='mine' ? 'Requests that need your step appear here.' : 'Use New request to ask a store for items.' }}</p></div>
@endforelse
</div>

@push('styles')<style>
.sr-filter{margin-bottom:16px}
.sr-list{display:grid;gap:12px}
.sr-card{display:grid;gap:10px;padding:16px 18px!important}
.sr-card__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.sr-purpose{margin:0;color:var(--fh-ink,#222)}
.sr-steps{display:flex;flex-wrap:wrap;gap:6px;margin:0;padding:0;list-style:none}
.sr-steps li{padding:4px 10px;border-radius:999px;background:var(--fh-glass-strong,#f5f5f5);border:1px solid var(--fh-border,#e5e5e5);font-size:.78rem;color:var(--fh-muted,#666)}
.sr-steps li.is-done{background:var(--fh-success-soft,#e2f2e7);border-color:transparent;color:var(--fh-success,#2f7d4f);font-weight:600}
.sr-steps li.is-now{background:var(--fh-primary-soft,#f7e4e1);border-color:transparent;color:var(--fh-primary,#9b292d);font-weight:600}
.sr-action{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.sr-action input{padding:7px 9px;border:1px solid var(--fh-border,#ddd);border-radius:8px;min-width:120px}
.sr-action--grid label{display:grid;gap:4px;font-size:.82rem;font-weight:600}
.sr-action--grid .sr-wide{flex:1 1 260px}
.sr-dialog{width:min(720px,calc(100vw - 32px));max-height:calc(100vh - 48px);padding:0;border:0;border-radius:16px;box-shadow:0 18px 48px rgba(30,20,20,.25)}
.sr-dialog::backdrop{background:rgba(30,22,25,.45)}
.sr-form{display:grid;gap:12px;padding:20px 22px}
.sr-form header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between}
.sr-form header .eyebrow{flex-basis:100%;margin:0}.sr-form h2{margin:0}
.sr-close{border:0;background:none;font-size:1.5rem;cursor:pointer;line-height:1}
.sr-lines{display:grid;gap:8px}
.sr-line{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto;gap:10px;align-items:end}
.sr-line .field{margin:0}
@media(max-width:560px){.sr-line{grid-template-columns:1fr}.sr-card__head{flex-direction:column}}
</style>@endpush
<script>
(function () {
    var dialog = document.getElementById('sr-new');
    document.querySelectorAll('[data-open-request]').forEach(function (b) { b.addEventListener('click', function () { dialog.showModal ? dialog.showModal() : dialog.setAttribute('open', ''); }); });
    document.querySelectorAll('[data-close-request]').forEach(function (b) { b.addEventListener('click', function () { dialog.close ? dialog.close() : dialog.removeAttribute('open'); }); });
    var store = document.querySelector('[data-sr-store]'), lines = document.querySelector('[data-sr-lines]');
    function filterItems() {
        lines.querySelectorAll('[data-sr-item]').forEach(function (select) {
            Array.from(select.options).forEach(function (o) { if (o.value) o.hidden = store.value && o.dataset.store !== store.value; });
            if (select.selectedOptions[0] && select.selectedOptions[0].hidden) select.value = '';
        });
    }
    function renumber() {
        lines.querySelectorAll('[data-sr-line]').forEach(function (line, i) {
            line.querySelectorAll('[name]').forEach(function (f) { f.name = f.name.replace(/lines\[\d+\]/, 'lines[' + i + ']'); });
        });
    }
    store.addEventListener('change', filterItems);
    document.querySelector('[data-sr-add]').addEventListener('click', function () {
        var copy = lines.querySelector('[data-sr-line]').cloneNode(true);
        copy.querySelectorAll('select, input').forEach(function (f) { f.value = ''; });
        lines.appendChild(copy); renumber(); filterItems();
    });
    lines.addEventListener('click', function (e) {
        if (!e.target.matches('[data-sr-remove]')) return;
        if (lines.querySelectorAll('[data-sr-line]').length > 1) { e.target.closest('[data-sr-line]').remove(); renumber(); }
    });
    filterItems();
})();
</script>
@endsection
