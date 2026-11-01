@extends('layouts.app')
@section('title','Overall Production Report')
@section('content')
@php
    $periodQuery = $period === 'month' ? ['period'=>'month','month'=>substr($from,0,7)] : ['period'=>'day','date'=>$from];
@endphp
<x-report-header eyebrow="Production management" title="Overall Production Report" description="The same detailed production sheet used for the daily report, available for a selected day or a whole month." :period="$periodLabel">
    <x-slot:actions>
        <button class="btn btn--quiet" type="button" onclick="window.print()">Print</button>
        <a class="btn" href="{{ route('operations.reports.export', array_merge(['view'=>$view], $periodQuery)) }}">Download Excel</a>
    </x-slot:actions>
</x-report-header>

<form class="card filter-bar" method="get" action="{{ route('operations.reports') }}">
    <input type="hidden" name="view" value="{{ $view }}">
    <label>Report period
        <select name="period" onchange="this.form.submit()">
            <option value="day" @selected($period==='day')>Daily</option>
            <option value="month" @selected($period==='month')>Monthly</option>
        </select>
    </label>
    <label class="{{ $period==='month'?'':'is-hidden' }}">Month
        <input type="month" name="month" value="{{ $period==='month'?substr($from,0,7):now()->format('Y-m') }}">
    </label>
    <label class="{{ $period==='day'?'':'is-hidden' }}">Date
        <input type="date" name="date" value="{{ $period==='day'?$from:now()->toDateString() }}">
    </label>
    @if(request('unit'))<input type="hidden" name="unit" value="{{ request('unit') }}">@endif<button class="btn" type="submit">View report</button>
</form>

@php
    // One unit at a time; "Summary" gives every unit's headline figures in one small table.
    $reportUnits = ['summary' => 'Summary', 'poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden'];
    $reportUnit = array_key_exists((string) request('unit'), $reportUnits) ? request('unit') : 'summary';
    $snap = $productionSnapshot;
@endphp
<nav class="view-switch" aria-label="Report unit" data-keep-link>
    @foreach($reportUnits as $key => $label)<a href="{{ route('operations.reports', array_merge($periodQuery, $key === 'summary' ? [] : ['unit' => $key])) }}" @if($reportUnit === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>

@if($reportUnit === 'summary')
<section class="card table-wrap">
<div class="section-heading"><div><h3>{{ $periodLabel }}</h3></div></div>
<table class="exec-table">
    <thead><tr><th>Unit</th><th class="num">Animals / birds</th><th class="num">Deaths</th><th class="num">Feed (kg)</th><th class="num">Production</th><th></th></tr></thead>
    <tbody>
        <tr><td><strong>Poultry</strong></td><td class="num">{{ number_format($snap['poultry_rows']->sum('birds')) }}</td><td class="num">{{ number_format($snap['poultry_rows']->sum('deaths')) }}</td><td class="num">—</td><td class="num">{{ number_format($snap['poultry_rows']->sum('total_eggs')) }} eggs</td><td><a href="{{ route('operations.reports', array_merge($periodQuery, ['unit' => 'poultry'])) }}">Open</a></td></tr>
        @foreach(['goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs'] as $code => $label)
            @php $a = $snap['livestock'][$code]; @endphp
            <tr><td><strong>{{ $label }}</strong></td><td class="num">{{ number_format($a['groups']->sum('current_quantity')) }}</td><td class="num">{{ number_format($a['mortality']->sum('quantity')) }}</td><td class="num">{{ number_format($a['feed']->sum('quantity_kg'), 1) }}</td><td class="num">{{ number_format($a['production']->sum('quantity')) }}</td><td><a href="{{ route('operations.reports', array_merge($periodQuery, ['unit' => $code])) }}">Open</a></td></tr>
        @endforeach
        <tr><td><strong>Garden</strong></td><td class="num">—</td><td class="num">—</td><td class="num">—</td><td class="num">{{ number_format($snap['units']->where('unit_code', 'garden')->count()) }} records</td><td><a href="{{ route('operations.reports', array_merge($periodQuery, ['unit' => 'garden'])) }}">Open</a></td></tr>
    </tbody>
</table>
</section>
@else
@include('daily-reports.partials.production-legacy', ['snapshot'=>$productionSnapshot, 'date'=>$from, 'periodLabel'=>$periodLabel.' · '.$reportUnits[$reportUnit], 'only'=>$reportUnit, 'compact'=>true])
@endif

<details class="card disclosure u-mt-16">
    <summary>View supporting records and totals</summary>
    <nav class="unit-tabs">
    <a class="{{ $view==='overall'?'active':'' }}" href="{{ route('operations.reports', $periodQuery) }}">All units</a>
    <a class="{{ $view==='livestock'?'active':'' }}" href="{{ route('operations.reports', array_merge(['view'=>'livestock'], $periodQuery)) }}">Livestock</a>
    <a class="{{ $view==='health'?'active':'' }}" href="{{ route('operations.reports', array_merge(['view'=>'health'], $periodQuery)) }}">Health & losses</a>
    <a class="{{ $view==='inputs'?'active':'' }}" href="{{ route('operations.reports', array_merge(['view'=>'inputs'], $periodQuery)) }}">Feeding & inputs</a>
    <a class="{{ $view==='production'?'active':'' }}" href="{{ route('operations.reports', array_merge(['view'=>'production'], $periodQuery)) }}">Production & harvest</a>
</nav>
    <div class="production-unit-grid">@foreach($units as $unit)<article class="production-unit-card"><span>{{ $unit['icon'] }}</span><div><small>{{ $unit['label'] }}</small><strong>{{ number_format($unit['value']) }}</strong><em>{{ $unit['measure'] }}</em></div></article>@endforeach</div>
    <div class="exec-kpi-grid exec-kpi-grid--compact"><article class="exec-kpi exec-kpi--positive"><div><small>Activity in this period</small><strong>{{ $activity30 }}</strong></div></article><article class="exec-kpi exec-kpi--danger"><div><small>Needs attention</small><strong>{{ $attention }}</strong></div></article><article class="exec-kpi exec-kpi--warning"><div><small>Total recorded value</small><strong>@money($recordedValue)</strong></div></article></div>
    <div class="exec-panel table-wrap"><p class="card-label">Cross-unit activity</p><h3>{{ ucfirst(str_replace('_',' ',$view)) }} records</h3><table class="exec-table"><thead><tr><th>Date</th><th>Unit</th><th>Record</th><th>Subject</th><th>Quantity</th><th>Value / Cost</th><th>Status</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->record_date->format('d M Y') }}</td><td>{{ ucfirst($row->unit_code) }}</td><td>{{ str_replace('_',' ',ucfirst($row->record_type)) }}</td><td><strong>{{ $row->subject }}</strong></td><td>{{ $row->quantity!==null?number_format((float)$row->quantity,2).' '.$row->measure_unit:'—' }}</td><td>{{ $row->amount!==null?'UGX '.number_format((float)$row->amount):'—' }}</td><td><span class="status-pill status-pill--{{ $row->status==='needs_attention'?'inactive':'active' }}">{{ str_replace('_',' ',ucfirst($row->status)) }}</span></td></tr>@empty<tr><td colspan="7">No records match this report yet.</td></tr>@endforelse</tbody></table></div>
    {{ $rows->links() }}
    @if($eggs->isNotEmpty() || $pendingEggs)
    <div class="exec-panel table-wrap"><div class="page-head page-head--split"><div><p class="card-label">Poultry production</p><h3>Approved egg collections</h3></div>@if($pendingEggs)<span class="status-pill status-pill--inactive">{{ $pendingEggs }} awaiting supervisor review</span>@endif</div><table class="exec-table"><thead><tr><th>Date & time</th><th>Batch</th><th>Period</th><th>Grade breakdown</th><th>Total eggs</th><th>Tray equivalent</th><th>Recorded by</th></tr></thead><tbody>@forelse($eggs as $collection)<tr><td>{{ $collection->collected_on->format('d M Y') }}<br><small>{{ $collection->collected_at ? \Carbon\Carbon::parse($collection->collected_at)->format('H:i') : '—' }}</small></td><td>{{ $collection->batch?->code ?? '—' }}</td><td>{{ ucfirst($collection->session) }}</td><td>@foreach($collection->lines as $line)<div>{{ $line->grade?->name ?? 'Grade' }}: <strong>{{ number_format($line->total_eggs) }}</strong></div>@endforeach</td><td><strong>{{ number_format($collection->total_eggs) }}</strong></td><td>{{ number_format($collection->total_eggs/30,2) }}</td><td>{{ $collection->recorder?->displayName() ?? '—' }}</td></tr>@empty<tr><td colspan="7">No approved egg collections yet.</td></tr>@endforelse</tbody></table></div>
    @endif
</details>
@endsection
