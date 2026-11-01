{{--
    Grouped Monitor pages (Production, Feeds Management, Health & Treatment).
    With $current = null it shows one card per task (the landing page);
    with a $current task key it shows "← Back" to those cards and where you are.
    The unit is chosen with the Unit filter on each task page (partials.unit-filter).
--}}
@php
    $groupTasks = \App\Support\MonitorTasks::visibleTasks($user, $group);
@endphp
@if($current === null)
    @if($groupTasks)
        <div class="group-choices" data-keep-link>
            @foreach($groupTasks as $key => [$label, $hint])
                @php($units = \App\Support\MonitorTasks::unitLinks($user, $group, $key))
                <a class="group-choice" href="{{ \App\Support\MonitorTasks::landing($user, $group, $key) }}"><strong>{{ $label }}</strong><small>{{ $hint }}</small><small class="group-choice__units">{{ collect(array_keys($units))->map(fn ($u) => \App\Support\MonitorTasks::UNITS[$u])->join(' · ') }}</small></a>
            @endforeach
        </div>
    @else
        <p class="muted">Nothing to monitor here for your account.</p>
    @endif
@else
    <nav class="group-trail" aria-label="{{ \App\Support\MonitorTasks::GROUPS[$group] }}" data-keep-link>
        <span class="group-trail__here">{{ \App\Support\MonitorTasks::tasks()[$group][$current][0] ?? '' }}</span>
    </nav>
@endif
