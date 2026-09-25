@php
    $p = $data['productionOverview'];
    $eggMax = max(1, $p['monthly']->max('eggs'));
    $animalMax = max(1, $p['units']->where('code', '!=', 'garden')->max('value'));
@endphp
<section class="finance-simple-dashboard">
    <div class="finance-simple-head">
        <div><p class="card-label">Production dashboard</p><h2>Farm production performance</h2></div>
        <div class="form-actions">
            <a class="btn btn--small" href="{{ route('operations.reports') }}">Production reports</a>
            <a class="btn btn--quiet btn--small" href="{{ route('poultry.report.index') }}">Review daily entries</a>
        </div>
    </div>
    <p class="muted">Egg collections &middot; {{ $p['monthly']->first()['period'] }} &ndash; {{ $p['monthly']->last()['period'] }}. Includes pending, submitted and approved records; current month to date.</p>
    <div class="finance-annual-chart" role="img" aria-label="Monthly egg collections: {{ $p['monthly']->map(fn ($month) => $month['period'].': '.$month['eggs'].' collected, '.$month['damaged'].' damaged')->implode('; ') }}">
        @foreach($p['monthly'] as $month)
            <div class="finance-annual-month"><div>
                <i class="revenue" style="height:{{ $month['eggs'] / $eggMax * 100 }}%" title="{{ $month['period'] }}: {{ number_format($month['eggs']) }} eggs collected"></i>
                <i class="spending" style="height:{{ min(100, $month['damaged'] / $eggMax * 100) }}%" title="{{ $month['period'] }}: {{ number_format($month['damaged']) }} damaged eggs"></i>
            </div><small>{{ $month['label'] }}</small></div>
        @endforeach
    </div>
    <div class="finance-simple-legend"><span><i class="revenue"></i>Eggs collected</span><span><i class="spending"></i>Damaged eggs</span></div>
    @if($p['monthly']->sum('eggs') === 0)
        <p class="muted">No egg collections recorded for this period.</p>
    @endif
    <div class="finance-simple-grid">
        <section>
            <h3>Livestock on farm</h3>
            @foreach($p['units']->where('code', '!=', 'garden') as $unit)
                <div class="finance-simple-row"><span>{{ $unit['label'] }}</span><b><i style="width:{{ $unit['value'] / $animalMax * 100 }}%"></i></b><strong>{{ number_format($unit['value']) }} {{ $unit['measure'] }}</strong></div>
            @endforeach
            <p class="muted">Active birds and animals.</p>
        </section>
        <section>
            <h3>Today's feed coverage by poultry batch</h3>
            @forelse($p['feedPlan'] as $house)
                <div class="finance-simple-row"><span title="{{ $house['house'] }} / {{ $house['batch']->code }}"><a href="{{ route('batches.show', $house['batch']) }}">{{ $house['house'] }} / {{ $house['batch']->code }}</a></span><b><i style="width:{{ $house['percent'] }}%"></i></b><strong>{{ number_format($house['recordedKg'], 1) }} / {{ number_format($house['requiredKg'], 1) }} kg</strong></div>
            @empty
                <p class="muted">No active poultry batches to monitor.</p>
            @endforelse
            <p class="muted">Recorded / estimated feed, using 125g per bird per day. {{ number_format($p['feedShortageKg'], 1) }} kg still needed across batches.</p>
        </section>
    </div>
    <div class="table-wrap">
        <table class="finance-ratios">
            <thead><tr><th>Production indicator</th><th>{{ now()->format('M Y') }}</th></tr></thead>
            <tbody>
                <tr><td>Eggs collected today</td><td>{{ number_format($p['eggsToday']) }} eggs</td></tr>
                <tr><td>Eggs collected this month</td><td>{{ number_format($p['eggsMonth']) }} eggs</td></tr>
                <tr><td>Damaged eggs this month</td><td>{{ number_format($p['damagedMonth']) }} eggs</td></tr>
                <tr><td>Estimated poultry feed today</td><td>{{ number_format($p['dailyFeedKg'], 2) }} kg</td></tr>
                <tr><td>Poultry feed recorded today</td><td>{{ number_format($p['recordedFeedKg'], 2) }} kg</td></tr>
                <tr><td>Garden plots registered</td><td>{{ number_format($p['units']->firstWhere('code', 'garden')['value']) }}</td></tr>
                <tr><td>Farm unit records needing attention</td><td><a href="{{ route('operations.reports') }}">{{ number_format($p['needsAttention']) }} records</a></td></tr>
            </tbody>
        </table>
    </div>
</section>
