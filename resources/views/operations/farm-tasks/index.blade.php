@extends('layouts.app')
@section('title', 'Farm Tasks')
@section('content')
@php
    $closeBtn = '<button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest(\'dialog\').close()">&times;</button>';
    $statusPill = ['submitted' => ['pending', 'Awaiting confirmation'], 'approved' => ['approved', 'Confirmed'], 'returned' => ['inactive', 'Returned']];
@endphp
<div class="screen-actions">
    <button type="button" class="btn btn--small btn--quiet" onclick="document.getElementById('add-task-type').showModal()">+ New task type</button>
    <button type="button" class="btn btn--small" onclick="document.getElementById('assign-task').showModal()">+ Assign task</button>
</div>
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<nav class="view-switch" aria-label="Farm tasks" data-keep-link>
    <a href="{{ route('farm-tasks.index') }}" @if($show === 'assignments') aria-current="page" @endif>Assigned tasks <span class="view-switch__count">{{ $assignments->where('active', true)->count() }}</span></a>
    <a href="{{ route('farm-tasks.index', ['show' => 'entries']) }}" @if($show === 'entries') aria-current="page" @endif>Work recorded <span class="view-switch__count">{{ $entries->where('verification_status', 'submitted')->count() }}</span></a>
</nav>

<div class="card table-wrap">
@if($show === 'assignments')
    <table class="exec-table">
        <thead><tr><th>Worker</th><th>Task</th><th>Unit</th><th>From</th><th>To</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($assignments as $a)
            <tr>
                <td><strong>{{ $a->worker_name }}</strong><small class="table-sub">{{ $a->worker_username }}</small></td>
                <td>{{ $a->task_name }}@if($a->note)<small class="table-sub">{{ $a->note }}</small>@endif</td>
                <td>{{ $units[$a->unit_code] ?? $a->unit_code }}</td>
                <td>{{ \Carbon\Carbon::parse($a->starts_on)->format('d M Y') }}</td>
                <td>{{ $a->ends_on ? \Carbon\Carbon::parse($a->ends_on)->format('d M Y') : 'Until ended' }}</td>
                <td>@if($a->active)<x-status-pill status="active" label="Active" />@elseif($a->ended_at)<x-status-pill status="inactive" label="Ended" />@elseif(\Carbon\Carbon::parse($a->starts_on)->isFuture())<x-status-pill status="pending" label="Starts later" />@else<x-status-pill status="inactive" label="Finished" />@endif</td>
                <td>@if(!$a->ended_at && (!$a->ends_on || !\Carbon\Carbon::parse($a->ends_on)->isPast() || \Carbon\Carbon::parse($a->ends_on)->isToday()))<form method="POST" action="{{ route('farm-tasks.end', $a->id) }}" data-confirm="End {{ $a->task_name }} for {{ $a->worker_name }}? It disappears from their account.">@csrf<button class="btn btn--quiet btn--small">End</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No tasks assigned yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    <table class="exec-table">
        <thead><tr><th>Date</th><th>Worker</th><th>Task</th><th>Unit</th><th class="num">Quantity</th><th>Details</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($entries as $e)
            <tr>
                <td>{{ \Carbon\Carbon::parse($e->done_on)->format('d M Y') }}</td>
                <td>{{ $e->worker_name }}</td>
                <td>{{ $e->task_name }}</td>
                <td>{{ $units[$e->unit_code] ?? $e->unit_code }}</td>
                <td class="num">{{ $e->quantity !== null ? number_format((float) $e->quantity, fmod((float) $e->quantity, 1) ? 2 : 0).' '.$e->measure : '—' }}</td>
                <td>{{ $e->details ?: '—' }}@if($e->verification_note)<small class="table-sub">Note: {{ $e->verification_note }}</small>@endif</td>
                <td><x-status-pill :status="$statusPill[$e->verification_status][0] ?? 'pending'" :label="$statusPill[$e->verification_status][1] ?? ucfirst($e->verification_status)" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No work recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div>

<dialog id="assign-task" class="page-dialog" aria-label="Assign task">{!! $closeBtn !!}<h3>Assign a paid task</h3>
<form method="POST" action="{{ route('farm-tasks.assign') }}">@csrf<input type="hidden" name="_dialog" value="assign-task">
    <div class="form-grid">
        <div class="field"><label for="ft-worker">Worker</label><select id="ft-worker" name="user_id" required><option value="">Choose worker</option>@foreach($workers as $w)<option value="{{ $w->id }}" @selected(old('user_id') == $w->id)>{{ $w->displayName() }}</option>@endforeach</select></div>
        <div class="field"><label for="ft-task">Task</label><select id="ft-task" name="farm_task_type_id" required><option value="">Choose task</option>@foreach($types->where('is_active', true) as $t)<option value="{{ $t->id }}" @selected(old('farm_task_type_id') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div class="field"><label for="ft-unit">Unit</label><select id="ft-unit" name="unit_code" required>@foreach($units as $key => $label)<option value="{{ $key }}" @selected(old('unit_code') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="ft-from">From</label><input id="ft-from" type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required></div>
        <div class="field"><label for="ft-to">To <small>(optional)</small></label><input id="ft-to" type="date" name="ends_on" value="{{ old('ends_on') }}"></div>
        <div class="field field--wide"><label for="ft-note">Note</label><input id="ft-note" name="note" value="{{ old('note') }}" maxlength="1000"></div>
    </div>
    <p class="field-hint">The task appears in the worker's account (My Work) while it lasts. Managers are not listed.</p>
    <div class="form-actions"><button class="btn">Assign</button></div>
</form></dialog>

<dialog id="add-task-type" class="page-dialog" aria-label="New task type">{!! $closeBtn !!}<h3>New task type</h3>
<form method="POST" action="{{ route('farm-tasks.types.store') }}">@csrf<input type="hidden" name="_dialog" value="add-task-type">
    <div class="form-grid">
        <div class="field field--wide"><label for="tt-name">Task name</label><input id="tt-name" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Pen cleaning"></div>
        <div class="field"><label for="tt-product">Produces for sale <small>(optional)</small></label><select id="tt-product" name="sale_product_id"><option value="">Nothing for sale</option>@foreach($products as $p)<option value="{{ $p->id }}" data-unit="{{ $p->unit }}" @selected(old('sale_product_id') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div class="field"><label for="tt-measure">Measured in</label><input id="tt-measure" name="measure" value="{{ old('measure') }}" maxlength="40" placeholder="kg, bags…"></div>
    </div>
    <p class="field-hint">Only tasks that produce something for sale (like manure) ask the worker for a quantity, which is added to the Sales inventory once confirmed.</p>
    <div class="form-actions"><button class="btn">Add task type</button></div>
</form></dialog>

@if($types->isNotEmpty())
<details class="card disclosure u-mt-12"><summary><span><strong>Task types</strong><small>{{ $types->count() }} task(s)</small></span></summary><div class="disclosure__body">
<table class="exec-table"><thead><tr><th>Task</th><th>Produces for sale</th></tr></thead><tbody>
@foreach($types as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->product_name ? $t->product_name.' ('.$t->measure.')' : '—' }}</td></tr>@endforeach
</tbody></table></div></details>
@endif

<script>
(() => {
    const open = @json(old('_dialog'));
    const dialog = open && document.getElementById(open);
    if (dialog && dialog.showModal) dialog.showModal();
    // Choosing a product fills its measure.
    const product = document.getElementById('tt-product'), measure = document.getElementById('tt-measure');
    if (product) product.addEventListener('change', () => { const o = product.options[product.selectedIndex]; if (o.dataset.unit && !measure.value) measure.value = o.dataset.unit; });
})();
</script>
@endsection
