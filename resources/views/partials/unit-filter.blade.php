{{--
    Unit filter for a Monitor task page. Choosing another unit opens that unit's page
    for the same task, keeping the dates. Shown only when the task covers more than one unit.
    Needs: $group, $task, $unit.
--}}
@php
    $unitLinks = \App\Support\MonitorTasks::unitLinks($user, $group, $task, ['from' => request('from'), 'to' => request('to')]);
@endphp
@if(count($unitLinks) > 1)
    <div class="field"><label for="filter-unit">Unit</label>
        <select id="filter-unit" onchange="if (this.value) window.location.href = this.value">
            @foreach($unitLinks as $key => $href)
                <option value="{{ $key === $unit ? '' : $href }}" @selected($key === $unit)>{{ \App\Support\MonitorTasks::UNITS[$key] }}</option>
            @endforeach
        </select>
    </div>
@endif
