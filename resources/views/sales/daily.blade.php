@extends('layouts.app')
@section('title', 'Daily Sales')
@section('content')
{{-- A sales person's daily sheet: their sales in the chosen dates, cash brought forward and cash held. --}}
@php
    $s = $sheet;
@endphp
        <section class="sales-dashboard">
            <div class="screen-actions"><a class="btn btn--small btn--quiet" href="{{ route('sales.index',['screen'=>'reports']) }}" data-keep-link>View sales report</a></div>
            <form class="sales-report__filter" method="GET" action="{{ route('sales.index') }}"><input type="hidden" name="screen" value="daily">
                <div class="field"><label>From</label><input type="date" name="sales_from" value="{{ $s['salesFrom'] }}" max="{{ now()->toDateString() }}" required></div>
                <div class="field"><label>To</label><input type="date" name="sales_to" value="{{ $s['salesTo'] }}" max="{{ now()->toDateString() }}" required></div>
                <button class="btn">View</button>
            </form>
            <section class="exec-panel table-wrap" id="daily-sales-table">
                <table>
                    <thead><tr><th>Date</th><th>Time</th><th>Receipt</th><th>Customer</th><th>Method</th><th>Sale total</th><th>Received</th><th>Balance</th><th>Approval</th></tr></thead>
                    <tbody>
                        <tr class="balance-forward-row"><td>{{ \Carbon\Carbon::parse($s['salesFrom'])->format('d M Y') }}</td><td>Opening</td><td colspan="4"><strong>Balance brought forward</strong></td><td><strong>@money($s['openingBalance'], 2)</strong></td><td><strong>@money($s['openingBalance'], 2)</strong></td><td>{{ $s['openingBalance'] > 0 ? 'Awaiting Finance' : 'Clear' }}</td></tr>
                        @forelse($s['dailySales'] as $sale)
                            <tr><td>{{ $sale->sale_date->format('d M Y') }}</td><td>{{ $sale->created_at->format('H:i') }}</td><td><a href="{{ route('sales.show', $sale) }}">{{ $sale->payments->first()?->receipt_number ?: $sale->invoice_number }}</a></td><td>@if($sale->customer)<a href="{{ route('customer-accounts.show', $sale->customer) }}">{{ $sale->customer_name }}</a>@else{{ $sale->customer_name }}@endif</td><td>{{ Str::headline($sale->payment_method ?: $sale->payment_type) }}</td><td>@money($sale->total, 2)</td><td>@money($sale->payments->sum('amount'), 2)</td><td>@money($sale->balance, 2)</td><td>{{ Str::headline($sale->approval_status) }}</td></tr>
                        @empty
                            <tr><td colspan="9">No sales entered in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="form-actions" style="padding:14px 0 0"><strong>Total: @money($s['cashAvailable'], 2)</strong></div>
            </section>
        </section>
@endsection
