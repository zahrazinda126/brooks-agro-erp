@extends('layouts.app')
@section('title','Profitability')
@section('content')
<x-report-header eyebrow="Finance report" title="Profitability" description="Revenue, cost of goods sold, expenses and profit by account." :period="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')" />
<div class="form-actions"><a class="btn" href="{{ route('finance.reports.export',['report'=>'profitability','from'=>$from,'to'=>$to]) }}">Download Excel</a></div>
<form class="card filters" method="GET"><div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}" required></div><div class="field"><label>To</label><input type="date" name="to" value="{{ $to }}" required></div><button class="btn">Calculate</button></form>
<section class="card table-card profitability-accounts"><div class="section-heading"><div><p class="card-label">Selected period</p><h3>Profitability by account</h3><p>All farm-operation accounts remain visible, including accounts with no entry in this period.</p></div></div><div class="profitability-sheet"><table><thead><tr><th>Code</th><th>Account</th><th class="num">UGX</th></tr></thead><tbody>
@foreach($accountProfitability['sections'] as $section)
<tr class="profitability-section"><th colspan="3">{{ $section['name']==='Operating Costs' ? 'Expenses' : ($section['name']==='Cost of Sales' ? 'Cost of Goods Sold (COGS)' : $section['name']) }}</th></tr>
@foreach($section['rows'] as $accountRow)<tr class="profitability-account-row"><td>{{ $accountRow['code'] }}</td><td>{{ $accountRow['account'] }}</td><td class="num">{{ $accountRow['amount'] == 0 ? '—' : number_format($accountRow['amount'], 2) }}</td></tr>@endforeach
<tr class="profitability-subtotal"><th colspan="2">{{ $section['name']==='Revenue' ? 'Total Revenue' : ($section['name']==='Cost of Sales' ? 'Total COGS' : 'Total Expenses') }}</th><th class="num">@money($section['total'])</th></tr>
@if($section['name']==='Cost of Sales')<tr class="profitability-result"><th colspan="2">Gross Profit</th><th class="num">@money($accountProfitability['grossProfit'])</th></tr>@endif
@endforeach
<tr class="profitability-result profitability-result--final"><th colspan="2">Net Profit</th><th class="num">@money($accountProfitability['netProfit'])</th></tr>
</tbody></table></div></section>
@pushOnce('styles')<style>
.profitability-accounts,.profitability-accounts button,.profitability-accounts input{font-family:"Gill Sans MT","Segoe UI",Arial,sans-serif}
.profitability-accounts{font-size:12px}.profitability-accounts .section-heading h3{font-size:17px}.profitability-accounts .section-heading p{font-size:12px}
.profitability-sheet{width:100%;max-width:980px}.profitability-sheet table{width:100%;font-size:12px}
.profitability-sheet th,.profitability-sheet td{padding:7px 10px;line-height:1.2}
.profitability-accounts td:first-child{width:100px;color:#6e6259;font-size:11px}
.profitability-account-row td{font-size:12px;border-bottom:1px dotted #d8c7ae}
.profitability-account-row td:nth-child(2)::before{content:'+ ';color:#9f2630}
.profitability-section th{padding-block:6px;background:#f4ecdf;text-transform:uppercase;font-size:12px}
.profitability-subtotal th{background:#d6b665;font-size:12px}
.profitability-result th{background:#70400d;color:#fff;font-size:12px}
.profitability-result--final th{background:#4f2708;font-size:13px}
</style>@endPushOnce
@endsection
