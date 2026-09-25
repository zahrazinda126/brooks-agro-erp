@extends('layouts.app')
@section('title','Profit Analysis')
@section('content')
<x-report-header eyebrow="Finance report" title="Profit Analysis" description="Revenue, direct costs, expenditure and monthly profit contribution." :period="$start->format('d M Y').' – '.$end->format('d M Y')"><x-slot:actions><button class="btn" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
<form class="card filters" method="GET"><div class="field"><label>Financial year beginning July</label><input type="number" name="year" min="2000" max="2100" value="{{ $year }}"></div><div class="field"><label>Quarter</label><select name="quarter">@foreach(range(1,4) as $q)<option value="{{ $q }}" @selected($quarter===$q)>Q{{ $q }} ({{ \Carbon\Carbon::create($year,7,1)->addMonths(($q-1)*3)->format('M') }}–{{ \Carbon\Carbon::create($year,7,1)->addMonths(($q-1)*3+2)->format('M') }})</option>@endforeach</select></div><button class="btn">Analyse</button></form>
<div class="form-actions"><a class="btn" href="{{ route('finance.reports.export',['report'=>'profit-analysis','year'=>$year,'quarter'=>$quarter]) }}">Download Excel</a></div>
@php
    $revenueTable = ['Revenue', $revenueRows, 'revenue'];
    $otherTables = [['Cost of Sales', $costRows, 'cost'], ['Expenditure', $expenseRows, 'expense']];
@endphp
<div class="profit-analysis-grid">
    <div class="profit-analysis-column">
        @php
            $heading = $revenueTable[0];
            $tableRows = $revenueTable[1];
            $kind = $revenueTable[2];
            $tableTotal = (float) $tableRows->sum('amount');
        @endphp
        <section class="card profit-analysis-sheet"><h3>{{ $heading }}</h3><table><thead><tr><th>Account</th><th class="num">Qty sold</th><th class="num">Q{{ $quarter }} {{ $year }}</th><th class="num">%</th></tr></thead><tbody>
        @foreach($tableRows as $row)<tr><td>{{ str_replace(' ','_',$row['account']) }}</td><td class="num">{{ isset($quantities[$row['account']]) ? number_format($quantities[$row['account']]) : '–' }}</td><td class="num">{{ $row['amount'] ? number_format($row['amount'],2) : '–' }}</td><td class="share"><i style="--share:{{ $tableTotal>0?min(100,($row['amount']/$tableTotal)*100):0 }}%"></i><span>{{ $tableTotal>0?number_format(($row['amount']/$tableTotal)*100):0 }}%</span></td></tr>@endforeach
        <tr class="analysis-total"><th>Total Revenue</th><th></th><th class="num">{{ number_format($tableTotal,2) }}</th><th class="num">{{ $tableTotal>0?'100%':'0%' }}</th></tr>
        </tbody></table></section>
        <section class="card profit-analysis-sheet monthly-profit"><h3>Monthly profit contribution</h3><table><thead><tr><th>Month</th><th class="num">Profit</th><th class="num">%</th></tr></thead><tbody>@foreach($months as $month)<tr><td>{{ $month['month'] }}</td><td class="num">{{ $month['profit']<0?'('.number_format(abs($month['profit']),2).')':number_format($month['profit'],2) }}</td><td class="num">{{ $annualProfit!=0?number_format(($month['profit']/$annualProfit)*100,1):'0.0' }}%</td></tr>@endforeach<tr class="analysis-total"><th>Total Profit</th><th class="num">{{ number_format($annualProfit,2) }}</th><th class="num">100%</th></tr></tbody></table></section>
    </div>
    @foreach($otherTables as [$heading, $tableRows, $kind])
            @php
                $tableTotal = (float) $tableRows->sum('amount');
            @endphp
        <section class="card profit-analysis-sheet"><h3>{{ $heading }}</h3><table><thead><tr><th>Account</th><th class="num">Q{{ $quarter }} {{ $year }}</th><th class="num">%</th></tr></thead><tbody>
        @foreach($tableRows as $row)<tr><td>{{ str_replace(' ','_',$row['account']) }}</td><td class="num">{{ $row['amount'] ? '('.number_format($row['amount'],2).')' : '–' }}</td><td class="share"><i style="--share:{{ $tableTotal>0?min(100,($row['amount']/$tableTotal)*100):0 }}%"></i><span>{{ $tableTotal>0?number_format(($row['amount']/$tableTotal)*100):0 }}%</span></td></tr>@endforeach
        <tr class="analysis-total"><th>Total {{ $heading }}</th><th class="num">({{ number_format($tableTotal,2) }})</th><th class="num">{{ $tableTotal>0?'100%':'0%' }}</th></tr>
        </tbody></table></section>
    @endforeach
</div>
<style>.profit-analysis-grid{display:grid;grid-template-columns:repeat(3,minmax(260px,1fr));gap:14px;align-items:start}.profit-analysis-column{display:grid;gap:14px}.profit-analysis-sheet{padding:14px;font-family:"Gill Sans MT","Segoe UI",Arial,sans-serif}.profit-analysis-sheet h3{margin:0 0 9px;color:#6b211d;font-size:14px;text-transform:uppercase}.profit-analysis-sheet table{width:100%;border-collapse:collapse;font-size:11px}.profit-analysis-sheet th,.profit-analysis-sheet td{padding:5px 6px;border:1px solid #332b28}.profit-analysis-sheet thead th,.analysis-total>*{background:#68211d;color:#ffe900}.profit-analysis-sheet .num{text-align:right;font-variant-numeric:tabular-nums}.share{display:flex;align-items:center;gap:5px}.share i{display:block;width:48px;height:8px;border-radius:8px;background:linear-gradient(90deg,#51b873 var(--share),#f08b8b var(--share))}.monthly-profit{max-width:none}.monthly-profit h3{text-transform:none}@media(max-width:1050px){.profit-analysis-grid{grid-template-columns:1fr}.profit-analysis-sheet{overflow-x:auto}}</style>
@endsection
