@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
<div class="page-head page-head--split">
    <div><span class="finance-panel-kicker">Owner's customer view</span><h2>Customers & buying activity</h2><p>See who buys from the farm, how often they return, and the balances that need follow-up.</p></div>
    <a class="btn btn--quiet" href="{{ route('dashboard') }}">Back to dashboard</a>
</div>

<div class="customer-overview-kpis">
    <article><small>Active customers</small><strong>{{ number_format($totalCustomers) }}</strong><em>Saved in the customer register</em></article>
    <article><small>Repeat customers</small><strong>{{ number_format($repeatCustomers) }}</strong><em>Two or more completed purchases</em></article>
    <article><small>Recorded sales value</small><strong>UGX {{ number_format($salesValue) }}</strong><em>Excludes void sales</em></article>
    <article class="customer-kpi--alert"><small>Outstanding balances</small><strong>UGX {{ number_format($outstanding) }}</strong><em>Still expected from customers</em></article>
</div>

<div class="customer-analysis-grid">
    <section class="card customer-frequency">
        <div><p class="card-label">Customer frequency</p><h3>Frequent versus one-time clients</h3><p>A frequent customer has completed two or more purchases.</p></div>
        @php($analysed = $repeatCustomers + $oneTimeCustomers)
        @if($analysed > 0)
            <div class="frequency-bar"><i style="width:{{ $repeatCustomers / $analysed * 100 }}%"></i></div>
            <div class="frequency-legend"><span><b>{{ $repeatCustomers }}</b> Frequent customers</span><span><b>{{ $oneTimeCustomers }}</b> One-time customers</span></div>
        @else
            <div class="customer-analysis-empty"><strong>Customer names are ready; sales activity has not started.</strong><p>The 24 imported customers will be classified automatically after daily sales are recorded against their names.</p></div>
        @endif
        @if($customersWithoutSales)<small>{{ $customersWithoutSales }} registered customer(s) have no sale recorded yet.</small>@endif
    </section>
    <section class="card customer-leaders"><p class="card-label">Most loyal customers</p><h3>Top clients by purchase frequency</h3>@forelse($topCustomers as $customer)<div><span><strong>{{ $loop->iteration }}. {{ $customer->name }}</strong><small>{{ $customer->purchase_count }} purchases</small></span><b>UGX {{ number_format((float)$customer->total_purchased) }}</b></div>@empty<div class="customer-analysis-empty"><p>Top clients will appear here after sales are recorded.</p></div>@endforelse</section>
</div>

<section class="card customer-directory">
    <div class="customer-directory__head">
        <div><p class="card-label">Customer directory</p><h3>Contact and purchase details</h3></div>
        <form method="GET" action="{{ route('owner.customers') }}" class="customer-search"><input name="search" value="{{ $search }}" placeholder="Search name, phone or email"><button class="btn btn--small">Search</button>@if($search)<a href="{{ route('owner.customers') }}">Clear</a>@endif</form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Contact</th><th>Purchases</th><th>Last purchase</th><th class="num">Total bought</th><th class="num">Balance due</th><th>Type</th></tr></thead><tbody>
        @forelse($customers as $customer)
            <tr><td><strong>{{ $customer->name }}</strong>@if($customer->address)<small class="table-sub">{{ Str::limit($customer->address, 45) }}</small>@endif</td><td>{{ $customer->phone ?: 'No phone' }}<small class="table-sub">{{ $customer->email ?: 'No email' }}</small></td><td>{{ number_format($customer->purchase_count) }}</td><td>{{ $customer->last_purchase_on ? \Illuminate\Support\Carbon::parse($customer->last_purchase_on)->format('d M Y') : 'Never' }}</td><td class="num">UGX {{ number_format((float) $customer->total_purchased) }}</td><td class="num"><strong class="{{ $customer->outstanding_balance > 0 ? 'text-alarm' : '' }}">UGX {{ number_format((float) $customer->outstanding_balance) }}</strong></td><td><span class="status-pill">{{ $customer->purchase_count >= 2 ? 'Frequent' : ($customer->purchase_count === 1 ? 'One-time' : 'No purchase') }}</span></td></tr>
        @empty<tr><td colspan="7" class="empty">{{ $search ? 'No customer matches this search.' : 'No customers have been recorded.' }}</td></tr>@endforelse
    </tbody></table></div>
    {{ $customers->links() }}
</section>
@endsection
