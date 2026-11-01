@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
@php($customerView = request('view', 'accounts'))
@php($analysisType = in_array(request('analysis'), ['grading', 'loyalty', 'grading-loyalty', 'follow-up'], true) ? request('analysis') : 'grading')
@unless(in_array($customerView, ['analysis', 'add'], true))
<div class="page-head page-head--split">
    <div><span class="finance-panel-kicker">Company customer records</span><h2>{{ $customerView === 'add' ? 'Add customer' : ($customerView === 'analysis' ? 'Customer analysis' : 'Customer accounts') }}</h2><p>{{ $customerView === 'add' ? 'Register a customer and their contact and credit details.' : ($customerView === 'analysis' ? 'Review customer grades, purchasing patterns, revenue contribution, and customers requiring follow-up.' : 'View customer contact details, prepayments, purchases, and outstanding balances.') }}</p></div>
    <a class="btn btn--quiet" href="{{ route('dashboard') }}">Back to dashboard</a>
</div>
@endunless
@if(session('status'))<div class="saved">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif
@if($customerView === 'add' && $canCreateCustomer)
<section class="cr">
 <header class="cr-head"><span class="cr-head__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><circle cx="4.5" cy="9.5" r="2"/><path d="M3.5 19c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5M14.5 14.2c3.2-.4 6.5 1.4 6.5 4.8M1 18c.2-2 1.4-3.4 3.2-3.8"/></svg></span><div><p class="cr-kicker">Company customer records</p><h2>Add customer</h2><p>Register a customer and their contact and address details.</p></div></header>
 <form method="POST" action="{{ route('sales.customers.store') }}" class="cr-card" id="add-customer">@csrf
  <div class="cr-card__head"><span class="cr-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></span><div><h3>Customer Details</h3><p>Enter the customer's basic information.</p></div></div>
  @if($errors->any())<p class="cr-error">{{ $errors->first() }}</p>@endif
  <div class="cr-grid">
   <div class="cr-field"><span class="cr-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></span><label><span>Name <b>*</b></span><input name="name" value="{{ old('name') }}" placeholder="Enter customer name" required maxlength="160"></label></div>
   <div class="cr-field"><span class="cr-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg></span><label><span>Phone</span><input name="phone" value="{{ old('phone') }}" placeholder="Enter phone number" inputmode="tel" maxlength="40"></label></div>
   <div class="cr-field"><span class="cr-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" placeholder="Enter email address" maxlength="160"></label></div>
   <div class="cr-field"><span class="cr-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span><label><span>Address</span><input name="address" value="{{ old('address') }}" placeholder="Enter address" maxlength="1000"></label></div>
  </div>
  <div class="cr-actions"><a class="cr-btn cr-btn--ghost" href="{{ auth()->user()->canAccess('receivables') ? route('finance.receivables.index') : route('owner.customers') }}">Cancel</a><button class="cr-btn cr-btn--primary">Add customer</button></div>
 </form>
</section>
@endif

@if($customerView === 'analysis')


@if(in_array($analysisType, ['loyalty', 'grading-loyalty'], true))
<div class="customer-analysis-grid">
    <section class="card customer-frequency">
        <div><p class="card-label">Customer frequency</p><h3>Frequent versus one-time clients</h3><p>A frequent customer has completed two or more purchases.</p></div>
        @php($analysed = $repeatCustomers + $oneTimeCustomers)
        @if($analysed > 0)
            <div class="frequency-bar"><i style="width:{{ $repeatCustomers / $analysed * 100 }}%"></i></div>
            <div class="frequency-legend"><span><b>{{ $repeatCustomers }}</b> Frequent customers</span><span><b>{{ $oneTimeCustomers }}</b> One-time customers</span></div>
        @else
            <div class="customer-analysis-empty"><strong>Customer names are ready; sales activity has not started.</strong><p>The {{ number_format($totalCustomers) }} registered {{ Str::plural('customer', $totalCustomers) }} will be classified automatically once sales are recorded against their names.</p></div>
        @endif
        @if($customersWithoutSales)<small>{{ $customersWithoutSales }} registered customer(s) have no sale recorded yet.</small>@endif
    </section>
    <section class="card customer-leaders"><p class="card-label">Most loyal customers</p><h3>Top clients by purchase frequency</h3>@forelse($topCustomers as $customer)<div><span><strong>{{ $loop->iteration }}. {{ $customer->name }}</strong><small>{{ $customer->purchase_count }} purchases</small></span><b>@money($customer->total_purchased)</b></div>@empty<div class="customer-analysis-empty"><p>Top clients will appear here after sales are recorded.</p></div>@endforelse</section>
</div>
@endif

@if(in_array($analysisType, ['grading', 'grading-loyalty'], true))
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
    <div class="analysis-summary"><span>{{ $selectedProduct?->name ?? 'All products' }}</span><strong>@money($productRevenue, 2)</strong><small>Total revenue for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m',$selectedMonth)->format('F Y') }}</small></div>
    <div class="table-wrap"><table><thead><tr><th>Customer</th><th>Product</th><th class="num">Total quantity</th><th class="num">Total amount</th><th class="num">Product revenue share</th><th class="num">Average price</th><th>Monthly grade</th></tr></thead><tbody>
        @forelse($productAnalysis as $row)<tr>
            <td>@if($row->customer_id)<a class="customer-name-link" href="{{ route('customer-accounts.show',$row->customer_id) }}"><strong>{{ $row->customer_name }}</strong></a>@else<strong>{{ $row->customer_name }}</strong>@endif<small class="table-sub">{{ $row->purchase_days }} buying {{ Str::plural('day',$row->purchase_days) }}</small></td>
            <td>{{ $row->product_name }}</td><td class="num"><strong>{{ number_format((float)$row->quantity,2) }}</strong> {{ $row->unit }}</td><td class="num">@money($row->amount, 2)</td><td class="num">{{ number_format($row->share,1) }}%</td><td class="num">@money($row->average_price, 2) / {{ Str::singular($row->unit) }}</td><td><span class="status-pill customer-grade customer-grade--{{ strtolower($row->grade) }}">{{ $row->grade }}</span></td>
        </tr>@empty<tr><td colspan="7" class="empty">No sales match this month and product.</td></tr>@endforelse
    </tbody></table></div>
    <p class="table-note">Grades show contribution to the filtered product revenue: Platinum 10%+, Gold 5–9.9%, Silver 2–4.9%, Regular below 2%.</p>
</section>
@endif

@if($analysisType === 'follow-up')
<section class="card customer-follow-up">
    <div class="customer-directory__head"><div><p class="card-label">Retention follow-up</p><h3>Frequent customers who have stopped returning</h3><p>A customer appears here after at least two purchases and an absence longer than both 21 days and twice their normal buying interval.</p></div><strong>{{ $followUps->count() }} to contact</strong></div>
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
            <label>Status<select name="status"><option value="active" @selected($statusFilter==='active')>Active customers</option><option value="archived" @selected($statusFilter==='archived')>Archived (no purchase for a year)</option><option value="all" @selected($statusFilter==='all')>All customers</option></select></label>
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
            <tr><td><a class="customer-name-link" href="{{ route('customer-accounts.show',$customer) }}"><strong>{{ $customer->name }}</strong></a>@if($customer->address)<small class="table-sub">{{ Str::limit($customer->address, 45) }}</small>@endif</td><td class="u-nowrap">{{ $customer->phone ?: '—' }}@if($customer->email)<small class="table-sub">{{ $customer->email }}</small>@endif</td><td>{{ $customer->created_at->format('d M Y') }}</td><td>{{ number_format($customer->purchase_count) }}</td><td>{{ $customer->last_purchase_on ? \Illuminate\Support\Carbon::parse($customer->last_purchase_on)->format('d M Y') : 'Never' }}</td><td class="num">@money($customer->total_purchased)</td><td class="num">@money($customer->prepayment_received-(float)$customer->prepayment_used)</td><td class="num"><strong class="{{ $customer->outstanding_balance > 0 ? 'text-alarm' : '' }}">@money($customer->outstanding_balance)</strong></td><td><span class="status-pill">{{ $customer->purchase_count >= 2 ? 'Frequent' : ($customer->purchase_count === 1 ? 'One-time' : 'No purchase') }}</span></td></tr>
        @empty<tr><td colspan="9" class="empty">{{ $search ? 'No customer matches this search.' : 'No customers match the selected filters.' }}</td></tr>@endforelse
    </tbody></table></div>
    {{ $customers->links() }}
</section>
@endif
@pushOnce('styles')<style>
.customer-analysis-menu{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.customer-analysis-menu a{display:grid;gap:5px;min-height:130px;padding:18px;border:1px solid #e6d9cc;border-radius:14px;background:#fff;color:#2f2119;text-decoration:none;box-shadow:0 5px 16px rgba(62,39,25,.05)}.customer-analysis-menu a:hover{border-color:#d9862f;transform:translateY(-1px)}.customer-analysis-menu a.is-active{border-color:#8b1e2d;box-shadow:inset 4px 0 #8b1e2d,0 5px 16px rgba(62,39,25,.06)}.customer-analysis-menu span{color:#9d2930;font-size:10px;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.customer-analysis-menu strong{font-size:16px}.customer-analysis-menu small{color:#765e4d;line-height:1.45}.customer-account-filters{display:flex;align-items:end;justify-content:flex-end;gap:9px;flex-wrap:wrap}.customer-account-filters label{display:grid;gap:4px;color:#795336;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.customer-account-filters input,.customer-account-filters select{min-width:180px;padding:9px 10px;border:1px solid #dfd1c3;border-radius:9px;background:#fff;color:#2f2119}.customer-account-filters a{padding:9px;color:#8b1e2d;font-weight:700}.customer-product-analysis,.customer-follow-up{margin-top:0}.customer-analysis-filter{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.customer-analysis-filter label{display:grid;gap:5px;font-size:11px;font-weight:800;text-transform:uppercase;color:#795336}.customer-analysis-filter input,.customer-analysis-filter select{min-width:190px;padding:10px;border:1px solid #dfd1c3;border-radius:9px;background:#fff}.analysis-summary{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;padding:13px 16px;margin:14px 0;background:#fff7ea;border-left:4px solid #df8b2d;border-radius:10px}.analysis-summary strong{font-size:20px}.analysis-summary small{color:#805f49}.customer-grade--platinum{background:#5d3228;color:#fff}.customer-grade--gold{background:#e9a23b;color:#23170d}.customer-grade--silver{background:#dfd9d1}.customer-grade--regular{background:#f6eee5}.status-pill--alarm{background:#fbe5e4;color:#a92930}.table-note{font-size:12px;color:#805f49}@media(max-width:900px){.customer-analysis-menu{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-account-filters{justify-content:flex-start}}@media(max-width:560px){.customer-analysis-menu{grid-template-columns:1fr}.customer-analysis-menu a{min-height:auto}.customer-account-filters label,.customer-account-filters input,.customer-account-filters select{width:100%}}
</style>@endPushOnce
@endsection
