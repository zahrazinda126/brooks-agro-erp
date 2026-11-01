@extends('layouts.app')
@section('title', 'Poultry Mortality')
@section('content')
@include('partials.monitor-group', ['group' => 'health', 'current' => 'deaths'])

{{-- Same layout as the goat / rabbit / dog mortality form. Saved through the poultry daily deaths entry. --}}
<form method="POST" id="poultryDeathsForm" class="exec-panel u-mt-12">@csrf
    <input type="hidden" name="from" value="monitor">
    <h3>Add mortality record</h3>
    @if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif
    <div class="form-grid">
        <div class="field"><label for="pd-house">House &amp; batch</label>
            <select id="pd-house" name="poultry_house_id" required>
                <option value="">Choose house</option>
                @foreach($houses as $house)
                    <option value="{{ $house->id }}" data-action="{{ route('daily.deaths.store', $house->animal_group_id) }}" @selected(old('poultry_house_id') == $house->id)>{{ $house->house_number }} · {{ $house->batch?->code }} · {{ number_format($house->bird_quantity) }} birds</option>
                @endforeach
            </select>
            @if($houses->isEmpty())<small class="field-hint">No house has birds in it yet.</small>@endif
        </div>
        <div class="field"><label for="pd-date">Date</label><input id="pd-date" type="date" value="{{ now()->toDateString() }}" readonly><small class="field-hint">Deaths are recorded for today.</small></div>
        <div class="field"><label for="pd-died">Number died</label><input id="pd-died" type="number" min="0" name="quantity_died" value="{{ old('quantity_died') }}" required></div>
        <div class="field"><label for="pd-culled">Number culled</label><input id="pd-culled" type="number" min="0" name="quantity_culled" value="{{ old('quantity_culled') }}"></div>
        <div class="field field--wide"><label for="pd-note">Suspected cause / notes</label><textarea id="pd-note" name="worker_note" maxlength="500">{{ old('worker_note') }}</textarea></div>
    </div>
    <p class="field-hint">A manager is notified to examine the cause.</p>
    <div class="form-actions"><button class="btn">Save record</button><a class="btn btn--quiet" href="{{ route('monitor.health', 'deaths') }}">Cancel</a></div>
</form>
<script>
(() => {
    // The deaths are saved against the batch in the chosen house.
    const form = document.getElementById('poultryDeathsForm');
    const house = document.getElementById('pd-house');
    if (!form || !house) return;
    const setAction = () => { const o = house.options[house.selectedIndex]; form.action = o && o.dataset.action ? o.dataset.action : ''; };
    house.addEventListener('change', setAction);
    form.addEventListener('submit', (e) => { setAction(); if (!form.action || form.action === location.href) { e.preventDefault(); house.focus(); } });
    setAction();
})();
</script>
@endsection
