<form class="card filter-row-card" method="GET" action="{{ route('vaccination-schedule.index') }}">
    <div class="filter-row">
        <div class="field"><label for="filter-show">Show</label><select id="filter-show" name="show" onchange="this.form.submit()">
            @php($showing = $view === 'records' ? 'given' : ($filters['schedule_status'] ?? 'all'))
            @foreach(['all' => 'All schedule items', 'active' => 'Active (still due)', 'finished' => 'Finished', 'inactive' => 'Inactive', 'given' => 'Vaccinations given'] as $value => $label)<option value="{{ $value }}" @selected($showing === $value)>{{ $label }}</option>@endforeach
        </select></div>
        @include('partials.unit-filter', ['group' => 'health', 'task' => 'vaccination', 'unit' => 'poultry'])
        @if($view === 'records')
        <div class="field"><label for="filter-from">From</label><input id="filter-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">@error('from')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="filter-to">To</label><input id="filter-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">@error('to')<p class="field-error">{{ $message }}</p>@enderror</div>
        @endif
        <div class="field"><label for="filter-batch">Batch</label><select id="filter-batch" name="batch_id"><option value="">All batches</option>@foreach($filterBatches as $batch)<option value="{{ $batch->id }}" @selected(($filters['batch_id'] ?? '') == $batch->id)>{{ $batch->code }} — {{ $batch->breed?->name ?? 'Breed not recorded' }}{{ $batch->status !== 'active' ? ' ('.Str::headline($batch->status).')' : '' }}</option>@endforeach</select></div>
        <div class="field"><label for="filter-vaccine">Vaccine</label><select id="filter-vaccine" name="vaccine_id"><option value="">All vaccines</option>@foreach($filterVaccines as $vaccine)<option value="{{ $vaccine->id }}" @selected(($filters['vaccine_id'] ?? '') == $vaccine->id)>{{ $vaccine->name }}{{ !$vaccine->is_active ? ' (inactive)' : '' }}</option>@endforeach</select></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('vaccination-schedule.index', $view === 'records' ? ['show' => 'given'] : []) }}">Reset</a></div>
    </div>

</form>
