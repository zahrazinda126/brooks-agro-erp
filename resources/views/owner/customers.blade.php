@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
@php($customerView = request('view', 'accounts'))
@php($analysisType = request('analysis', 'overview'))
<div class="page-head page-head--split">
    <div><span class="finance-panel-kicker">Company customer records</span><h2>{{ $customerView === 'add' ? 'Add customer' : ($customerView === 'analysis' ? 'Customer analysis' : 'Customer accounts') }}</h2><p>{{ $customerView === 'add' ? 'Register a customer and their contact and credit details.' : ($customerView === 'analysis' ? 'Review customer grades, purchasing patterns, revenue contribution, and customers requiring follow-up.' : 'View customer contact details, prepayments, purchases, and outstanding balances.') }}</p></div>
    <a class="btn btn--quiet" href="{{ route('dashboard') }}">Back to dashboard</a>
</div>
@if(session('status'))<div class="saved">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif
@if($customerView === 'add' && $canCreateCustomer)<section id="add-customer" class="card"><form method="POST" action="{{ route('sales.customers.store') }}" class="form-grid">@csrf<div class="field"><label>Name</label><input name="name" value="{{ old('name') }}" required></div><div class="field"><label>Phone</label><input name="phone" value="{{ old('phone') }}"></div><div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}"></div><div class="field"><label>Address</label><input name="address" value="{{ old('address') }}"></div><div class="field"><label>Tax number</label><input name="tax_number" value="{{ old('tax_number') }}"></div><div class="field"><label>Credit limit (UGX)</label><input type="number" min="0" name="credit_limit" value="{{ old('credit_limit', 0) }}"></div><button class="btn">Add customer</button></form></section>@endif

@if($customerView === 'analysis')
<nav class="customer-analysis-menu" id="customer-analysis" aria-label="Customer analysis reports">
    <a href="{{ route('owner.customers', ['view'=>'analysis','analysis'=>'overview']) }}#customer-analysis" class="{{ $analysisType==='overview' ? 'is-active' : '' }}"><span>Overview</span><strong>Customer summary</strong><small>Key customer, sales, and balance figures.</small></a>
    <a href="{{ route('owner.customers', ['view'=>'analysis','analysis'=>'grading']) }}#customer-analysis" class="{{ $analysisType==='grading' ? 'is-active' : '' }}"><span>Purchasing</span><strong>Monthly customer grading</strong><small>Grade customers by product purchases and revenue contribution.</small></a>
    <a href="{{ route('owner.customers', ['view'=>'analysis','analysis'=>'loyalty']) }}#customer-analysis" class="{{ $analysisType==='loyalty' ? 'is-active' : '' }}"><span>Retention</span><strong>Customer loyalty</strong><small>Compare frequent customers and identify the most loyal buyers.</small></a>
    <a href="{{ route('owner.customers', ['view'=>'analysis','analysis'=>'follow-up']) }}#customer-analysis" class="{{ $analysisType==='follow-up' ? 'is-active' : '' }}"><span>Attention</span><strong>Customers to follow up</strong><small>Find regular customers whose purchases have suddenly stopped.</small></a>
</nav>

@if($analysisType === 'overview')
<div class="customer-overview-kpis">
    <article><small>Active customers</small><strong>{{ number_format($totalCustomers) }}</strong><em>Saved in the customer register</em></article>
    <article><small>Repeat customers</small><strong>{{ number_format($repeatCustomers) }}</strong><em>Two or more completed purchases</em></article>
    <article><small>Recorded sales value</small><strong>UGX {{ number_format($salesValue) }}</strong><em>Excludes void sales</em></article>
    <article class="customer-kpi--alert"><small>Outstanding balances</small><strong>UGX {{ number_format($outstanding) }}</strong><em>Still expected from customers</em></article>
</div>
@endif

@if($analysisType === 'loyalty')
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
@endif

@if($analysisType === 'grading')
<section class="card customer-product-analysis">
    <div class="customer-directory__head">
        <div><p class="card-label">Monthly customer grading</p><h3>Purchases by product</h3><p>Egg quantities are converted to tray equivalents, including loose damaged eggs.</p></div>
        <form method="GET" action="{{ route('owner.customers') }}" class="customer-analysis-filter">
            <input type="hidden" name="view" value="analysis">
            <input type="hidden" name="analysis" value="grading">
            <label>Month<input type="month" name="month" value="{{ $selectedMonth }}"></label>
            <label>Product<select name="product_id"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($selectedProduct?->id===$product->id)>{{ ucfirst($product->category) }} · {{ $product->name }}</option>@endforeach</select></label>
            <button class="btn btn--small">Apply filters</button>
        </form>
    </div>
    <div class="analysis-summary"><span>{{ $selectedProduct?->name ?? 'All products' }}</span><strong>UGX {{ number_format($productRevenue, 2) }}</strong><small>Total revenue for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m',$selectedMonth)->format('F Y') }}</small></div>
    <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Product</th><th class="num">Total quantity</th><th class="num">Total amount</th><th class="num">Product revenue share</th><th class="num">Average price</th><th>Monthly grade</th></tr></thead><tbody>
        @forelse($productAnalysis as $row)<tr>
            <td>@if($row->customer_id)<a class="customer-name-link" href="{{ route('customer-accounts.show',$row->customer_id) }}"><strong>{{ $row->customer_name }}</strong></a>@else<strong>{{ $row->customer_name }}</strong>@endif<small class="table-sub">{{ $row->purchase_days }} buying {{ Str::plural('day',$row->purchase_days) }}</small></td>
            <td>{{ $row->product_name }}</td><td class="num"><strong>{{ number_format((float)$row->quantity,2) }}</strong> {{ $row->unit }}</td><td class="num">UGX {{ number_format((float)$row->amount,2) }}</td><td class="num">{{ number_format($row->share,1) }}%</td><td class="num">UGX {{ number_format((float)$row->average_price,2) }} / {{ Str::singular($row->unit) }}</td><td><span class="status-pill customer-grade customer-grade--{{ strtolower($row->grade) }}">{{ $row->grade }}</span></td>
        </tr>@empty<tr><td colspan="7" class="empty">No sales match this month and product.</td></tr>@endforelse
    </tbody></table></div>
    <p class="table-note">Grades show contribution to the filtered product revenue: Platinum 10%+, Gold 5–9.9%, Silver 2–4.9%, Regular below 2%.</p>
</section>
@endif

@if($analysisType === 'follow-up')
<section class="card customer-follow-up">
    <div class="section-heading"><div><p class="card-label">Retention follow-up</p><h3>Frequent customers who have stopped returning</h3><p>A customer appears here after at least two purchases and an absence longer than both 21 days and twice their normal buying interval.</p></div><strong>{{ $followUps->count() }} to contact</strong></div>
    <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Contact</th><th>Usual buying days</th><th>Normal interval</th><th>Last purchase</th><th>Absent for</th></tr></thead><tbody>
        @forelse($followUps as $row)<tr><td><a class="customer-name-link" href="{{ route('customer-accounts.show',$row->customer) }}"><strong>{{ $row->customer->name }}</strong></a><small class="table-sub">{{ $row->purchases }} purchases in the last 6 months</small></td><td>{{ $row->customer->phone ?: 'No phone recorded' }}</td><td>{{ $row->usual_days ?: 'No clear pattern' }}</td><td>About every {{ $row->normal_gap }} days</td><td>{{ $row->last_purchase->format('d M Y') }}</td><td><span class="status-pill status-pill--alarm">{{ $row->days_absent }} days</span></td></tr>
        @empty<tr><td colspan="6" class="empty">No previously frequent customer currently meets the follow-up rule.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endif

@endif

@if($customerView === 'accounts')
<section class="card customer-directory">
    <div class="customer-directory__head">
        <div><p class="card-label">Customer directory</p><h3>Contact and purchase details</h3></div>
        <form method="GET" action="{{ route('owner.customers') }}" class="customer-account-filters">
            <input type="hidden" name="view" value="accounts">
            <label>Search<input name="search" value="{{ $search }}" placeholder="Name, phone or email"></label>
            <label>Status<select name="status"><option value="active" @selected($statusFilter==='active')>Active customers</option><option value="archived" @selected($statusFilter==='archived')>Archived customers</option><option value="all" @selected($statusFilter==='all')>All customers</option></select></label>
            <label>Account position<select name="account"><option value="all" @selected($accountFilter==='all')>All account types</option><option value="prepayment" @selected($accountFilter==='prepayment')>Has available prepayment</option><option value="owing" @selected($accountFilter==='owing')>Owes the farm</option><option value="frequent" @selected($accountFilter==='frequent')>Frequent customer</option><option value="one-time" @selected($accountFilter==='one-time')>One-time customer</option><option value="no-purchases" @selected($accountFilter==='no-purchases')>No purchases</option></select></label>
            <label>Purchased from<input type="date" name="purchased_from" value="{{ $purchasedFrom }}"></label>
            <label>Purchased to<input type="date" name="purchased_to" value="{{ $purchasedTo }}"></label>
            <label>Created from<input type="date" name="created_from" value="{{ $createdFrom }}"></label>
            <label>Created to<input type="date" name="created_to" value="{{ $createdTo }}"></label>
            <button class="btn btn--small">Apply filters</button>
            @if($search||$statusFilter!=='active'||$accountFilter!=='all'||$purchasedFrom||$purchasedTo||$createdFrom||$createdTo)<a href="{{ route('owner.customers',['view'=>'accounts']) }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Contact</th><th>Date created</th><th>Purchases</th><th>Last purchase</th><th class="num">Total bought</th><th class="num">Prepayment available</th><th class="num">Balance due</th><th>Type</th></tr></thead><tbody>
        @forelse($customers as $customer)
            <tr><td><a class="customer-name-link" href="{{ route('customer-accounts.show',$customer) }}"><strong>{{ $customer->name }}</strong></a>@if($customer->address)<small class="table-sub">{{ Str::limit($customer->address, 45) }}</small>@endif</td><td>{{ $customer->phone ?: 'No phone' }}<small class="table-sub">{{ $customer->email ?: 'No email' }}</small></td><td>{{ $customer->created_at->format('d M Y') }}</td><td>{{ number_format($customer->purchase_count) }}</td><td>{{ $customer->last_purchase_on ? \Illuminate\Support\Carbon::parse($customer->last_purchase_on)->format('d M Y') : 'Never' }}</td><td class="num">UGX {{ number_format((float) $customer->total_purchased) }}</td><td class="num">UGX {{ number_format((float)$customer->prepayment_received-(float)$customer->prepayment_used) }}</td><td class="num"><strong class="{{ $customer->outstanding_balance > 0 ? 'text-alarm' : '' }}">UGX {{ number_format((float) $customer->outstanding_balance) }}</strong></td><td><span class="status-pill">{{ $customer->purchase_count >= 2 ? 'Frequent' : ($customer->purchase_count === 1 ? 'One-time' : 'No purchase') }}</span></td></tr>
        @empty<tr><td colspan="9" class="empty">{{ $search ? 'No customer matches this search.' : 'No customers match the selected filters.' }}</td></tr>@endforelse
    </tbody></table></div>
    {{ $customers->links() }}
</section>
@endif
<style>
.customer-analysis-menu{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}.customer-analysis-menu a{display:grid;gap:5px;min-height:130px;padding:18px;border:1px solid #e6d9cc;border-radius:14px;background:#fff;color:#2f2119;text-decoration:none;box-shadow:0 5px 16px rgba(62,39,25,.05)}.customer-analysis-menu a:hover{border-color:#d9862f;transform:translateY(-1px)}.customer-analysis-menu a.is-active{border-color:#8b1e2d;box-shadow:inset 4px 0 #8b1e2d,0 5px 16px rgba(62,39,25,.06)}.customer-analysis-menu span{color:#9d2930;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.customer-analysis-menu strong{font-size:16px}.customer-analysis-menu small{color:#765e4d;line-height:1.45}.customer-account-filters{display:flex;align-items:end;justify-content:flex-end;gap:9px;flex-wrap:wrap}.customer-account-filters label{display:grid;gap:4px;color:#795336;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.customer-account-filters input,.customer-account-filters select{min-width:180px;padding:9px 10px;border:1px solid #dfd1c3;border-radius:9px;background:#fff;color:#2f2119}.customer-account-filters a{padding:9px;color:#8b1e2d;font-weight:700}.customer-product-analysis,.customer-follow-up{margin-top:0}.customer-analysis-filter{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.customer-analysis-filter label{display:grid;gap:5px;font-size:11px;font-weight:800;text-transform:uppercase;color:#795336}.customer-analysis-filter input,.customer-analysis-filter select{min-width:190px;padding:10px;border:1px solid #dfd1c3;border-radius:9px;background:#fff}.analysis-summary{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;padding:13px 16px;margin:14px 0;background:#fff7ea;border-left:4px solid #df8b2d;border-radius:10px}.analysis-summary strong{font-size:20px}.analysis-summary small{color:#805f49}.customer-grade--platinum{background:#5d3228;color:#fff}.customer-grade--gold{background:#e9a23b;color:#23170d}.customer-grade--silver{background:#dfd9d1}.customer-grade--regular{background:#f6eee5}.status-pill--alarm{background:#fbe5e4;color:#a92930}.table-note{font-size:12px;color:#805f49}@media(max-width:900px){.customer-analysis-menu{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-account-filters{justify-content:flex-start}}@media(max-width:560px){.customer-analysis-menu{grid-template-columns:1fr}.customer-analysis-menu a{min-height:auto}.customer-account-filters label,.customer-account-filters input,.customer-account-filters select{width:100%}}
</style>
@endsection
