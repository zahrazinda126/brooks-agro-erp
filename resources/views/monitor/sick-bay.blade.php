@extends('layouts.app')
@section('title', 'Sick Bay')
@section('content')
@include('partials.monitor-group', ['group' => 'health', 'current' => 'sickbay'])

@if($canRecord)
<div class="screen-actions">
    <button type="button" class="btn btn--small" onclick="document.getElementById('sb-admit').showModal()">+ Take into sick bay</button>
    <button type="button" class="btn btn--small btn--quiet" onclick="document.getElementById('sb-new').showModal()">+ New sick bay</button>
</div>

<dialog id="sb-admit" class="page-dialog" aria-label="Take animals into a sick bay"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Take into sick bay</h3>
<form method="POST" action="{{ route('sick-bay.admit') }}" id="sb-admit-form">@csrf<input type="hidden" name="_dialog" value="sb-admit">
    <div class="form-grid">
        <div class="field"><label for="sb-bay">Sick bay</label><select id="sb-bay" name="sick_bay_id" required><option value="">Choose a sick bay</option>
            @foreach($bays->groupBy('unit_code') as $unit => $list)<optgroup label="{{ $units[$unit] ?? ucfirst($unit) }}">@foreach($list as $bay)<option value="{{ $bay->id }}" data-unit="{{ $unit }}" @selected(old('sick_bay_id') == $bay->id)>{{ $bay->name }}</option>@endforeach</optgroup>@endforeach
        </select></div>
        <div class="field"><label for="sb-source">From</label><select id="sb-source" name="source" required><option value="">Choose the sick bay first</option>
            @foreach($sources as $unit => $list)@foreach($list as $value => $label)<option value="{{ $value }}" data-unit="{{ $unit }}" @selected(old('source') === $value)>{{ $label }}</option>@endforeach @endforeach
        </select></div>
        <div class="field"><label for="sb-qty">How many</label><input id="sb-qty" type="number" name="quantity" min="1" value="{{ old('quantity') }}" required></div>
        <div class="field"><label for="sb-date">Date</label><input id="sb-date" type="date" name="admitted_on" value="{{ old('admitted_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        <div class="field field--wide"><label>Signs</label><div class="choice-row">@foreach(\App\Models\SickBayAdmission::SIGNS as $sign)<label class="check-label"><input type="checkbox" name="signs[]" value="{{ $sign }}" @checked(in_array($sign, old('signs', []), true))> {{ $sign }}</label>@endforeach</div></div>
        <div class="field field--wide"><label for="sb-condition">Suspected illness or injury <small>(optional)</small></label><input id="sb-condition" name="condition" maxlength="160" value="{{ old('condition') }}" placeholder="e.g. Respiratory infection"></div>
        <div class="field field--wide"><label for="sb-notes">Notes <small>(optional)</small></label><textarea id="sb-notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></div>
    </div>
    <p class="field-hint">Poultry: the birds leave their house but stay in the batch. Recovered birds go back to their house.</p>
    <div class="form-actions"><button class="btn">Take in</button></div>
</form></dialog>

<dialog id="sb-new" class="page-dialog" aria-label="New sick bay"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>New sick bay</h3>
<form method="POST" action="{{ route('sick-bay.bays.store') }}">@csrf<input type="hidden" name="_dialog" value="sb-new">
    <div class="form-grid">
        <div class="field"><label for="sbn-unit">Unit</label><select id="sbn-unit" name="unit_code" required>@foreach(\App\Models\SickBay::UNITS as $key => $label)<option value="{{ $key }}" @selected(old('unit_code', $filters['unit'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="sbn-name">Name</label><input id="sbn-name" name="name" maxlength="80" value="{{ old('name') }}" required placeholder="e.g. Sick bay 2 (behind House 4)"></div>
        <div class="field field--wide"><label for="sbn-notes">Notes <small>(optional)</small></label><input id="sbn-notes" name="notes" maxlength="1000" value="{{ old('notes') }}"></div>
    </div>
    <div class="form-actions"><button class="btn">Add sick bay</button></div>
</form></dialog>
@endif

@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<form method="GET" action="{{ route('sick-bay.index') }}" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="sbf-unit">Unit</label><select id="sbf-unit" name="unit" onchange="this.form.sick_bay.value=''; this.form.submit()"><option value="">All units</option>@foreach($units as $key => $label)<option value="{{ $key }}" @selected(($filters['unit'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="sbf-bay">Sick bay</label><select id="sbf-bay" name="sick_bay"><option value="">All sick bays</option>@foreach($bays as $bay)@continue(($filters['unit'] ?? null) && $bay->unit_code !== $filters['unit'])<option value="{{ $bay->id }}" @selected((int) ($filters['sick_bay'] ?? 0) === $bay->id)>{{ $bay->name }}</option>@endforeach</select></div>
        <div class="field"><label for="sbf-status">Status</label><select id="sbf-status" name="status">@foreach(['in_care' => 'In care now', 'discharged' => 'Left the sick bay', 'all' => 'All'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="sbf-from">Taken in from</label><input id="sbf-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="field"><label for="sbf-to">To</label><input id="sbf-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('sick-bay.index') }}">Reset</a></div>
    </div>
    <p class="filter-row__note">{{ number_format($inCareCount) }} {{ $inCareCount === 1 ? 'animal' : 'animals' }} in care now{{ $inCareBays ? ' in '.$inCareBays.' '.\Illuminate\Support\Str::plural('sick bay', $inCareBays) : '' }}</p>
</form>

<div class="card table-wrap">
<table class="exec-table sick-bay-table">
    <thead><tr><th>Taken in</th><th>Sick bay</th><th>From</th><th>Signs / condition</th><th class="num">In</th><th class="num">Recovered</th><th class="num">Died</th><th class="num">Culled</th><th class="num">Still in care</th><th>Progress</th>@if($canRecord)<th></th>@endif</tr></thead>
    <tbody>
    @forelse($admissions as $a)
        @php $last = $a->checks->last(); @endphp
        <tr>
            <td>{{ $a->admitted_on->format('d M Y') }}<br><span class="muted">{{ $a->daysInCare() }} {{ $a->daysInCare() === 1 ? 'day' : 'days' }}</span></td>
            <td>{{ $a->sickBay->name }}<br><span class="muted">{{ $units[$a->unit_code] ?? ucfirst($a->unit_code) }}</span></td>
            <td>{{ $a->fromLabel() }}</td>
            <td>{{ $a->condition ?: '—' }}@if($a->signs)<br><span class="muted">{{ implode(', ', $a->signs) }}</span>@endif</td>
            <td class="num">{{ number_format($a->quantity) }}</td>
            <td class="num">{{ number_format($a->recovered) }}</td>
            <td class="num">{{ number_format($a->died) }}</td>
            <td class="num">{{ number_format($a->culled) }}</td>
            <td class="num"><strong>{{ number_format($a->in_care) }}</strong></td>
            <td>
                @if($a->status === 'discharged')<x-status-pill status="completed" label="Left {{ $a->discharged_on?->format('d M') }}" />
                @elseif($last)<x-status-pill :status="$last->progress === 'worse' ? 'overdue' : ($last->progress === 'improving' ? 'active' : 'pending')" :label="\App\Models\SickBayCheck::PROGRESS[$last->progress]" />
                @else<span class="muted">Not checked yet</span>@endif
                @if($a->checks->isNotEmpty())
                <details class="sick-bay-log"><summary>Daily log ({{ $a->checks->count() }})</summary>
                    <ul>@foreach($a->checks as $c)<li><strong>{{ $c->checked_on->format('d M') }}</strong> · {{ \App\Models\SickBayCheck::PROGRESS[$c->progress] }}@if($c->recovered) · {{ $c->recovered }} recovered @endif @if($c->died) · {{ $c->died }} died @endif @if($c->culled) · {{ $c->culled }} culled @endif · {{ $c->still_sick }} still sick @if($c->treatment)<br><span class="muted">Treatment: {{ $c->treatment }}</span>@endif @if($c->notes)<br><span class="muted">{{ $c->notes }}</span>@endif <br><span class="muted">{{ $c->recorder?->displayName() }}</span></li>@endforeach</ul>
                </details>
                @endif
            </td>
            @if($canRecord)<td>@if($a->status === 'in_care')<button type="button" class="btn btn--small" onclick="document.getElementById('sb-check-{{ $a->id }}').showModal()">Update</button>@endif</td>@endif
        </tr>
    @empty
        <tr><td colspan="{{ $canRecord ? 11 : 10 }}" class="muted">{{ $filters['status'] === 'in_care' ? 'No animals in a sick bay right now.' : 'Nothing found for these filters.' }}</td></tr>
    @endforelse
    </tbody>
</table>
{{ $admissions->links() }}
</div>

@if($canRecord)
@foreach($admissions->where('status', 'in_care') as $a)
<dialog id="sb-check-{{ $a->id }}" class="page-dialog" aria-label="Update sick bay progress"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button>
<h3>{{ $a->sickBay->name }} · {{ $a->fromLabel() }}</h3>
<p class="muted">{{ number_format($a->in_care) }} still in care since {{ $a->admitted_on->format('d M Y') }}{{ $a->condition ? ' · '.$a->condition : '' }}</p>
<form method="POST" action="{{ route('sick-bay.check', $a) }}">@csrf<input type="hidden" name="_dialog" value="sb-check-{{ $a->id }}">
    <div class="form-grid">
        <div class="field"><label for="sbc-date-{{ $a->id }}">Date</label><input id="sbc-date-{{ $a->id }}" type="date" name="checked_on" value="{{ now()->toDateString() }}" min="{{ $a->admitted_on->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
        <div class="field"><label for="sbc-progress-{{ $a->id }}">How are they doing?</label><select id="sbc-progress-{{ $a->id }}" name="progress" required>@foreach(\App\Models\SickBayCheck::PROGRESS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="sbc-rec-{{ $a->id }}">Recovered <small>(back to {{ $a->unit_code === 'poultry' ? 'house' : 'group' }})</small></label><input id="sbc-rec-{{ $a->id }}" type="number" name="recovered" min="0" max="{{ $a->in_care }}" value="0"></div>
        <div class="field"><label for="sbc-died-{{ $a->id }}">Died</label><input id="sbc-died-{{ $a->id }}" type="number" name="died" min="0" max="{{ $a->in_care }}" value="0"></div>
        <div class="field"><label for="sbc-cull-{{ $a->id }}">Culled</label><input id="sbc-cull-{{ $a->id }}" type="number" name="culled" min="0" max="{{ $a->in_care }}" value="0"></div>
        <div class="field field--wide"><label for="sbc-treat-{{ $a->id }}">Treatment given <small>(optional)</small></label><input id="sbc-treat-{{ $a->id }}" name="treatment" maxlength="255" placeholder="e.g. Tylosin in water, wound spray"></div>
        <div class="field field--wide"><label for="sbc-notes-{{ $a->id }}">Notes <small>(optional)</small></label><textarea id="sbc-notes-{{ $a->id }}" name="notes" maxlength="2000"></textarea></div>
    </div>
    <p class="field-hint">Deaths and culls are added to the deaths records for examination.</p>
    <div class="form-actions"><button class="btn">Save progress</button></div>
</form></dialog>
@endforeach
@endif

<script>
(() => {
    const o = @json(old('_dialog')); const d = o && document.getElementById(o); if (d && d.showModal) d.showModal();
    // In "Take into sick bay", list only the houses / groups of the chosen sick bay's unit.
    const bay = document.getElementById('sb-bay'), source = document.getElementById('sb-source');
    if (!bay || !source) return;
    const sync = () => {
        const unit = bay.selectedOptions[0]?.dataset.unit;
        source.querySelectorAll('option[data-unit]').forEach(o => { o.hidden = o.dataset.unit !== unit; if (o.hidden && o.selected) source.value = ''; });
        source.options[0].textContent = unit ? 'Choose where they came from' : 'Choose the sick bay first';
    };
    bay.addEventListener('change', sync); sync();
})();
</script>
@endsection
