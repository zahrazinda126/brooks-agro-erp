{{--
    Farm Units › one unit: switch unit, then that unit's pages as tabs. Shown by layouts.app on any page
    opened with ?farmunit=…, so the unit's pages stay connected and the sidebar stays on Farm Units.
--}}
@php
    $unitNames = \App\Support\MonitorTasks::UNITS;
    $unitPages = \App\Support\MonitorTasks::unitPages($user, $farmUnit);
    $routeName = request()->route()?->getName();
    $here = request()->query() + (request()->route()?->parameters() ?? []);
    $isHere = fn ($route, $params) => $route === $routeName
        && collect($params)->every(fn ($value, $key) => (string) ($here[$key] ?? '') === (string) $value);
    // Only the closest match is current (Garden's "All activity" also matches its typed pages).
    $currentIndex = collect($unitPages)->filter(fn ($p) => $isHere($p[1], $p[2]))->sortByDesc(fn ($p) => count($p[2]))->keys()->first();
    $otherUnits = collect($unitNames)->filter(fn ($name, $code) => \App\Support\MonitorTasks::unitPages($user, $code) !== []);
@endphp
<div data-section-overview="farm-units" hidden></div>
<section class="farm-unit-bar" data-farm-unit-bar data-keep-link aria-label="{{ $unitNames[$farmUnit] }} pages">
    <nav class="farm-unit-bar__units" aria-label="Units">
        @foreach($otherUnits as $code => $name)
            <a href="{{ route('farm-units.show', $code) }}" @if($code === $farmUnit) aria-current="page" @endif>{{ $name }}</a>
        @endforeach
    </nav>
    <nav class="view-switch farm-unit-bar__pages" aria-label="{{ $unitNames[$farmUnit] }}">
        @foreach($unitPages as $index => [$label, $route, $params])
            <a href="{{ route($route, $params + ['farmunit' => $farmUnit]) }}" @if($index === $currentIndex) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
</section>
