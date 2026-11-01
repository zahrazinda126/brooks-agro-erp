@extends('layouts.app')
@section('title', 'Poultry Houses')
@section('content')
@php
    $show = in_array(request('show'), ['occupied', 'empty', 'inactive'], true) ? request('show') : 'all';
    $listed = match ($show) {
        'occupied' => $houses->whereNotNull('animal_group_id'),
        'empty'    => $houses->whereNull('animal_group_id')->where('is_active', true),
        'inactive' => $houses->where('is_active', false),
        default    => $houses,
    };
    $closeBtn = '<button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest(\'dialog\').close()">&times;</button>';
@endphp
<div class="page-head"><h2>Poultry Houses &amp; Workers</h2><p>Each worker can look after at most two houses.</p></div>
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<div class="screen-actions">
    <button type="button" class="btn btn--small" onclick="document.getElementById('transfer-birds').showModal()">Transfer birds</button>
    <button type="button" class="btn btn--small" onclick="document.getElementById('assign-worker').showModal()">Assign worker</button>
    <button type="button" class="btn btn--small btn--quiet" onclick="document.getElementById('add-house').showModal()">+ Add house</button>
</div>

<nav class="view-switch" aria-label="Show houses" data-keep-link>
    @php($counts = ['all' => $houses->count(), 'occupied' => $houses->whereNotNull('animal_group_id')->count(), 'empty' => $houses->whereNull('animal_group_id')->where('is_active', true)->count(), 'inactive' => $houses->where('is_active', false)->count()])
    @foreach(['all' => 'All', 'occupied' => 'Occupied', 'empty' => 'Empty', 'inactive' => 'Inactive'] as $key => $label)
        <a href="{{ route('poultry-houses.index', $key === 'all' ? [] : ['show' => $key]) }}" @if($show === $key) aria-current="page" @endif>{{ $label }} <span class="view-switch__count">{{ number_format($counts[$key]) }}</span></a>
    @endforeach
</nav>

<div class="card table-wrap">
<table>
    <thead><tr><th>House</th><th>Type</th><th>Batch</th><th>Birds</th><th>Workers</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($listed as $house)
        <tr>
            <td><strong>{{ $house->house_number }}</strong>@if($house->notes)<small class="table-sub">{{ $house->notes }}</small>@endif</td>
            <td>{{ ucfirst($house->house_type) }}</td>
            <td>{{ $house->batch?->code ?? '—' }}</td>
            <td>{{ number_format($house->bird_quantity) }}</td>
            <td>
                @forelse($house->activeAssignments as $assignment)
                    <div class="house-worker"><span>{{ $assignment->employee->fullName() }}</span><form method="POST" action="{{ route('poultry-houses.assignments.end', $assignment) }}" data-confirm="End {{ $assignment->employee->fullName() }}'s assignment to {{ $house->house_number }}?">@csrf<button class="btn btn--quiet btn--small" title="End assignment">End</button></form></div>
                @empty
                    <span class="muted">None</span>
                @endforelse
            </td>
            <td>@if(!$house->is_active)<x-status-pill status="inactive" label="Inactive" />@elseif($house->animal_group_id)<x-status-pill status="completed" label="Occupied" />@else<x-status-pill status="pending" label="Empty" />@endif</td>
            <td>
                <button type="button" class="btn btn--quiet btn--small" onclick="document.getElementById('edit-house-{{ $house->id }}').showModal()">Edit</button>
                <dialog id="edit-house-{{ $house->id }}" class="page-dialog" aria-label="Edit house">{!! $closeBtn !!}<h3>Edit {{ $house->house_number }}</h3>
                    <form method="POST" action="{{ route('poultry-houses.update', $house) }}">@csrf @method('PUT')<input type="hidden" name="_dialog" value="edit-house-{{ $house->id }}">
                        <div class="form-grid">
                            <div class="field"><label>House number</label><input name="house_number" value="{{ $house->house_number }}" required></div>
                            <div class="field"><label>House type</label><select name="house_type" required><option value="standard" @selected($house->house_type === 'standard')>Standard</option><option value="brooder" @selected($house->house_type === 'brooder')>Brooder</option></select></div>
                            <div class="field field--wide"><label>Notes</label><input name="notes" value="{{ $house->notes }}"></div>
                            <div class="field"><label><input type="checkbox" name="is_active" value="1" @checked($house->is_active)> Active house</label></div>
                        </div>
                        <p class="field-hint">Bird numbers change only through <em>Transfer birds</em>, so the movement history stays complete.</p>
                        <div class="form-actions"><button class="btn">Save</button></div>
                    </form>
                </dialog>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No houses in this view.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<dialog id="transfer-birds" class="page-dialog" aria-label="Transfer birds">{!! $closeBtn !!}<h3>Transfer birds between houses</h3>
<form method="POST" action="{{ route('poultry-houses.transfer') }}">@csrf<input type="hidden" name="_dialog" value="transfer-birds">
    <div class="form-grid">
        <div class="field"><label>From house</label><select name="from_house_id" required><option value="">Choose occupied house</option>@foreach($houses->whereNotNull('animal_group_id') as $house)<option value="{{ $house->id }}" @selected(old('from_house_id') == $house->id)>{{ $house->house_number }} · {{ $house->batch?->code }} · {{ number_format($house->bird_quantity) }} birds</option>@endforeach</select></div>
        <div class="field"><label>To house</label><select name="to_house_id" required><option value="">Choose destination</option>@foreach($houses->where('is_active', true) as $house)<option value="{{ $house->id }}" @selected(old('to_house_id') == $house->id)>{{ $house->house_number }} · {{ ucfirst($house->house_type) }} · {{ $house->batch?->code ?? 'Empty' }}</option>@endforeach</select></div>
        <div class="field"><label>Birds to move</label><input type="number" min="1" name="bird_quantity" value="{{ old('bird_quantity') }}" required></div>
        <div class="field"><label>Movement date</label><input type="date" name="moved_on" value="{{ old('moved_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        <div class="field field--wide"><label>Reason</label><input name="reason" value="{{ old('reason') }}" placeholder="End of brooding, balancing houses..."></div>
    </div>
    <div class="form-actions"><button class="btn">Transfer birds</button></div>
</form></dialog>

<dialog id="assign-worker" class="page-dialog" aria-label="Assign worker">{!! $closeBtn !!}<h3>Assign worker to a house</h3>
<form method="POST" action="{{ route('poultry-houses.assign') }}">@csrf<input type="hidden" name="_dialog" value="assign-worker">
    <div class="form-grid">
        <div class="field"><label>Worker</label><select name="employee_id" required><option value="">Choose worker</option>@foreach($workers as $worker)<option value="{{ $worker->id }}" @selected(old('employee_id') == $worker->id)>{{ $worker->fullName() }}</option>@endforeach</select></div>
        <div class="field"><label>House</label><select name="poultry_house_id" required><option value="">Choose house</option>@foreach($houses->where('is_active', true) as $house)<option value="{{ $house->id }}" @selected(old('poultry_house_id') == $house->id)>{{ $house->house_number }} · {{ $house->batch?->code ?? 'Empty' }}</option>@endforeach</select></div>
        <div class="field"><label>Assigned from</label><input type="date" name="assigned_from" value="{{ old('assigned_from', now()->toDateString()) }}" required></div>
    </div>
    <div class="form-actions"><button class="btn">Assign</button></div>
</form></dialog>

<dialog id="add-house" class="page-dialog" aria-label="Add house">{!! $closeBtn !!}<h3>Add poultry house</h3>
<form method="POST" action="{{ route('poultry-houses.store') }}">@csrf<input type="hidden" name="_dialog" value="add-house">
    <div class="form-grid">
        <div class="field"><label>House number</label><input name="house_number" value="{{ old('house_number') }}" required></div>
        <div class="field"><label>House type</label><select name="house_type" required><option value="standard">Standard poultry house</option><option value="brooder" @selected(old('house_type') === 'brooder')>Brooder</option></select></div>
        <div class="field field--wide"><label>Notes</label><input name="notes" value="{{ old('notes') }}"></div>
    </div>
    <p class="field-hint">A new house starts empty. Birds enter through a batch allocation or a transfer.</p>
    <div class="form-actions"><button class="btn">Add house</button></div>
</form></dialog>

<script>
(() => {
    // Reopen the form after a failed save.
    const open = @json(old('_dialog'));
    const dialog = open && document.getElementById(open);
    if (dialog && dialog.showModal) dialog.showModal();
})();
</script>
@endsection
