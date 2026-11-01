@extends('layouts.app')
@section('title', 'Feed Mixing')
@section('content')
@php
    $kg = fn ($v) => \App\Support\MixedFeedStock::kg((float) $v);
@endphp
@if($canMix)<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('mix-dialog').showModal()" data-bar-action>+ Record mixing</button></div>@endif
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

{{-- Mixed feed on its way to this person's stores. --}}
@if($toReceive->isNotEmpty())
<section class="card mix-receive">
    <h3>Mixed feed to receive</h3>
    @foreach($toReceive as $d)
    <form method="POST" action="{{ route('operations.feed-mixing.receive', $d->id) }}" class="mix-receive__row">@csrf
        <div><strong>{{ $d->store }}</strong><small>{{ $d->reference }} · mixed {{ \Carbon\Carbon::parse($d->mixed_on)->format('d M Y') }} · {{ $kg($d->sent_kg) }} kg sent</small></div>
        <div class="field"><label for="rk-{{ $d->id }}">Kg that arrived</label><input id="rk-{{ $d->id }}" type="number" name="received_kg" min="0" step="0.1" value="{{ $d->sent_kg + 0 }}" required></div>
        <div class="field"><label for="rn-{{ $d->id }}">Note</label><input id="rn-{{ $d->id }}" name="receive_note" placeholder="Optional"></div>
        <button class="btn btn--small">Received</button>
    </form>
    @endforeach
</section>
@endif

{{-- Stores holding mixed feed, their balance and the houses they feed. --}}
<section class="card table-card">
    <h3>Mixed feed in the stores</h3>
    <table><thead><tr><th>Store</th><th class="num">Mixed feed in store</th><th>Houses it feeds</th></tr></thead><tbody>
    @forelse($stores as $s)<tr><td><strong>{{ $s->name }}</strong></td><td class="num"><strong>{{ $kg($s->balance_kg) }} kg</strong></td><td>{{ $s->houses ? implode(', ', $s->houses) : 'No houses assigned' }}</td></tr>
    @empty<tr><td colspan="3" class="empty">No store holds mixed feed yet.@if($canManage) Add the stores below.@endif</td></tr>@endforelse
    </tbody></table>
    @if($canManage)
    <details class="mix-setup">
        <summary>Stores and houses</summary>
        <form method="POST" action="{{ route('operations.feed-mixing.stores') }}" class="filter-row">@csrf
            <div class="field"><label for="ms-name">Add a store that receives mixed feed</label><input id="ms-name" name="name" required placeholder="e.g. Poultry Store A (Houses 1–3)"></div>
            <div class="filter-row__actions"><button class="btn btn--small">Add store</button></div>
        </form>
        @if($stores->isNotEmpty())
        <form method="POST" action="{{ route('operations.feed-mixing.houses') }}">@csrf
            <table><thead><tr><th>House</th><th>Gets mixed feed from</th></tr></thead><tbody>
            @foreach($houses as $h)<tr><td>{{ $h->house_number }}</td><td><select name="house_store[{{ $h->id }}]" aria-label="Store for {{ $h->house_number }}"><option value="">Not assigned</option>@foreach($stores as $s)<option value="{{ $s->id }}" @selected($h->feed_store_id == $s->id)>{{ $s->name }}</option>@endforeach</select></td></tr>@endforeach
            </tbody></table>
            <div class="form-actions"><button class="btn btn--small">Save house stores</button></div>
        </form>
        @endif
    </details>
    @endif
</section>

{{-- Every mixing: ingredients taken, feed made, where it went and what arrived. --}}
<form method="GET" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="mx-from">From</label><input id="mx-from" type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label for="mx-to">To</label><input id="mx-to" type="date" name="to" value="{{ $to }}"></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('operations.feed-mixing.index') }}">Reset</a></div>
    </div>
</form>
<section class="card table-card">
    <table><thead><tr><th>Date</th><th>Mixing</th><th>Ingredients taken</th><th class="num">Mixed feed made</th><th>Sent to</th><th>Arrived</th></tr></thead><tbody>
    @forelse($mixings as $m)
        @php
            $rows = $dispatches->get($m->id, collect());
        @endphp
        <tr>
            <td class="u-nowrap">{{ \Carbon\Carbon::parse($m->mixed_on)->format('d M Y') }}</td>
            <td><strong>{{ $m->reference }}</strong><small class="table-sub">Taken by {{ $m->taken_by }} · recorded by {{ $m->recorder }}</small></td>
            <td>{{ $ingredients->get($m->id, collect())->map(fn ($i) => $i->feed_name.' '.$kg($i->quantity_kg).' kg')->implode(', ') }}<small class="table-sub">Total {{ $kg($ingredients->get($m->id, collect())->sum('quantity_kg')) }} kg</small></td>
            <td class="num"><strong>{{ $kg($m->mixed_kg) }} kg</strong></td>
            <td>@foreach($rows as $d){{ $d->store }}: {{ $kg($d->sent_kg) }} kg<br>@endforeach</td>
            <td>@foreach($rows as $d)@if($d->status === 'received')<span class="{{ $d->sent_kg - $d->received_kg > 0.004 ? 'text-alarm' : '' }}">{{ $kg($d->received_kg) }} kg{{ $d->sent_kg - $d->received_kg > 0.004 ? ' ('.$kg($d->sent_kg - $d->received_kg).' kg short)' : '' }}</span><small class="table-sub">{{ $d->receiver }}{{ $d->receive_note ? ' · '.$d->receive_note : '' }}</small>@else<span class="status-pill">On the way</span><br>@endif @endforeach</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No feed was mixed in these dates.</td></tr>
    @endforelse
    </tbody></table>
</section>

@if($canMix)
<dialog id="mix-dialog" class="page-dialog" aria-label="Record mixing"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button>
    <h3>Record mixing</h3>
    <form method="POST" action="{{ route('operations.feed-mixing.store') }}" id="mix-form">@csrf
        <div class="form-grid">
            <div class="field"><label for="mx-on">Date</label><input id="mx-on" type="date" name="mixed_on" value="{{ old('mixed_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
            <div class="field"><label for="mx-by">Taken from the store by</label><input id="mx-by" name="taken_by" value="{{ old('taken_by') }}" list="mix-people" required placeholder="Name"><datalist id="mix-people">@foreach($people as $p)<option value="{{ $p }}">@endforeach</datalist></div>
        </div>
        <h4 class="u-mt-16">Ingredients taken for mixing</h4>
        <table class="mix-table"><thead><tr><th>Ingredient</th><th class="num">In feed store</th><th>Kg taken</th></tr></thead><tbody>
        @foreach($feeds as $feed)<tr><td>{{ $feed }}</td><td class="num">{{ $kg($balances[$feed] ?? 0) }} kg</td><td><input type="number" name="ingredients[{{ $feed }}]" value="{{ old('ingredients.'.$feed) }}" min="0" step="0.1" data-ingredient aria-label="Kg of {{ $feed }} taken"></td></tr>@endforeach
        <tr class="report-total-row"><td colspan="2"><strong>Total taken</strong></td><td><strong id="mix-taken">0</strong> kg</td></tr>
        </tbody></table>
        <div class="field u-mt-16"><label for="mx-made">Mixed feed made (kg)</label><input id="mx-made" type="number" name="mixed_kg" value="{{ old('mixed_kg') }}" min="1" step="0.1" required></div>
        <h4 class="u-mt-16">Sent to the stores</h4>
        @if($stores->isEmpty())<p class="muted">No store holds mixed feed yet. Add one under "Stores and houses" first.</p>@endif
        <table class="mix-table"><tbody>
        @foreach($stores as $s)<tr><td>{{ $s->name }}<small class="table-sub">{{ $s->houses ? implode(', ', $s->houses) : 'No houses assigned' }}</small></td><td><input type="number" name="send[{{ $s->id }}]" value="{{ old('send.'.$s->id) }}" min="0" step="0.1" data-send aria-label="Kg sent to {{ $s->name }}"></td></tr>@endforeach
        <tr class="report-total-row"><td><strong>Total sent</strong> <span id="mix-check" class="muted"></span></td><td><strong id="mix-sent">0</strong> kg</td></tr>
        </tbody></table>
        <div class="field u-mt-16"><label for="mx-notes">Notes</label><textarea id="mx-notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
        <div class="form-actions"><button class="btn">Save mixing</button></div>
    </form>
</dialog>
<script>
(function () {
    var form = document.getElementById('mix-form'); if (!form) return;
    var sum = function (sel) { return Array.from(form.querySelectorAll(sel)).reduce(function (t, i) { return t + (parseFloat(i.value) || 0); }, 0); };
    var fmt = function (n) { return (Math.round(n * 10) / 10).toLocaleString(); };
    function refresh() {
        var made = parseFloat(document.getElementById('mx-made').value) || 0, sent = sum('[data-send]');
        document.getElementById('mix-taken').textContent = fmt(sum('[data-ingredient]'));
        document.getElementById('mix-sent').textContent = fmt(sent);
        document.getElementById('mix-check').textContent = made && Math.abs(made - sent) > 0.05 ? '(must add up to ' + fmt(made) + ' kg)' : '';
    }
    form.addEventListener('input', refresh); refresh();
    @if($errors->any() && old('mixed_kg') !== null) document.getElementById('mix-dialog').showModal(); @endif
})();
</script>
@endif
@pushOnce('styles')<style>.mix-receive__row{display:grid;grid-template-columns:minmax(200px,1.4fr) minmax(120px,.7fr) minmax(140px,1fr) auto;gap:10px;align-items:end;padding:10px 0;border-top:1px solid #eee3d8}.mix-receive__row small{display:block;color:#806b5d}.mix-receive__row .field{margin:0}.mix-setup{margin-top:12px}.mix-setup summary{cursor:pointer;font-weight:700;color:#8b1e2d}.mix-setup form{margin-top:10px}.mix-table{width:100%;border-collapse:collapse}.mix-table td,.mix-table th{padding:6px 8px;border-bottom:1px solid #eee3d8}.mix-table input{width:100%;min-height:36px;padding:6px 8px;border:1px solid #decfc1;border-radius:6px}@media(max-width:700px){.mix-receive__row{grid-template-columns:1fr}}</style>@endPushOnce
@endsection
