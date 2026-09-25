@extends('layouts.app')
@section('title','Expenditure Analysis')
@section('content')
<x-report-header eyebrow="Finance report" title="Expenditure Analysis" description="Operating expenditure by account, month and financial year." :period="'July '.$year.' – June '.($year+1)"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
<div class="form-actions"><a class="btn" href="{{ route('finance.reports.export',['report'=>'expenditure-analysis','year'=>$year]) }}">Download Excel</a></div>
<form class="card filters" method="GET"><div class="field"><label>Financial year beginning July</label><input type="number" name="year" min="2000" max="2100" value="{{ $year }}" required></div><button class="btn">View analysis</button></form>
<section class="card expenditure-workbook">
 <h3>Expenditure summary</h3>
 <div class="sheet-scroll"><table class="expenditure-summary"><thead><tr><th>Account</th><th>July {{ $year }} – June {{ $year+1 }}</th><th>July {{ $year-1 }} – June {{ $year }}</th><th>Current vs previous</th></tr></thead><tbody>
 @foreach($current['rows'] as $row)
 @php($prior=$previous['rows']->firstWhere('account',$row['account'])['total'] ?? 0)
 <tr><td>{{ str_replace(' ', '_', $row['account']) }}</td><td class="num">{{ $row['total'] ? '('.number_format($row['total'],2).')' : '–' }}</td><td class="num">{{ $prior ? '('.number_format($prior,2).')' : '–' }}</td><td class="num">{{ ($row['total']-$prior) ? '('.number_format(abs($row['total']-$prior),2).')' : '–' }}</td></tr>
 @endforeach
 <tr class="sheet-total"><th>Administrative expenses</th><th class="num">({{ number_format($current['annual'],2) }})</th><th class="num">({{ number_format($previous['annual'],2) }})</th><th class="num">({{ number_format(abs($current['annual']-$previous['annual']),2) }})</th></tr>
 </tbody></table></div>
</section>
<section class="card expenditure-workbook">
 <h3>Month-on-month expenditure</h3>
 <div class="sheet-scroll"><table><thead><tr><th>Code</th><th>Account</th>@foreach($current['months'] as $month)<th class="num">{{ $month }}</th>@endforeach<th class="num">Total</th></tr></thead><tbody>
 @foreach($current['rows'] as $row)<tr><td>{{ $row['code'] }}</td><td>{{ str_replace(' ', '_', $row['account']) }}</td>@foreach($row['values'] as $amount)<td class="num">{{ $amount ? number_format($amount,2) : '–' }}</td>@endforeach<td class="num"><strong>{{ $row['total'] ? number_format($row['total'],2) : '–' }}</strong></td></tr>@endforeach
 <tr class="sheet-total"><th colspan="2">Total Operating Expenditure</th>@foreach($current['totals'] as $amount)<th class="num">{{ $amount ? number_format($amount,2) : '–' }}</th>@endforeach<th class="num">{{ number_format($current['annual'],2) }}</th></tr>
 <tr class="movement-row"><th colspan="2">Movement month on month</th>@foreach($movement as $change)<th class="num {{ $change!==null&&$change>0?'movement-up':'movement-down' }}">{{ $change===null?'–':number_format(abs($change),1).'% '.($change>0?'▲':($change<0?'▼':'•')) }}</th>@endforeach<th></th></tr>
 </tbody></table></div>
</section>
<style>.expenditure-workbook{font-family:"Gill Sans MT","Segoe UI",Arial,sans-serif}.expenditure-workbook h3{margin:0 0 12px;font-size:16px}.sheet-scroll{overflow-x:auto}.expenditure-workbook table{width:100%;min-width:1500px;border-collapse:collapse;font-size:11px}.expenditure-summary{min-width:760px!important;max-width:900px}.expenditure-workbook th,.expenditure-workbook td{padding:5px 7px;border:1px solid #3c332d;white-space:nowrap}.expenditure-workbook thead th{background:#68211d;color:#ffe900}.expenditure-workbook thead th:first-child,.expenditure-workbook tbody td:nth-child(2){text-align:left}.expenditure-workbook .num{text-align:right;font-variant-numeric:tabular-nums}.sheet-total>*{background:#68211d!important;color:#ffe900!important;font-weight:900}.movement-row>*{background:#eee;font-weight:800}.movement-up{color:#e02424}.movement-down{color:#00b52a}</style>
@endsection
