{{--
    Unit report switches. $part = 'unit' renders the Unit field (inside a filter form);
    $part = 'tabs' renders the section tabs. Needs $unit, $section (null for Garden), $from, $to.
--}}
@php
    $dates = ['from' => $from, 'to' => $to];
@endphp
@if(($part ?? 'tabs') === 'unit')
    <div class="field"><label for="report-unit">Unit</label>
        <select id="report-unit" onchange="window.location.href = this.value">
            @foreach(\App\Support\UnitReports::UNITS as $key => $label)
                <option value="{{ \App\Support\UnitReports::url($key, $section, $dates) }}" @selected($key === $unit)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
@elseif($unit !== 'garden')
    <nav class="view-switch" aria-label="Report section" data-keep-link>
        @foreach(\App\Support\UnitReports::SECTIONS as $key => $label)
            <a href="{{ \App\Support\UnitReports::url($unit, $key, $dates) }}" @if($section === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        @if($unit === 'poultry' && ($showSales ?? false))<a href="{{ route('poultry.report.index', ['view' => 'sales'] + array_filter($dates)) }}" @if(($salesActive ?? false)) aria-current="page" @endif>Sales</a>@endif
    </nav>
@endif
