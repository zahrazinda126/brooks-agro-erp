@extends('layouts.app')
@section('title', 'Shop Sales')
@section('content')
@if($screen==='reports')<x-report-header eyebrow="Sales & customers" title="Sales Report" description="Sales performance, customer frequency, products sold and receipts for the selected period." :period="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>@endif
<div class="page-head">
    <p class="eyebrow">Retail shop</p>
    <h2>{{ ['setup' => 'Selling Prices', 'reports' => 'Sales Report'][$screen] ?? 'Record Daily Sale' }}</h2>
    <p>{{ ['setup' => 'Update the current market price used when recording sales.', 'reports' => 'Review sales, customers and products for the selected period.'][$screen] ?? 'Enter a completed customer sale and issue its receipt.' }}</p>
</div>

<nav class="unit-tabs" aria-label="Sales workspace">
    @if($screen !== 'setup')
        <a class="{{ $screen === 'sale' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'sale']) }}">Record Daily Sale</a>
        <a class="{{ $screen === 'reports' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'reports']) }}">Sales Report</a>
    @else
        <a class="active" href="{{ route('sales.index', ['screen' => 'setup']) }}">Selling Prices</a>
    @endif
</nav>

@if($screen === 'sale')
<section class="exec-panel">
    <div class="section-heading"><div><p class="card-label">Live egg availability</p><h3>Eggs ready for sale</h3><p>Approved collections minus completed sales and plus recorded returns.</p></div><strong>{{ number_format($eggStock->sum('eggs')) }} eggs</strong></div>
    <div class="summary-grid">
        @forelse($eggStock as $stock)
            <article class="summary-card"><span>{{ $stock['name'] }}</span><strong>{{ number_format($stock['trays']) }} trays</strong><small>{{ number_format($stock['loose']) }} loose eggs · {{ number_format($stock['eggs']) }} eggs total</small></article>
        @empty
            <div class="notice">No egg products are linked to egg grades yet.</div>
        @endforelse
    </div>
</section>
<section class="exec-panel">
    <form method="POST" action="{{ route('sales.store') }}" id="saleForm" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="payment_type" value="cash">
        <input type="hidden" name="amount_paid" value="0">
        <input type="hidden" name="discount" value="0">
        <div class="form-grid">
            <div class="field"><label>Sale date</label><input type="date" name="sale_date" value="{{ old('sale_date', now()->toDateString()) }}" required></div>
            <div class="field"><label>Customer name</label><input name="walk_in_customer" value="{{ old('walk_in_customer') }}" list="sales-customer-list" placeholder="Type or choose a customer"><datalist id="sales-customer-list">@foreach($customers as $customer)<option value="{{ $customer->name }}"></option>@endforeach</datalist><span class="field-hint">Start typing to find an existing customer. A new name is saved automatically; leave blank for a one-time customer.</span></div>
            <div class="field"><label>Payment method</label><select name="payment_method" id="sale-payment-method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="agent">Agent</option><option value="mobile_money">Mobile money</option><option value="prepayment">Customer prepayment credit</option><option value="in_kind">In kind / barter</option></select><span class="field-hint">Prepayment uses money the customer paid earlier; it does not record new cash.</span></div>
            <div class="field" id="sale-merchant-field" hidden><label>Merchant code</label><input name="merchant_code" id="sale-merchant-code" value="{{ old('merchant_code') }}" placeholder="Enter the mobile-money merchant code"><span class="field-hint">Required for Mobile Money.</span></div>
            <div class="field" id="sale-receipt-field" hidden><label>Payment receipt</label><input type="file" name="payment_receipt" id="sale-payment-receipt" accept=".jpg,.jpeg,.png,.pdf"><span class="field-hint">Required for Bank or Agent payments. Maximum 5 MB.</span></div>
            <div class="field"><label>Customer account credit</label><div class="field-readonly">Applied automatically from the selected customer’s available prepayment balance.</div><span class="field-hint">No prepayment ID is required.</span></div>
            <div class="field"><label>Receiving account</label><select name="farm_account">@foreach($accounts as $account)<option value="{{ $account }}">{{ $account }}</option>@endforeach</select></div>
        </div>

        <section class="entry-lines">
            <div class="entry-lines__head"><div><p class="eyebrow">Items</p><h3>Products sold</h3></div><strong class="entry-total">Total <span id="invoiceTotal">UGX 0</span></strong></div>
            <div id="saleLines" class="entry-lines__list"></div>
        </section>
        <template id="saleLineTemplate">
            <article class="entry-line">
                <div class="entry-line__title"><strong data-line-number>Item</strong><strong data-line-total>UGX 0</strong></div>
                <div class="entry-line__grid">
                    <div class="field field--wide"><label>Product</label><select data-product required><option value="">Choose product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ (float)$product->unit_price > 0 ? $product->unit_price : '' }}" data-loose-price="{{ $product->loose_unit_price }}" data-unit="{{ $product->unit }}" data-category="{{ $product->category }}" data-off-layer="{{ $product->unit==='bird' && Str::contains(Str::lower($product->name),'layer') ? '1' : '0' }}" data-eggs-per-tray="{{ $product->egg_grade_id && $product->eggGrade?->is_defect ? ($product->eggGrade?->eggs_per_tray ?? 30) : '' }}">{{ ucfirst($product->category) }} &middot; {{ $product->name }} ({{ $product->egg_grade_id && $product->eggGrade?->is_defect ? 'tray or individual egg' : $product->unit }})</option>@endforeach<option value="__unlisted__">+ Unlisted / type a product</option></select></div>
                    <div class="field field--wide" data-custom-product hidden><label>Unlisted product name</label><input data-description placeholder="Type the product sold"></div>
                    <div class="field" data-custom-category-field hidden><label>Category</label><select data-custom-category>@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></div>
                    <div class="field"><label>Sale unit</label><input data-unit readonly><select data-unit-choice hidden><option value="">Choose unit</option>@foreach(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'] as $unit)<option value="{{ $unit }}">{{ ucfirst($unit) }}</option>@endforeach</select><select data-egg-unit-choice hidden><option value="tray">Whole trays</option><option value="egg">Loose individual eggs</option></select></div>
                    <div class="field"><label>Quantity</label><input data-quantity type="number" step="0.01" min="0.01" required></div>
                    <div class="field" data-poultry-house-field hidden><label>Poultry house supplying the birds</label><select data-poultry-house disabled><option value="">Choose occupied house</option>@foreach($poultryHouses as $house)<option value="{{ $house->id }}">House {{ $house->displayNumber() }} &middot; {{ $house->batch?->code }} &middot; {{ number_format($house->bird_quantity) }} birds</option>@endforeach</select><small class="field-hint">The sold birds will be deducted from this house. When none remain, the house becomes free.</small></div>
                    <div class="field"><label>Unit price (UGX)</label><input data-price type="number" step="0.01" min="0" readonly required><small class="field-hint" data-price-hint>Select a product to use its selling price.</small></div>
                </div>
                <input data-category type="hidden"><div class="entry-line__actions"><button type="button" class="btn btn--quiet btn--small" data-remove>Remove item</button></div>
            </article>
        </template>
        <div class="form-actions"><button type="button" class="btn btn--quiet" id="addSaleLine">Add item</button><button class="btn">Complete Sale &amp; Print Receipt</button></div>
        <div class="field"><label>Sale note</label><textarea name="notes" placeholder="Optional"></textarea></div>
    </form>
</section>
<script>
(() => {
    const paymentMethod=document.getElementById('sale-payment-method'),merchantField=document.getElementById('sale-merchant-field'),merchantCode=document.getElementById('sale-merchant-code'),receiptField=document.getElementById('sale-receipt-field'),paymentReceipt=document.getElementById('sale-payment-receipt');
    const syncPaymentEvidence=()=>{const mobile=paymentMethod.value==='mobile_money',evidence=['bank','agent'].includes(paymentMethod.value);merchantField.hidden=!mobile;merchantCode.required=mobile;receiptField.hidden=!evidence;paymentReceipt.required=evidence;};paymentMethod.addEventListener('change',syncPaymentEvidence);syncPaymentEvidence();
    const body = document.getElementById('saleLines'), template = document.getElementById('saleLineTemplate'); let index = 0;
    const total = () => { let sum = 0; body.querySelectorAll('.entry-line').forEach(row => { const value = (parseFloat(row.querySelector('input[data-quantity]').value) || 0) * (parseFloat(row.querySelector('input[data-price]').value) || 0); row.querySelector('[data-line-total]').textContent = 'UGX ' + Math.round(value).toLocaleString(); sum += value; }); document.getElementById('invoiceTotal').textContent = 'UGX ' + Math.round(sum).toLocaleString(); };
    const add = () => {
        const row = template.content.firstElementChild.cloneNode(true), i = index++, product = row.querySelector('select[data-product]'), unit = row.querySelector('input[data-unit]'), unitChoice = row.querySelector('select[data-unit-choice]'), eggUnitChoice = row.querySelector('select[data-egg-unit-choice]'), quantity = row.querySelector('input[data-quantity]'), price = row.querySelector('input[data-price]'), category = row.querySelector('input[type="hidden"][data-category]'), customCategory = row.querySelector('select[data-custom-category]'), description = row.querySelector('input[data-description]'), customBlock = row.querySelector('[data-custom-product]'), customCategoryField = row.querySelector('[data-custom-category-field]'), priceHint = row.querySelector('[data-price-hint]'), houseField=row.querySelector('[data-poultry-house-field]'), house=row.querySelector('[data-poultry-house]');
        row.querySelector('[data-line-number]').textContent = `Item ${i + 1}`; product.name = `lines[${i}][product_id]`; unit.name = `lines[${i}][unit]`; quantity.name = `lines[${i}][quantity]`; price.name = `lines[${i}][unit_price]`; category.name = `lines[${i}][category]`; description.name = `lines[${i}][description]`; house.name=`lines[${i}][poultry_house_id]`;
        const sync = () => {
            const option = product.selectedOptions[0], custom = product.value === '__unlisted__', savedPrice = option?.dataset.price || '', loosePrice = option?.dataset.loosePrice || '', eggsPerTray = parseInt(option?.dataset.eggsPerTray || '0', 10), eggProduct = eggsPerTray > 0;
            product.name = custom ? '' : `lines[${i}][product_id]`;
            customBlock.hidden = !custom; customCategoryField.hidden = !custom; description.disabled = !custom; description.required = custom; customCategory.disabled = !custom;
            unit.hidden = custom || eggProduct; unit.disabled = custom || eggProduct; unit.name = !custom && !eggProduct ? `lines[${i}][unit]` : ''; unitChoice.hidden = !custom; unitChoice.disabled = !custom; unitChoice.required = custom; if(custom) unitChoice.name = `lines[${i}][unit]`; else unitChoice.name = '';
            eggUnitChoice.hidden = !eggProduct; eggUnitChoice.disabled = !eggProduct; eggUnitChoice.required = eggProduct; eggUnitChoice.name = eggProduct ? `lines[${i}][unit]` : '';
            unit.value = custom ? '' : (option?.dataset.unit || ''); category.value = custom ? customCategory.value : (option?.dataset.category || '');
            const offLayer=option?.dataset.offLayer==='1'; houseField.hidden=!offLayer; house.disabled=!offLayer; house.required=offLayer; quantity.step=offLayer?'1':quantity.step; quantity.min=offLayer?'1':quantity.min;
            const looseEggs = eggProduct && eggUnitChoice.value === 'egg'; price.value = custom ? '' : (looseEggs ? loosePrice : savedPrice); price.readOnly = !custom && price.value !== ''; quantity.step = (looseEggs||offLayer) ? '1' : '0.01'; quantity.min = (looseEggs||offLayer) ? '1' : '0.01'; priceHint.textContent = looseEggs ? 'Saved individual-egg selling price applied automatically.' : (price.readOnly ? 'Saved selling price applied automatically.' : 'No selling price is set; enter the price used for this sale.');
            total();
        };
        product.onchange = sync; eggUnitChoice.onchange = sync; customCategory.onchange = () => category.value = customCategory.value; unitChoice.onchange = () => unit.value = unitChoice.value; quantity.oninput = total; price.oninput = total; row.querySelector('[data-remove]').onclick = () => { row.remove(); total(); }; body.appendChild(row);
    };
    document.getElementById('addSaleLine').onclick = add; add();
})();
</script>
@elseif($screen === 'setup')
<section class="exec-panel"><div class="section-heading"><div><p class="card-label">Product catalogue</p><h3>Add an item to be priced</h3></div></div><form method="POST" action="{{ route('sales.products.store') }}" class="form-grid">@csrf<div class="field"><label>Item code</label><input name="code" required placeholder="e.g. EGG-DAMAGED"></div><div class="field"><label>Item name</label><input name="name" required></div><div class="field"><label>Category</label><select name="category_choice" required>@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach<option value="custom">Other category</option></select></div><div class="field"><label>Other category</label><input name="custom_category"></div><div class="field"><label>Standard unit</label><select name="unit" required>@foreach(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'] as $unit)<option value="{{ $unit }}">{{ ucfirst($unit) }}</option>@endforeach</select></div><div class="field"><label>Standard selling price (UGX)</label><input type="number" name="unit_price" min="0" step="0.01" required></div><div class="field"><label>Individual / loose price (UGX)</label><input type="number" name="loose_unit_price" min="0" step="0.01"><small>Use when the item can also be sold individually.</small></div><div class="field"><label>Opening stock (optional)</label><input type="number" name="stock_quantity" min="0" step="0.01"></div><div class="field field--wide"><label>Notes</label><textarea name="notes"></textarea></div><button class="btn">Add priced item</button></form></section>
@if($priceHistory->isNotEmpty())
<section class="price-change-notice"><div><p class="eyebrow">Latest price update</p><strong>{{ $priceHistory->first()->description }}</strong><span>{{ $priceHistory->first()->created_at->format('d M Y, H:i') }} &middot; Previous UGX {{ number_format((float) data_get($priceHistory->first()->old_values,'unit_price')) }} &rarr; New UGX {{ number_format((float) data_get($priceHistory->first()->new_values,'unit_price')) }}</span></div><span>Price history is retained</span></section>
@endif
<section class="exec-panel table-wrap">
    <div class="section-head"><div><p class="eyebrow">Market pricing</p><h3>Current selling prices</h3></div><p>Submit a proposed price. The current price remains active until the Director approves it.</p></div>
    <table class="exec-table"><thead><tr><th>Category</th><th>Product</th><th>Unit</th><th>Current price</th><th>New market price</th></tr></thead><tbody>
    @forelse($products as $product)
        <tr><td>{{ ucfirst($product->category) }}</td><td><strong>{{ $product->name }}</strong>@php $lastChange = $priceHistory->firstWhere('auditable_id', $product->id); @endphp @if($lastChange)<small class="data-sub">Last set by {{ $lastChange->user?->displayName() ?? $lastChange->username ?? 'System' }}<br>{{ $lastChange->created_at->format('d M Y, H:i') }}</small>@endif</td><td>{{ $product->unit }}@if($product->loose_unit_price)<small>Individual: UGX {{ number_format($product->loose_unit_price) }}</small>@endif</td><td>UGX {{ number_format((float) $product->unit_price, 2) }}</td><td><form method="POST" action="{{ route('sales.products.price', $product) }}" class="form-actions price-update-form" data-product="{{ $product->name }}" data-current-price="{{ (float) $product->unit_price }}">@csrf @method('PUT')<input type="hidden" name="expected_updated_at" value="{{ $product->updated_at->format('Y-m-d H:i:s') }}"><input type="hidden" name="confirm_price_change" value="0" data-confirm-price><input aria-label="New standard price" type="number" min="0" step="0.01" name="unit_price" value="{{ $product->unit_price }}" required>@if($product->egg_grade_id && $product->eggGrade?->is_defect)<input aria-label="Individual egg price" title="Individual egg price" type="number" min="0" step="0.01" name="loose_unit_price" value="{{ $product->loose_unit_price ?: 250 }}" required>@endif<input aria-label="Effective date" type="date" name="effective_from" value="{{ now()->toDateString() }}" required><button class="btn btn--small">Update price</button></form></td></tr>
    @empty<tr><td colspan="5">No products have been configured by stock control.</td></tr>@endforelse
    </tbody></table>
</section>
<section class="exec-panel"><div class="section-heading"><div><p class="card-label">Accountability</p><h3>Recent price changes</h3></div></div><div class="price-history">@forelse($priceHistory as $change)<article><div><strong>{{ $change->description }}</strong><small>{{ $change->created_at->format('d M Y, H:i') }} &middot; {{ $change->user?->displayName() ?? $change->username ?? 'System' }}</small></div><span>UGX {{ number_format((float)data_get($change->old_values,'unit_price')) }} &rarr; <b>UGX {{ number_format((float)data_get($change->new_values,'unit_price')) }}</b></span></article>@empty<p class="muted">No price changes have been recorded yet.</p>@endforelse</div></section>
<script>document.querySelectorAll('.price-update-form').forEach(form=>{form.querySelector('button').textContent='Request Director approval';form.addEventListener('submit',event=>{const input=form.querySelector('[name="unit_price"]'),current=Number(form.dataset.currentPrice),next=Number(input.value);if(next===current)return;const message=`${form.dataset.product} is currently UGX ${current.toLocaleString()}. Send the proposed price of UGX ${next.toLocaleString()} to the Director for approval?`;if(!window.confirm(message)){event.preventDefault();return;}form.querySelector('[data-confirm-price]').value='1';});});</script>
@else
@php
    $reportMax = max(1, $monthly->max('amount'));
    $topCustomer = $customerReport->first();
    $topProduct = $productReport->first();
@endphp
<section class="sales-report">
    <form class="sales-report__filter" method="GET" action="{{ route('sales.index') }}">
        <input type="hidden" name="screen" value="reports">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
        <button class="btn">Apply dates</button>
        <button class="btn btn--quiet" type="button" onclick="window.print()">Print report</button>
    </form>
    <div class="sales-report__kpis">
        <article><small>Sales value</small><strong>UGX {{ number_format($reportTotal) }}</strong><span>{{ $reportSales->count() }} receipt{{ $reportSales->count() === 1 ? '' : 's' }}</span></article>
        <article><small>Average receipt</small><strong>UGX {{ number_format($reportSales->count() ? $reportTotal / $reportSales->count() : 0) }}</strong><span>For the selected period</span></article>
        <article><small>Top customer</small><strong>{{ $topCustomer['name'] ?? 'No sales yet' }}</strong><span>{{ $topCustomer ? 'UGX '.number_format($topCustomer['amount']) : 'No customer activity' }}</span></article>
        <article><small>Best-selling product</small><strong>{{ $topProduct['name'] ?? 'No sales yet' }}</strong><span>{{ $topProduct ? number_format($topProduct['quantity'], 2).' units' : 'No product activity' }}</span></article>
    </div>
    <div class="sales-report__grid">
        <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Six-month trend</p><h3>Sales movement</h3></div></div><div class="sales-chart">@foreach($monthly as $month)<div><span><i style="height:{{ $month['amount'] > 0 ? max(5, $month['amount'] / $reportMax * 100) : 2 }}%"></i></span><small>{{ $month['label'] }}</small><b>UGX {{ number_format($month['amount']) }}</b></div>@endforeach</div></section>
        <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Customer analysis</p><h3>Frequent customers</h3></div></div><div class="sales-ranking">@forelse($customerReport->take(8) as $row)<div><b>{{ $row['rank'] }}</b><span><strong>{{ $row['name'] }}</strong><small>{{ $row['sales'] }} purchase{{ $row['sales'] === 1 ? '' : 's' }} &middot; {{ number_format($row['share'],1) }}%</small></span><em>UGX {{ number_format($row['amount']) }}</em></div>@empty<p class="muted">No customer sales in this period.</p>@endforelse</div></section>
    </div>
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Entry audit</p><h3>Recorded sales</h3></div></div><table><thead><tr><th>Business date</th><th>Recorded at</th><th>Recorded by</th><th>Receipt</th><th>Customer</th><th>Products sold</th><th>Total</th></tr></thead><tbody>@forelse($reportSales->sortByDesc('created_at') as $sale)<tr><td>{{ $sale->sale_date->format('d M Y') }}</td><td>{{ $sale->created_at->format('d M Y, H:i') }}@if($sale->updated_at->gt($sale->created_at->copy()->addSecond()))<small class="data-sub">Updated {{ $sale->updated_at->format('d M Y, H:i') }}</small>@endif</td><td>{{ $sale->seller?->displayName() ?? 'System' }}</td><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer_name }}</td><td>@foreach($sale->lines as $line)<div><strong>{{ $line->description }}</strong> &middot; {{ number_format($line->quantity,2) }} {{ Str::plural($line->unit,$line->quantity) }}</div>@endforeach</td><td><strong>UGX {{ number_format($sale->total) }}</strong></td></tr>@empty<tr><td colspan="7">No sales found for these dates.</td></tr>@endforelse</tbody></table></section>
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Product performance</p><h3>What was sold</h3></div></div><table><thead><tr><th>Product</th><th>Quantity</th><th>Sales value</th></tr></thead><tbody>@forelse($productReport as $row)<tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ number_format($row['quantity'],2) }} {{ Str::plural($row['unit'], $row['quantity']) }}</td><td>UGX {{ number_format($row['amount']) }}</td></tr>@empty<tr><td colspan="3">No sales found for these dates.</td></tr>@endforelse</tbody></table></section>
</section>
@endif
@endsection
