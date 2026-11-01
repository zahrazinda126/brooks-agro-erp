@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php
    $closeBtn = '<button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest(\'dialog\').close()">&times;</button>';
    $tab = in_array(request('tab'), ['materials', 'payments'], true) ? request('tab') : 'builders';
    $neededCost = $needed->sum('estimated_cost');
@endphp
<div class="page-head page-head--split"><div><h2>{{ $project->name }}</h2><p>{{ $project->location ?: 'No location given' }} · started {{ \Carbon\Carbon::parse($project->starts_on)->format('d M Y') }}@if($project->expected_end_on) · expected finish {{ \Carbon\Carbon::parse($project->expected_end_on)->format('d M Y') }}@endif</p></div>
@if($canManage)<form method="POST" action="{{ route('construction.finish', $project->id) }}">@csrf<button class="btn btn--quiet btn--small">{{ $project->status === 'finished' ? 'Reopen project' : 'Mark finished' }}</button></form>@endif</div>
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<nav class="view-switch" aria-label="Project" data-keep-link>
    <a href="{{ route('construction.show', $project->id) }}" @if($tab === 'builders') aria-current="page" @endif>Builders <span class="view-switch__count">{{ $workers->count() }}</span></a>
    <a href="{{ route('construction.show', [$project->id, 'tab' => 'materials']) }}" @if($tab === 'materials') aria-current="page" @endif>Materials</a>
    <a href="{{ route('construction.show', [$project->id, 'tab' => 'payments']) }}" @if($tab === 'payments') aria-current="page" @endif>Payments <span class="view-switch__count">{{ number_format($payments->sum('amount')) }}</span></a>
</nav>

@if($tab === 'builders')
    @if($canManage)<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('add-builder').showModal()">+ Add builder</button></div>@endif
    <div class="card table-wrap"><table class="exec-table">
        <thead><tr><th>Name</th><th>Role</th><th>Agreed pay</th><th>Period</th><th class="num">Paid (UGX)</th><th></th></tr></thead>
        <tbody>
        @forelse($workers as $w)
            <tr>
                <td><strong>{{ $w->first_name }} {{ $w->last_name }}</strong><small class="table-sub">{{ $w->staff_no }}{{ $w->phone ? ' · '.$w->phone : '' }}</small></td>
                <td>{{ ['lead' => 'Lead constructor', 'builder' => 'Builder', 'helper' => 'Helper'][$w->role] ?? 'Builder' }}</td>
                <td>@if($w->pay_basis === 'by_lead')<span class="muted">Paid by the lead</span>@else UGX {{ number_format($w->agreed_amount) }} {{ $w->pay_basis === 'day' ? 'per day' : 'for the job' }}@endif</td>
                <td>{{ $w->from_on ? \Carbon\Carbon::parse($w->from_on)->format('d M') : '—' }} – {{ $w->to_on ? \Carbon\Carbon::parse($w->to_on)->format('d M Y') : 'open' }}</td>
                <td class="num">@if($w->pay_basis === 'by_lead')—@else{{ number_format($w->paid) }}@endif @if($w->pay_basis === 'job')<small class="table-sub">{{ number_format(max(0, $w->agreed_amount - $w->paid)) }} left to pay</small>@endif</td>
                <td>@if($canManage)<form method="POST" action="{{ route('construction.workers.destroy', [$project->id, $w->id]) }}" data-confirm="Remove {{ $w->first_name }} from this project?">@csrf @method('DELETE')<button class="btn btn--quiet btn--small">Remove</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No one on this project yet. Use + Add builder to add the lead constructor and the team.</td></tr>
        @endforelse
        </tbody>
    </table></div>
@elseif($tab === 'materials')
    @if($canManage)<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('add-material').showModal()">+ Material needed</button></div>@endif
    <section class="card table-wrap"><h3>Materials needed</h3><table class="exec-table">
        <thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Estimated cost (UGX)</th><th>Notes</th><th></th></tr></thead>
        <tbody>
        @forelse($needed as $m)
            <tr><td><strong>{{ $m->item }}</strong></td><td class="num">{{ number_format((float) $m->quantity, fmod((float) $m->quantity, 1) ? 2 : 0) }} {{ $m->unit }}</td><td class="num">{{ $m->estimated_cost !== null ? number_format($m->estimated_cost) : '—' }}</td><td>{{ $m->notes ?: '—' }}</td>
            <td>@if($canManage)<form method="POST" action="{{ route('construction.materials.destroy', [$project->id, $m->id]) }}">@csrf @method('DELETE')<button class="btn btn--quiet btn--small">Remove</button></form>@endif</td></tr>
        @empty
            <tr><td colspan="5" class="muted">No materials listed yet.</td></tr>
        @endforelse
        </tbody>
        @if($needed->isNotEmpty())<tfoot><tr><th colspan="2">Estimated total</th><th class="num">{{ number_format($neededCost) }}</th><th colspan="2"></th></tr></tfoot>@endif
    </table></section>
    <section class="card table-wrap u-mt-12"><h3>Issued from the Building Materials Store</h3><table class="exec-table">
        <thead><tr><th>Date</th><th>Item</th><th class="num">Quantity</th><th class="num">Value (UGX)</th><th>Issued to</th></tr></thead>
        <tbody>
        @forelse($issued as $i)
            <tr><td>{{ \Carbon\Carbon::parse($i->movement_date)->format('d M Y') }}</td><td>{{ $i->item_name }}</td><td class="num">{{ number_format((float) $i->quantity, fmod((float) $i->quantity, 1) ? 2 : 0) }} {{ $i->item_unit }}</td><td class="num">{{ $i->total_cost !== null ? number_format($i->total_cost) : '—' }}</td><td>{{ $i->supplier_or_recipient ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Nothing issued yet. The Store Manager issues materials to this project from the Building Materials Store.</td></tr>
        @endforelse
        </tbody>
    </table></section>
@else
    @if($bills->isNotEmpty())
    <section class="card table-wrap"><h3>Supplier bills (bought on credit)</h3><table class="exec-table">
        <thead><tr><th>Date</th><th>Supplier</th><th>For</th><th class="num">Bill (UGX)</th><th class="num">Paid (UGX)</th><th class="num">Still owed (UGX)</th></tr></thead>
        <tbody>@foreach($bills as $bill)<tr><td>{{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}</td><td>{{ $bill->supplier_name }}</td><td>{{ $bill->description ?: $bill->item_name }}</td><td class="num">{{ number_format($bill->amount) }}</td><td class="num">{{ number_format($bill->amount_paid) }}</td><td class="num"><strong>{{ number_format(max(0, $bill->amount - $bill->amount_paid)) }}</strong></td></tr>@endforeach</tbody>
        <tfoot><tr><th colspan="5">Still owed to suppliers</th><th class="num">{{ number_format($bills->sum(fn ($b) => max(0, $b->amount - $b->amount_paid))) }}</th></tr></tfoot>
    </table></section>
    @endif
    <div class="card table-wrap u-mt-12"><h3>Payments made</h3><table class="exec-table">
        <thead><tr><th>Date</th><th>Paid to</th><th>For</th><th>Reference</th><th class="num">Amount (UGX)</th></tr></thead>
        <tbody>
        @forelse($payments as $pay)
            <tr><td>{{ \Carbon\Carbon::parse($pay->transaction_date)->format('d M Y') }}</td><td>{{ $pay->first_name ? $pay->first_name.' '.$pay->last_name : ($pay->counterparty ?: '—') }}</td><td>{{ $pay->entry_account }}<small class="table-sub">{{ $pay->description }}</small></td><td>{{ $pay->reference ?: '—' }}</td><td class="num">{{ number_format($pay->amount) }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">No payments yet. Finance records them in Data Entry &amp; Register and chooses this project.</td></tr>
        @endforelse
        </tbody>
        @if($payments->isNotEmpty())<tfoot><tr><th colspan="4">Total paid</th><th class="num">{{ number_format($payments->sum('amount')) }}</th></tr></tfoot>@endif
    </table></div>
@endif

@if($canManage)
<dialog id="add-builder" class="page-dialog" aria-label="Add to the project">{!! $closeBtn !!}<h3>Add to the project</h3>
<form method="POST" action="{{ route('construction.workers.store', $project->id) }}" id="add-builder-form">@csrf<input type="hidden" name="_dialog" value="add-builder">
    <div class="choice-row" role="radiogroup" aria-label="Who">
        <label class="check-label"><input type="radio" name="person" value="existing" @checked(old('person', 'existing') === 'existing')> Already registered</label>
        <label class="check-label"><input type="radio" name="person" value="new" @checked(old('person') === 'new')> New person</label>
    </div>
    <div class="form-grid u-mt-12">
        <div class="field field--wide" data-person="existing"><label for="cb-person">Person</label><select id="cb-person" name="employee_id"><option value="">Choose a registered person</option>
            @foreach($people as $person)<option value="{{ $person->id }}" @selected(old('employee_id') == $person->id)>{{ $person->first_name }} {{ $person->last_name }} · {{ $person->staff_no }}{{ $person->on_payroll ? '' : ' · temporary' }}</option>@endforeach
        </select></div>
        <div class="field" data-person="new"><label for="nb-first">First name</label><input id="nb-first" name="new_first_name" value="{{ old('new_first_name') }}" maxlength="80"></div>
        <div class="field" data-person="new"><label for="nb-last">Surname</label><input id="nb-last" name="new_last_name" value="{{ old('new_last_name') }}" maxlength="80"></div>
        <div class="field" data-person="new"><label for="nb-phone">Phone</label><input id="nb-phone" name="new_phone" type="tel" value="{{ old('new_phone') }}" maxlength="30"></div>
        <div class="field" data-person="new"><label for="nb-nin">National ID <small>(optional)</small></label><input id="nb-nin" name="new_national_id" value="{{ old('new_national_id') }}" maxlength="20"></div>
        <input type="hidden" name="new_role" id="nb-role-text" value="Builder"><input type="hidden" name="new_arrived_on" value="{{ now()->toDateString() }}">
        <div class="field"><label for="cb-role">Role</label><select id="cb-role" name="role" required>@foreach(['lead' => 'Lead constructor', 'builder' => 'Builder', 'helper' => 'Helper'] as $k => $label)<option value="{{ $k }}" @selected(old('role', 'builder') === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="field" data-lead><label for="cb-basis">Paid</label><select id="cb-basis" name="pay_basis"><option value="job" @selected(old('pay_basis') !== 'day')>For the whole job</option><option value="day" @selected(old('pay_basis') === 'day')>Per day</option></select></div>
        <div class="field" data-lead><label for="cb-amount">Agreed amount (UGX)</label><input id="cb-amount" type="number" min="0" step="1" name="agreed_amount" value="{{ old('agreed_amount') }}"></div>
        <div class="field"><label for="cb-from">From</label><input id="cb-from" type="date" name="from_on" value="{{ old('from_on', now()->toDateString()) }}"></div>
        <div class="field"><label for="cb-to">To</label><input id="cb-to" type="date" name="to_on" value="{{ old('to_on') }}"></div>
    </div>
    <p class="field-hint">The farm pays the lead constructor; builders and helpers are paid by the lead.</p>
    <p class="field-hint" data-person="new">HR will be asked to give them a room.</p>
    <div class="form-actions"><button class="btn">Add</button></div>
</form></dialog>
<script>
(() => {
    const form = document.getElementById('add-builder-form'); if (!form) return;
    const role = form.querySelector('#cb-role'), roleText = form.querySelector('#nb-role-text');
    const sync = () => {
        const isNew = form.querySelector('input[name="person"]:checked').value === 'new', isLead = role.value === 'lead';
        form.querySelectorAll('[data-person]').forEach(el => { el.hidden = el.dataset.person !== (isNew ? 'new' : 'existing'); el.querySelectorAll('input,select').forEach(i => { i.disabled = el.hidden; }); });
        ['nb-first', 'nb-last', 'nb-phone'].forEach(id => { form.querySelector('#' + id).required = isNew; });
        form.querySelector('#cb-person').required = !isNew;
        form.querySelectorAll('[data-lead]').forEach(el => { el.hidden = !isLead; el.querySelectorAll('input,select').forEach(i => { i.disabled = !isLead; }); });
        form.querySelector('#cb-amount').required = isLead;
        roleText.disabled = !isNew;
        roleText.value = {lead: 'Lead constructor', builder: 'Builder', helper: 'Helper'}[role.value];
    };
    form.addEventListener('change', sync); sync();
})();
</script>

<dialog id="add-material" class="page-dialog" aria-label="Material needed">{!! $closeBtn !!}<h3>Material needed</h3>
<form method="POST" action="{{ route('construction.materials.store', $project->id) }}">@csrf<input type="hidden" name="_dialog" value="add-material">
    <div class="form-grid">
        <div class="field field--wide"><label for="cm-item">Item</label><input id="cm-item" name="item" value="{{ old('item') }}" required maxlength="160" placeholder="e.g. Cement"></div>
        <div class="field"><label for="cm-qty">Quantity</label><input id="cm-qty" type="number" min="0.01" step="0.01" name="quantity" value="{{ old('quantity') }}" required></div>
        <div class="field"><label for="cm-unit">Unit</label><input id="cm-unit" name="unit" value="{{ old('unit') }}" maxlength="40" placeholder="bags, pieces…"></div>
        <div class="field"><label for="cm-cost">Estimated cost (UGX)</label><input id="cm-cost" type="number" min="0" step="1" name="estimated_cost" value="{{ old('estimated_cost') }}"></div>
        <div class="field field--wide"><label for="cm-notes">Notes</label><input id="cm-notes" name="notes" value="{{ old('notes') }}" maxlength="1000"></div>
    </div>
    <div class="form-actions"><button class="btn">Add to list</button></div>
</form></dialog>
@endif
<script>(() => { const o = @json(old('_dialog')); const d = o && document.getElementById(o); if (d && d.showModal) d.showModal(); })();</script>
@endsection
