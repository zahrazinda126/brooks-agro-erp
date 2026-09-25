<form class="card" method="GET" action="{{ route('vaccination-schedule.index') }}">
    <p class="card-label">Filter vaccinations</p>
    <div class="form-grid">
        <div class="field"><label for="filter-from">Administered from</label><input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">@error('from')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="filter-to">Administered to</label><input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">@error('to')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="filter-batch">Batch</label><select id="filter-batch" name="batch_id"><option value="">All batches</option>@foreach($filterBatches as $batch)<option value="{{ $batch->id }}" @selected(($filters['batch_id'] ?? '') == $batch->id)>{{ $batch->code }} — {{ $batch->breed?->name ?? 'Breed not recorded' }}{{ $batch->status !== 'active' ? ' ('.Str::headline($batch->status).')' : '' }}</option>@endforeach</select></div>
        <div class="field"><label for="filter-vaccine">Vaccine</label><select id="filter-vaccine" name="vaccine_id"><option value="">All vaccines</option>@foreach($filterVaccines as $vaccine)<option value="{{ $vaccine->id }}" @selected(($filters['vaccine_id'] ?? '') == $vaccine->id)>{{ $vaccine->name }}{{ !$vaccine->is_active ? ' (inactive)' : '' }}</option>@endforeach</select></div>
        <div class="field"><label for="filter-status">Schedule status</label><select id="filter-status" name="schedule_status"><option value="">All statuses</option><option value="active" @selected(($filters['schedule_status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['schedule_status'] ?? '') === 'inactive')>Inactive</option></select></div>
    </div>
    <p class="field-hint">Dates filter completed vaccinations, including both selected dates. Batch and vaccine filter both tables. Batch schedules include applicable general programmes; status applies only to schedules.</p>
    <div class="form-actions"><button class="btn">Apply filters</button><a class="btn btn--quiet" href="{{ route('vaccination-schedule.index') }}">Reset filters</a></div>
    <p class="muted">{{ number_format($records->total()) }} vaccination records &middot; {{ number_format($plans->count()) }} schedule items match.</p>
</form>
