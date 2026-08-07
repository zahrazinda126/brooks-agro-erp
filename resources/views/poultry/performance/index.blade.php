@extends('layouts.app')

@section('title', 'Performance Reviews')

@section('content')
<div class="page-head"><h2>Poultry attendant performance</h2><p>Calculated from eggs, birds, feed cost, mortality and expected production.</p></div>
<form method="GET" class="card filters"><div class="field"><label>From</label><input name="from" type="date" value="{{ $from }}"></div><div class="field"><label>To</label><input name="to" type="date" value="{{ $to }}"></div><div class="field field--actions"><button class="btn btn--inline">Apply</button></div></form>
<div class="data-list"><div class="data-head" style="grid-template-columns:1.5fr repeat(7,1fr)"><span>Attendant / house</span><span>Performance</span><span>Expected</span><span>Deficit</span><span>Damage</span><span>Feed cost</span><span>Profit/loss</span><span>Position</span></div>
@forelse($rows as $row)<div class="data-row" style="grid-template-columns:1.5fr repeat(7,1fr)"><span class="data-cell" data-label="Attendant">{{ $row['attendant'] }}<em class="data-sub">{{ $row['house'] }} · {{ number_format($row['birds']) }} birds</em></span><span class="data-cell" data-label="Performance">{{ number_format($row['performance'],1) }}%</span><span class="data-cell" data-label="Expected">{{ number_format($row['expected_performance'],1) }}%</span><span class="data-cell" data-label="Deficit">{{ number_format($row['deficit']) }} eggs<br>UGX {{ number_format($row['deficit_cost']) }}</span><span class="data-cell" data-label="Damage">{{ number_format($row['damage']) }} eggs</span><span class="data-cell" data-label="Feed cost">UGX {{ number_format($row['feed_cost']) }}</span><span class="data-cell" data-label="Profit/loss">UGX {{ number_format($row['profit_loss']) }}</span><span class="data-cell" data-label="Position">#{{ $row['position'] }}</span></div>@empty <div class="card empty"><p class="muted" style="margin:0">No egg collection records exist for this period.</p></div>@endforelse</div>
@endsection
