@extends('layouts.app')
@php
    $titles = [
        'profit-loss' => 'Income Statement',
        'balance-sheet' => 'Balance Sheet',
        'cash-flow' => 'Cash Flow Statement',
        'trial-balance' => 'Trial Balance',
    ];
@endphp
@section('title',$titles[$report])
@section('content')
<x-report-header eyebrow="Finance & management" :title="$titles[$report]" :description="$report==='profit-loss' ? 'Revenue, cost of sales and operating costs for the selected period.' : ($report==='balance-sheet'?'Assets, liabilities and owners equity across the financial year.':'Financial activity for the selected period.')" :period="$report==='balance-sheet' ? 'July '.$balanceSheetModel['year'].' - June '.($balanceSheetModel['year']+1) : \Carbon\Carbon::parse($from)->format('d M Y').' - '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button><a class="btn" href="{{ route('finance.reports.export',['report'=>$report,'from'=>$from,'to'=>$to,'year'=>$balanceSheetModel['year'] ?? null]) }}">Download Excel</a></x-slot:actions></x-report-header>
@if($report==='balance-sheet')
<form class="card filters" method="GET"><div class="field"><label>Financial year beginning July</label><input type="number" name="year" min="2000" max="2100" value="{{ $balanceSheetModel['year'] }}" required></div><button class="btn">View balance sheet</button></form>
@else
@include('finance.reports._filter')
@endif
@if($report==='profit-loss')
<div class="card table-card"><table class="income-statement"><thead><tr><th>Income statement section</th><th class="num">Amount (UGX)</th></tr></thead><tbody>
<tr><th>Revenue</th><td class="num">{{ number_format($incomeStatement['revenue']) }}</td></tr>
<tr><th>Less: Cost of Sales</th><td class="num">({{ number_format($incomeStatement['costOfSales']) }})</td></tr>
<tr class="statement-subtotal"><th>Gross Profit</th><td class="num">{{ number_format($incomeStatement['grossProfit']) }}</td></tr>
<tr><th>Less: Operating Costs</th><td class="num">({{ number_format($incomeStatement['operatingCosts']) }})</td></tr>
<tr class="statement-total"><th>{{ $incomeStatement['netProfit']>=0?'Net Profit':'Net Loss' }}</th><td class="num">{{ number_format(abs($incomeStatement['netProfit'])) }}</td></tr>
</tbody></table></div>
@elseif($report==='balance-sheet')
@php
    $balanceCodes = [
        'NCA (Non-Current Assets)' => '1010000', 'Property Plant and Equipment (PPE)' => '1011000', 'Land' => '1011001',
        'Total NCA' => '', 'CA (Current Assets)' => '1020000', 'Cash and Cash Equivalents' => '1022000',
        'Accounts Receivable' => '1030000', 'Inventory' => '1026000', 'Other Receivables' => '1025000',
        'Total CA' => '', 'Total Assets' => '', 'NCL (Non-Current Liabilities)' => '2020000', 'Loans' => '2021002',
        'Lease Liability' => '2021004', 'Un_Earned_Contribution' => '2021003', 'Deferred Tax' => '2021001',
        'Total NCL' => '', 'CL (Current Liabilities)' => '2030000', 'Accounts Payable' => '2032001',
        'Un_Earned_Income (customers)' => '2032003', 'Total CL' => '', 'Total Liabilities' => '',
        "Owner's Equity" => '3000000', 'Capital' => '3000000', 'Reserves' => '3000002',
        'Shareholder funds' => '3000003', "Total Owner's Equity" => '', "TOTAL LIABILITIES + OWNER'S EQUITY" => '',
    ];
@endphp
<section class="card balance-sheet-workbook">
    <div class="balance-sheet-scroll">
        <table>
            <thead><tr><th>Code</th><th>Account</th>@foreach($balanceSheetModel['columns'] as $month)<th class="num">{{ $month }}</th>@endforeach<th class="num balance-to-date">Total to date</th></tr></thead>
            <tbody>
            @foreach($balanceSheetModel['rows'] as $sheetRow)
                <tr class="balance-row balance-row--{{ $sheetRow['style'] }}">
                    <td>{{ $balanceCodes[$sheetRow['label']] ?? '' }}</td>
                    <td>{{ str_replace('_', ' ', $sheetRow['label']) }}</td>
                    @foreach($sheetRow['values'] as $amount)
                        <td class="num">{{ abs($amount) < 0.005 ? '–' : number_format($amount, 2) }}</td>
                    @endforeach
                    <td class="num balance-to-date">{{ abs($sheetRow['total']) < 0.005 ? '–' : number_format($sheetRow['total'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@else
<div class="card table-card trial-balance-table"><table><thead><tr><th>Account/section</th><th class="num">Debit / Asset</th><th class="num">Credit / Liability</th></tr></thead><tbody>@forelse($rows as $x)<tr><td>{{ $x['label'] }}</td><td class="num">{{ $x['debit']?'UGX '.number_format($x['debit']):'-' }}</td><td class="num">{{ $x['credit']?'UGX '.number_format($x['credit']):'-' }}</td></tr>@empty<tr><td colspan="3">No records for this period.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th><th class="num">UGX {{ number_format($rows->sum('debit')) }}</th><th class="num">UGX {{ number_format($rows->sum('credit')) }}</th></tr></tfoot></table></div>
@endif
<p class="muted">This report uses data recorded in the ERP. It is a management report, not a statutory financial statement until opening balances, depreciation, inventory valuation and accrual journals are configured.</p>
<style>
.income-statement .statement-subtotal th,.income-statement .statement-subtotal td{border-top:2px solid #9c775a;font-weight:800}.income-statement .statement-total th,.income-statement .statement-total td{border-top:3px double #8b1e2d;border-bottom:3px double #8b1e2d;font-size:1.05rem;font-weight:900}
.balance-sheet-workbook{padding:0;overflow:hidden;font-family:"Gill Sans MT","Segoe UI",Arial,sans-serif}.balance-sheet-scroll{overflow-x:auto}.balance-sheet-workbook table{width:100%;min-width:1500px;border-collapse:collapse;font-size:11px}.balance-sheet-workbook th,.balance-sheet-workbook td{padding:5px 8px;border-bottom:0;white-space:nowrap}.balance-sheet-workbook thead th{position:sticky;top:0;z-index:2;background:#002d68;color:#fff;font-size:11px}.balance-sheet-workbook th:first-child,.balance-sheet-workbook td:first-child{width:85px}.balance-sheet-workbook th:nth-child(2),.balance-sheet-workbook td:nth-child(2){width:255px;text-align:left}.balance-sheet-workbook .num{text-align:right;font-variant-numeric:tabular-nums}.balance-row--heading td{padding-top:12px;font-weight:800}.balance-row--subtotal td{background:#087dc1;color:#fff;font-weight:800}.balance-row--total td{background:#ce0000;color:#fff;font-weight:800}.balance-row--final td{background:#fff200;color:#111;font-weight:900}.balance-row--detail td:nth-child(2){padding-left:16px}
.balance-sheet-workbook .balance-to-date{border-left:2px solid #002d68;font-weight:800}
@media(max-width:800px){.balance-sheet-workbook table{font-size:10px}.balance-sheet-workbook th,.balance-sheet-workbook td{padding:5px 6px}}
</style>
@endsection
