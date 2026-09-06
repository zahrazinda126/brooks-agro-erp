@extends('layouts.app')
@php($titles=['profit-loss'=>'Income Statement','balance-sheet'=>'Balance Sheet','cash-flow'=>'Cash Flow Statement','trial-balance'=>'Trial Balance'])
@section('title',$titles[$report])
@section('content')
<x-report-header eyebrow="Finance & management" :title="$titles[$report]" :description="$report==='profit-loss' ? 'Revenue, cost of sales and operating costs for the selected period.' : ($report==='balance-sheet'?'Recorded assets and liabilities.':'Financial activity for the selected period.')" :period="\Carbon\Carbon::parse($from)->format('d M Y').' - '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><a class="btn btn--quiet" href="{{ route('finance.reports.index') }}">All reports</a><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button><a class="btn" href="{{ route('finance.reports.export',['report'=>$report,'from'=>$from,'to'=>$to]) }}">Download Excel</a></x-slot:actions></x-report-header>
@include('finance.reports._filter')
@if($report==='profit-loss')
<div class="card table-card"><table class="income-statement"><thead><tr><th>Income statement section</th><th class="num">Amount (UGX)</th></tr></thead><tbody>
<tr><th>Revenue</th><td class="num">{{ number_format($incomeStatement['revenue']) }}</td></tr>
<tr><th>Less: Cost of Sales</th><td class="num">({{ number_format($incomeStatement['costOfSales']) }})</td></tr>
<tr class="statement-subtotal"><th>Gross Profit</th><td class="num">{{ number_format($incomeStatement['grossProfit']) }}</td></tr>
<tr><th>Less: Operating Costs</th><td class="num">({{ number_format($incomeStatement['operatingCosts']) }})</td></tr>
<tr class="statement-total"><th>{{ $incomeStatement['netProfit']>=0?'Net Profit':'Net Loss' }}</th><td class="num">{{ number_format(abs($incomeStatement['netProfit'])) }}</td></tr>
</tbody></table></div>
@else
<div class="card table-card"><table><thead><tr><th>Account/section</th><th class="num">Debit / Asset</th><th class="num">Credit / Liability</th></tr></thead><tbody>@forelse($rows as $x)<tr><td>{{ $x['label'] }}</td><td class="num">{{ $x['debit']?'UGX '.number_format($x['debit']):'-' }}</td><td class="num">{{ $x['credit']?'UGX '.number_format($x['credit']):'-' }}</td></tr>@empty<tr><td colspan="3">No records for this period.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th><th class="num">UGX {{ number_format($rows->sum('debit')) }}</th><th class="num">UGX {{ number_format($rows->sum('credit')) }}</th></tr></tfoot></table></div>
@endif
<p class="muted">This report uses data recorded in the ERP. It is a management report, not a statutory financial statement until opening balances, depreciation, inventory valuation and accrual journals are configured.</p>
<style>.income-statement .statement-subtotal th,.income-statement .statement-subtotal td{border-top:2px solid #9c775a;font-weight:800}.income-statement .statement-total th,.income-statement .statement-total td{border-top:3px double #8b1e2d;border-bottom:3px double #8b1e2d;font-size:1.05rem;font-weight:900}</style>
@endsection
