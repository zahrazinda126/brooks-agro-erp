@extends('layouts.app')
@section('title', $screen === 'analysis' ? (['summary'=>'Sales Summary','monthly'=>'Month-on-Month Analysis','product_amount'=>'Product Type by Amount','product_quantity'=>'Product Type by Quantity','revenue'=>'Revenue Analysis'][$analysisView] ?? 'Sales Analysis') : (['inventory'=>'Inventory','setup'=>'Selling Prices','reports'=>'Sales Report','balancing'=>'Daily Sales Balancing','receipt_archive'=>'Receipt Archive'][$screen] ?? 'Record Daily Sale'))
@section('content')
@if($screen==='reports')<x-report-header eyebrow="Sales & customers" title="Sales Report" description="Sales performance, customer frequency, products sold and receipts for the selected period." :period="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>@endif
@if(!in_array($screen, ['inventory', 'sale'], true))
<div class="page-head">
    <p class="eyebrow">{{ $screen === 'analysis' ? (['summary'=>'Sales Summary','monthly'=>'Month-on-Month Analysis','product_amount'=>'Product Type by Amount','product_quantity'=>'Product Type by Quantity','revenue'=>'Revenue Analysis'][$analysisView] ?? 'Sales Analysis') : (['setup'=>'Selling Prices','reports'=>'Sales Report','balancing'=>'Daily Sales Balancing','receipt_archive'=>'Receipt Archive'][$screen] ?? 'Record Daily Sale') }}</p>
    @if($screen !== 'analysis')
    <h2>{{ ['inventory' => 'Inventory', 'setup' => 'Selling Prices', 'reports' => 'Sales Report', 'analysis' => 'Sales Analysis', 'balancing' => 'Daily Sales Balancing', 'receipt_archive' => 'Receipt Archive'][$screen] ?? 'Record Daily Sale' }}</h2>
    <p>{{ ['inventory' => 'View the farm products currently available for sale from Poultry, Goats, Rabbits, Dogs and Garden.', 'setup' => 'Update the current market price used when recording sales.', 'reports' => 'Review individual sales and receipts for the selected period.', 'analysis' => 'Compare sales performance by farm unit, product, customer and payment method.', 'balancing' => 'Reconcile today’s sales collections and transfer physical cash to Bank or the Reservoir account.', 'receipt_archive' => 'Find previously uploaded banking and cash-handover receipts.'][$screen] ?? 'Record a customer sale or staff consumption, then send it for Finance and CEO approval.' }}</p>
    @endif
</div>
@endif

@if($screen === 'inventory')
@if(!$inventoryUnit)
<div class="summary-grid" aria-label="Farm unit inventories">
    @foreach(['poultry'=>'Poultry','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs','garden'=>'Garden'] as $unitCode=>$unitName)
        <a class="summary-card{{ $inventoryUnit === $unitCode ? ' active' : '' }}" style="color:inherit;text-decoration:none" href="{{ route('sales.index', ['screen'=>'inventory', 'unit'=>$unitCode]) }}" @if($inventoryUnit === $unitCode) aria-current="page" @endif>
            <span>Farm unit inventory</span>
            <strong>{{ $unitName }}</strong>
            <small>{{ collect($farmStock[$unitCode] ?? [])->count() }} product group(s) in stock</small>
        </a>
    @endforeach
</div>
@else
<div class="form-actions">
    <a class="btn btn--quiet" href="{{ route('sales.index', ['screen'=>'inventory']) }}">&larr; Back to farm units</a>
</div>
@endif
@elseif(!in_array($screen, ['analysis', 'sale'], true))
<nav class="unit-tabs" aria-label="Sales workspace">
    @if($screen !== 'setup')
        <a class="{{ $screen === 'sale' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'sale']) }}">Record Daily Sale</a>
        <a class="{{ $screen === 'reports' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'reports']) }}">Sales Report</a>
        <a class="{{ $screen === 'analysis' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'analysis']) }}">Sales Analysis</a>
    @else
        <a class="active" href="{{ route('sales.index', ['screen' => 'setup']) }}">Selling Prices</a>
    @endif
</nav>
@endif

@if($screen === 'sale')
<dialog id="add-customer-dialog" class="card"><form method="POST" action="{{ route('sales.customers.store') }}" class="form-grid">@csrf<h3 class="field--wide">Add customer</h3><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Phone</label><input name="phone"></div><div class="field"><label>Email</label><input type="email" name="email"></div><div class="field"><label>Address</label><input name="address"></div><div class="field"><label>Credit limit</label><input type="number" min="0" step="0.01" name="credit_limit" value="0"></div><div class="form-actions field--wide"><button class="btn">Save customer</button><button class="btn btn--quiet" type="button" onclick="document.getElementById('add-customer-dialog').close()">Cancel</button></div></form></dialog>
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
            <div class="field"><label>Date</label><input type="date" name="sale_date" value="{{ old('sale_date', now()->toDateString()) }}" required></div>
            <div class="field" id="sale-payment-field"><label>Payment method</label><select name="payment_method" id="sale-payment-method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="agent">Agent</option><option value="mobile_money">Mobile money</option><option value="prepayment">Customer prepayment credit</option><option value="in_kind">In kind / barter</option><option value="farm_consumption">Farm consumption (no payment)</option></select><span class="field-hint">Choose Farm consumption when a product is taken for farm or staff use without payment.</span></div>
            <div class="field" id="sale-customer-field"><label>Customer name</label><div style="display:flex;align-items:stretch;gap:.5rem"><input style="min-width:0;flex:1" name="walk_in_customer" value="{{ old('walk_in_customer') }}" list="sales-customer-list" placeholder="Type or choose a customer"><button class="btn btn--quiet" style="flex:0 0 auto;padding-inline:1rem;font-size:1.25rem" type="button" title="Add a customer who is not on the list" aria-label="Add new customer" onclick="document.getElementById('add-customer-dialog').showModal()">+</button></div><datalist id="sales-customer-list">@foreach($customers as $customer)<option value="{{ $customer->name }}"></option>@endforeach</datalist><span class="field-hint">Choose an existing customer, or use + to add one who is not on the list. Leave blank for a one-time customer.</span></div>
            <div class="field" id="sale-merchant-field" hidden><label>Transaction ID</label><input name="merchant_code" id="sale-merchant-code" value="{{ old('merchant_code') }}" placeholder="Enter the mobile-money Transaction ID"><span class="field-hint">Required for Mobile Money.</span></div>
            <div class="field" id="sale-receipt-field" hidden><label>Payment receipt</label><input type="file" name="payment_receipt" id="sale-payment-receipt" accept=".jpg,.jpeg,.png,.pdf"><span class="field-hint">Required for Bank or Agent payments. Maximum 5 MB.</span></div>
            <div class="field" id="sale-credit-field" hidden><label>Customer account credit</label><div class="field-readonly">Applied automatically from the selected customer’s available prepayment balance.</div><span class="field-hint">No prepayment ID is required.</span></div>
            <div class="field" id="sale-account-field"><label>Receiving account</label><select name="farm_account">@foreach($accounts as $account)<option value="{{ $account }}">{{ $account }}</option>@endforeach</select></div>
        </div>

        <div id="saleLines" class="entry-lines__list"></div>
        <div class="field-readonly" id="sale-money-total"><strong>Sale total:</strong> <span id="invoiceTotal">UGX 0</span></div>
        <template id="saleLineTemplate">
            <article class="entry-line">
                <div class="entry-line__title"><strong data-line-number>Item</strong><strong data-line-total>UGX 0</strong></div>
                <div class="entry-line__grid">
                    <div class="field field--wide"><label>Product</label><select data-product required><option value="">Choose product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ (float)$product->unit_price > 0 ? $product->unit_price : '' }}" data-loose-price="{{ $product->loose_unit_price }}" data-unit="{{ $product->unit }}" data-category="{{ $product->category }}" data-off-layer="{{ $product->unit==='bird' && Str::contains(Str::lower($product->name),'layer') ? '1' : '0' }}" data-eggs-per-tray="{{ $product->egg_grade_id && $product->eggGrade?->is_defect ? ($product->eggGrade?->eggs_per_tray ?? 30) : '' }}">{{ ucfirst($product->category) }} &middot; {{ $product->name }} ({{ $product->egg_grade_id && $product->eggGrade?->is_defect ? 'tray or individual egg' : $product->unit }})</option>@endforeach<option value="__unlisted__">+ Unlisted / type a product</option></select></div>
                    <div class="field field--wide" data-custom-product hidden><label>Unlisted product name</label><input data-description placeholder="Type the product"></div>
                    <div class="field" data-custom-category-field hidden><label>Category</label><select data-custom-category>@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></div>
                    <div class="field"><label>Unit</label><input data-unit readonly><select data-unit-choice hidden><option value="">Choose unit</option>@foreach(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'] as $unit)<option value="{{ $unit }}">{{ ucfirst($unit) }}</option>@endforeach</select><select data-egg-unit-choice hidden><option value="tray">Whole trays</option><option value="egg">Loose individual eggs</option></select></div>
                    <div class="field"><label>Quantity</label><input data-quantity type="number" step="0.01" min="0.01" required></div>
                    <div class="field" data-poultry-house-field hidden><label>Poultry house supplying the birds</label><select data-poultry-house disabled><option value="">Choose occupied house</option>@foreach($poultryHouses as $house)<option value="{{ $house->id }}">House {{ $house->displayNumber() }} &middot; {{ $house->batch?->code }} &middot; {{ number_format($house->bird_quantity) }} birds</option>@endforeach</select><small class="field-hint">The sold birds will be deducted from this house. When none remain, the house becomes free.</small></div>
                    <div class="field"><label>Unit price (UGX)</label><input data-price type="number" step="0.01" min="0" readonly required><small class="field-hint" data-price-hint>Select a product to use its selling price.</small></div>
                </div>
                <input data-category type="hidden"><div class="entry-line__actions"><button type="button" class="btn btn--quiet btn--small" data-remove>Remove item</button></div>
            </article>
        </template>
        <div class="field"><label>Note</label><textarea name="notes" placeholder="Optional"></textarea></div>
        <div class="field" id="consumed-by-field" hidden><label>Used by / purpose</label><input name="consumed_by" id="consumed-by" placeholder="e.g. Staff kitchen or poultry unit"></div>
        <div class="form-actions"><button type="button" class="btn btn--quiet" id="addSaleLine">Add item</button><button class="btn" id="sale-submit">Record &amp; Send for Approval</button></div>
    </form>
</section>
<script>
(() => {
    const paymentMethod=document.getElementById('sale-payment-method'),merchantField=document.getElementById('sale-merchant-field'),merchantCode=document.getElementById('sale-merchant-code'),receiptField=document.getElementById('sale-receipt-field'),paymentReceipt=document.getElementById('sale-payment-receipt');
    const consumedField=document.getElementById('consumed-by-field'),consumedBy=document.getElementById('consumed-by'),creditField=document.getElementById('sale-credit-field'),internalOnly=['sale-customer-field','sale-account-field'];
    const syncPaymentEvidence=()=>{const internal=paymentMethod.value==='farm_consumption',prepayment=!internal&&paymentMethod.value==='prepayment',mobile=!internal&&paymentMethod.value==='mobile_money',evidence=!internal&&['bank','agent'].includes(paymentMethod.value);merchantField.hidden=!mobile;merchantCode.required=mobile;receiptField.hidden=!evidence;paymentReceipt.required=evidence;creditField.hidden=!prepayment;consumedField.hidden=!internal;consumedBy.required=internal;internalOnly.forEach(id=>{const field=document.getElementById(id);field.hidden=internal;field.querySelectorAll('input,select,button').forEach(control=>control.disabled=internal);});document.getElementById('sale-money-total').style.display=internal?'none':'';document.getElementById('sale-submit').textContent=internal?'Record Farm Consumption & Send for Approval':'Record & Send for Approval';document.querySelectorAll('[data-price]').forEach(input=>{input.closest('.field').hidden=internal;if(internal&&!input.value)input.value=0;});document.querySelectorAll('[data-line-total]').forEach(total=>total.style.display=internal?'none':'');};paymentMethod.addEventListener('change',syncPaymentEvidence);
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
            if(paymentMethod.value==='farm_consumption'&&!price.value)price.value=0;price.closest('.field').hidden=paymentMethod.value==='farm_consumption';row.querySelector('[data-line-total]').hidden=paymentMethod.value==='farm_consumption';total();
        };
        product.onchange = sync; eggUnitChoice.onchange = sync; customCategory.onchange = () => category.value = customCategory.value; unitChoice.onchange = () => unit.value = unitChoice.value; quantity.oninput = total; price.oninput = total; row.querySelector('[data-remove]').onclick = () => { row.remove(); total(); }; body.appendChild(row); syncPaymentEvidence();
    };
    document.getElementById('addSaleLine').onclick = add; add(); syncPaymentEvidence();
})();
</script>
@elseif($screen === 'inventory')
@foreach(['poultry'=>'Poultry','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs','garden'=>'Garden harvest'] as $unitCode=>$unitName)
@continue($inventoryUnit !== $unitCode)
<section class="exec-panel table-wrap" id="inventory-{{ $unitCode }}">
    <div class="section-heading"><div><p class="card-label">{{ $unitName }}</p><h3>Available stock</h3></div><strong>{{ collect($farmStock[$unitCode] ?? [])->count() }} item(s)</strong></div>
    <table class="exec-table"><thead><tr><th>Product / group</th><th>Details</th><th>Available quantity</th></tr></thead><tbody>
    @forelse($farmStock[$unitCode] ?? [] as $item)
        <tr><td><strong>{{ $item['item'] }}</strong></td><td>{{ $item['detail'] }}</td><td><strong>{{ number_format($item['quantity'], 2) }}</strong> {{ $item['unit'] }}</td></tr>
    @empty
        <tr><td colspan="3">No available stock has been recorded for {{ Str::lower($unitName) }}.</td></tr>
    @endforelse
    </tbody></table>
</section>
@endforeach
@elseif($screen === 'setup')
<form method="GET" class="card filters selling-price-filter"><input type="hidden" name="screen" value="setup"><div class="field"><label>Farm unit</label><select name="price_unit"><option value="">All units</option>@foreach(['poultry'=>'Poultry','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs','garden'=>'Garden','other'=>'Other'] as $code=>$label)<option value="{{ $code }}" @selected($priceUnit===$code)>{{ $label }}</option>@endforeach</select></div><button class="btn">Filter prices</button>@if($priceUnit)<a class="btn btn--quiet" href="{{ route('sales.index',['screen'=>'setup']) }}">Clear</a>@endif</form>
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
@include('sales.partials.product-catalogue-entry')
<section class="exec-panel"><div class="section-heading"><div><p class="card-label">Accountability</p><h3>Recent price changes</h3></div></div><div class="price-history">@forelse($priceHistory as $change)<article><div><strong>{{ $change->description }}</strong><small>{{ $change->created_at->format('d M Y, H:i') }} &middot; {{ $change->user?->displayName() ?? $change->username ?? 'System' }}</small></div><span>UGX {{ number_format((float)data_get($change->old_values,'unit_price')) }} &rarr; <b>UGX {{ number_format((float)data_get($change->new_values,'unit_price')) }}</b></span></article>@empty<p class="muted">No price changes have been recorded yet.</p>@endforelse</div></section>
<script>document.querySelectorAll('.price-update-form').forEach(form=>{form.querySelector('button').textContent='Request Director approval';form.addEventListener('submit',event=>{const input=form.querySelector('[name="unit_price"]'),current=Number(form.dataset.currentPrice),next=Number(input.value);if(next===current)return;const message=`${form.dataset.product} is currently UGX ${current.toLocaleString()}. Send the proposed price of UGX ${next.toLocaleString()} to the Director for approval?`;if(!window.confirm(message)){event.preventDefault();return;}form.querySelector('[data-confirm-price]').value='1';});});</script>
@elseif($screen === 'receipt_archive')
<section class="sales-report">
    <div class="form-actions">
        <a class="btn btn--quiet" href="{{ route('sales.index', ['screen' => 'balancing']) }}">&larr; Back to daily balancing</a>
    </div>
    <form class="sales-report__filter" method="GET" action="{{ route('sales.index') }}">
        <input type="hidden" name="screen" value="receipt_archive">
        <div class="field"><label>From</label><input type="date" name="receipt_from" value="{{ $receiptFrom }}" required></div>
        <div class="field"><label>To</label><input type="date" name="receipt_to" value="{{ $receiptTo }}" required></div>
        <div class="field"><label>Category</label><select name="receipt_destination"><option value="">All categories</option><option value="bank" @selected($receiptDestination === 'bank')>Bank deposits</option><option value="reservoir" @selected($receiptDestination === 'reservoir')>Reservoir handovers</option></select></div>
        <div class="field"><label>Sales person</label><select name="receipt_sales_person"><option value="">All sales people</option>@foreach($receiptSalesPeople as $person)<option value="{{ $person->id }}" @selected($receiptSalesPerson === $person->id)>{{ $person->displayName() }}</option>@endforeach</select></div>
        <button class="btn">Filter receipts</button>
    </form>

    @forelse($receiptArchive->groupBy(fn ($balance) => $balance->business_date->format('F Y')) as $month => $receipts)
        <section class="exec-panel table-wrap">
            <div class="section-heading"><div><p class="card-label">Receipt category</p><h3>{{ $month }}</h3></div><strong>{{ $receipts->count() }} receipt(s)</strong></div>
            <table>
                <thead><tr><th>Business date</th><th>Category</th><th>Sales person</th><th>Amount</th><th>Closed by</th><th>Uploaded file</th></tr></thead>
                <tbody>
                    @foreach($receipts as $receipt)
                        <tr><td>{{ $receipt->business_date->format('d M Y') }}</td><td>{{ $receipt->destination === 'bank' ? 'Bank deposit' : 'Reservoir handover' }}</td><td>{{ $receipt->salesPerson?->displayName() ?? 'Unknown' }}</td><td><strong>UGX {{ number_format((float) $receipt->amount_transferred, 2) }}</strong></td><td>{{ $receipt->closer?->displayName() ?? 'System' }}<small class="table-sub">{{ $receipt->closed_at?->format('d M Y, H:i') }}</small></td><td><a class="btn btn--quiet btn--small" href="{{ route('sales.balancing.receipt', $receipt, false) }}" target="_blank" rel="noopener">View receipt</a></td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <div class="notice">No uploaded receipts were found for the selected filters.</div>
    @endforelse
</section>
@elseif($screen === 'balancing')
<section class="sales-report daily-balancing">
    <div class="form-actions"><a class="btn btn--quiet" href="{{ route('sales.index', ['screen' => 'receipt_archive']) }}">Receipt archive</a></div>
    <form class="sales-report__filter" method="GET" action="{{ route('sales.index') }}">
        <input type="hidden" name="screen" value="balancing">
        <div class="field"><label>Business date</label><input type="date" name="balance_date" value="{{ $balanceDate }}" max="{{ now()->toDateString() }}" required></div>
        <button class="btn">Open workbook</button>
        <button class="btn btn--quiet" type="button" onclick="window.print()">Print</button>
    </form>

    <div class="sales-report__kpis">
        <article><small>Balance brought forward</small><strong>UGX {{ number_format($balanceOpening, 2) }}</strong><span>{{ $balanceOpening > 0 ? 'An earlier day was not fully transferred' : 'Opening balance is clear' }}</span></article>
        <article><small>Sales value entered</small><strong>UGX {{ number_format($balanceSales->sum('total'), 2) }}</strong><span>{{ $balanceSales->count() }} sale(s)</span></article>
        <article><small>Money received today</small><strong>UGX {{ number_format($balancePayments->sum('amount'), 2) }}</strong><span>All collection methods</span></article>
        <article><small>Physical cash to transfer</small><strong>UGX {{ number_format($balanceCashDue, 2) }}</strong><span>Opening balance plus today’s cash</span></article>
        <article><small>Day status</small><strong>{{ $dailyBalance ? 'Closed' : 'Open' }}</strong><span>{{ $dailyBalance?->closed_at?->format('d M Y, H:i') ?? 'Awaiting balancing' }}</span></article>
    </div>

    <section class="exec-panel table-wrap">
        <div class="section-heading"><div><p class="card-label">Sheet 1</p><h3>Sales made on {{ \Carbon\Carbon::parse($balanceDate)->format('d M Y') }}</h3></div></div>
        <table><thead><tr><th>Time</th><th>Invoice</th><th>Customer</th><th>Products</th><th>Payment method</th><th>Approval</th><th>Total (UGX)</th><th>Received (UGX)</th><th>Outstanding (UGX)</th></tr></thead><tbody>
        @forelse($balanceSales as $sale)<tr><td>{{ $sale->created_at->format('H:i') }}</td><td><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></td><td>{{ $sale->customer_name }}</td><td>@foreach($sale->lines as $line)<div>{{ $line->description }} &middot; {{ number_format($line->quantity, 2) }} {{ $line->unit }}</div>@endforeach</td><td>{{ Str::headline($sale->payment_method ?: $sale->payment_type) }}</td><td>{{ Str::headline($sale->approval_status) }}</td><td>{{ number_format($sale->total, 2) }}</td><td>{{ number_format($sale->payments->sum('amount'), 2) }}</td><td>{{ number_format($sale->balance, 2) }}</td></tr>
        @empty<tr><td colspan="9">No sales were entered for this date.</td></tr>@endforelse
        </tbody><tfoot><tr><th colspan="6">Daily total</th><th>{{ number_format($balanceSales->sum('total'), 2) }}</th><th>{{ number_format($balancePayments->sum('amount'), 2) }}</th><th>{{ number_format($balanceSales->sum('balance'), 2) }}</th></tr></tfoot></table>
    </section>

    <section class="exec-panel table-wrap">
        <div class="section-heading"><div><p class="card-label">Sheet 2</p><h3>Collections by payment method</h3></div></div>
        <table><thead><tr><th>Payment method</th><th>Amount (UGX)</th><th>Treatment at close</th></tr></thead><tbody>
        @forelse($balanceByMethod as $method => $amount)<tr><td>{{ Str::headline($method) }}</td><td>{{ number_format($amount, 2) }}</td><td>{{ $method === 'cash' ? 'Must be banked or handed to Reservoir' : 'Already received in its payment account' }}</td></tr>
        @empty<tr><td colspan="3">No payments were received for this date.</td></tr>@endforelse
        </tbody><tfoot><tr><th>Total received</th><th>{{ number_format($balancePayments->sum('amount'), 2) }}</th><th></th></tr></tfoot></table>
    </section>

    <section class="exec-panel">
        <div class="section-heading"><div><p class="card-label">Sheet 3</p><h3>End-of-day cash handover</h3></div><strong>Expected UGX {{ number_format($balanceCashDue, 2) }}</strong></div>
        @if($dailyBalance)
            <div class="notice notice--ok">
                <strong>Day closed.</strong> UGX {{ number_format($dailyBalance->amount_transferred, 2) }} was transferred to {{ Str::headline($dailyBalance->destination) }}.
                @if($dailyBalance->receipt_path)
                    <a class="btn btn--quiet btn--small" href="{{ route('sales.balancing.receipt', $dailyBalance, false) }}" target="_blank" rel="noopener">View uploaded receipt</a>
                @endif
                The next day’s balance brought forward is UGX 0.
            </div>
        @else
            <form method="POST" action="{{ route('sales.balancing.close') }}" class="form-grid" enctype="multipart/form-data">@csrf
                <input type="hidden" name="business_date" value="{{ $balanceDate }}">
                <input type="hidden" name="sales_person_id" value="{{ request('sales_person_id', auth()->id()) }}">
                <div class="field"><label>Cash counted and transferred (UGX)</label><input type="number" name="amount_transferred" min="0" step="0.01" value="{{ number_format($balanceCashDue, 2, '.', '') }}" required></div>
                <div class="field"><label>Transferred to</label><select name="destination" required><option value="bank">Bank</option><option value="reservoir">Reservoir account</option></select></div>
                <div class="field"><label>Upload receipt</label><input type="file" name="deposit_receipt" accept="image/jpeg,image/png,image/webp,application/pdf"><small>Upload the bank deposit receipt when Bank is selected.</small></div>
                <div class="field field--wide"><label>Note</label><textarea name="notes" placeholder="Optional explanation or handover details"></textarea></div>
                <div class="field--wide notice">The system will close the day only when the transferred cash exactly equals UGX {{ number_format($balanceCashDue, 2) }}.</div>
                <button class="btn">Balance and close day</button>
            </form>
        @endif
    </section>
</section>
@elseif($screen === 'analysis')
@if(!$analysisView || ($analysisView === 'revenue' && !$revenueView))
<div class="summary-grid" aria-label="Sales analysis reports">
    @php
        $analysisCards = $analysisView === 'revenue' ? [
        'ytd'=>['Year-to-date comparison','Compare each revenue category with the same period in the previous financial year.'],
        'monthly'=>['Month-on-month comparison','Compare monthly product-category revenue and total operating revenue.'],
    ] : [
        'summary'=>'Sales Summary',
        'monthly'=>'Analysis (Month-on-Month)',
        'product_amount'=>'Product Type by Amount',
        'product_quantity'=>'Product Type by Quantity',
    ];
    @endphp
    @foreach($analysisCards as $viewCode=>$card)
        @php
            [$viewTitle, $viewDescription] = is_array($card) ? $card : [$card, 'Open report'];
        @endphp
        <a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('sales.index', $analysisView === 'revenue' ? ['screen'=>'analysis','analysis_view'=>'revenue','revenue_view'=>$viewCode] : ['screen'=>'analysis','analysis_view'=>$viewCode,'from'=>$from,'to'=>$to]) }}">
            <span>{{ $analysisView === 'revenue' ? 'Revenue analysis report' : 'Sales analysis report' }}</span>
            <strong>{{ $viewTitle }}</strong>
            <small>{{ $viewDescription }}</small>
        </a>
    @endforeach
</div>
@else
<div class="form-actions">
    <a class="btn btn--quiet" href="{{ route('sales.index',['screen'=>'analysis','from'=>$from,'to'=>$to]) }}">&larr; Back to sales analysis</a>
</div>
<section class="sales-report">
    <form class="sales-report__filter" method="GET" action="{{ route('sales.index') }}">
        <input type="hidden" name="screen" value="analysis">
        <input type="hidden" name="analysis_view" value="{{ $analysisView }}">
        @if($analysisView === 'revenue')<input type="hidden" name="revenue_view" value="{{ $revenueView }}">@endif
        @if($analysisView === 'monthly')
        <div class="field"><label>From month</label><input type="month" name="from_month" value="{{ $fromMonth }}" required></div>
        <div class="field"><label>To month</label><input type="month" name="to_month" value="{{ $toMonth }}" required></div>
        @elseif($analysisView === 'revenue')
        <div class="field"><label>Financial year starts</label><input type="number" name="financial_year" value="{{ $revenueFinancialYear }}" min="2000" max="{{ now()->addYear()->year }}" required><small>July to June</small></div>
        @else
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
        @endif
        <button class="btn">Analyse</button>
        <a class="btn btn--quiet" href="{{ route('sales.analysis.export', $analysisView === 'monthly' ? ['analysis_view'=>$analysisView,'from_month'=>$fromMonth,'to_month'=>$toMonth] : ($analysisView === 'revenue' ? ['analysis_view'=>'revenue','financial_year'=>$revenueFinancialYear] : ['analysis_view'=>$analysisView,'from'=>$from,'to'=>$to])) }}">Download Excel</a>
    </form>
    @if($analysisView === 'revenue' && $revenueView === 'ytd')
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Revenue summary</p><h3>Year-to-date comparison</h3><p>{{ $revenueYearStart->format('d M Y') }} to {{ $revenueYtdEnd->format('d M Y') }}, compared with the previous financial year.</p></div></div><table><thead><tr><th>Revenue category</th><th>{{ $revenueYearStart->format('Y') }}/{{ $revenueYearStart->copy()->addYear()->format('y') }}</th><th>Previous year</th><th>Variance</th><th>% variance</th></tr></thead><tbody>@foreach($revenueYtdAnalysis as $row)<tr><td><strong>{{ $row['category'] }}</strong></td><td>{{ $row['current'] ? number_format($row['current'],2) : '-' }}</td><td>{{ $row['previous'] ? number_format($row['previous'],2) : '-' }}</td><td>{{ $row['variance'] ? number_format($row['variance'],2) : '-' }}</td><td class="{{ $row['percent'] !== null && $row['percent'] < 0 ? 'movement-down' : 'movement-up' }}">{{ $row['percent'] === null ? '-' : number_format($row['percent'],1).'%' }}</td></tr>@endforeach</tbody><tfoot><tr><th>Total operating revenue</th><th>{{ number_format($revenueYtdAnalysis->sum('current'),2) }}</th><th>{{ number_format($revenueYtdAnalysis->sum('previous'),2) }}</th><th>{{ number_format($revenueYtdAnalysis->sum('variance'),2) }}</th><th></th></tr></tfoot></table></section>
    @elseif($analysisView === 'revenue' && $revenueView === 'monthly')
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Month-on-month comparison</p><h3>Revenue by product category</h3></div></div><table><thead><tr><th>Revenue category</th>@foreach($revenueMonths as $month)<th>{{ $month['label'] }}</th>@endforeach<th>Total</th></tr></thead><tbody>@foreach($revenueMonthlyAnalysis as $row)<tr><td><strong>{{ $row['category'] }}</strong></td>@foreach($revenueMonths as $month)<td>{{ ($row['months'][$month['key']] ?? 0) ? number_format($row['months'][$month['key']],2) : '-' }}</td>@endforeach<td><strong>{{ number_format($row['months']->sum(),2) }}</strong></td></tr>@endforeach</tbody><tfoot><tr><th>Total operating revenue</th>@foreach($revenueMonths as $month)<th>{{ number_format($revenueMonthlyAnalysis->sum(fn($row) => $row['months'][$month['key']] ?? 0),2) }}</th>@endforeach<th>{{ number_format($revenueMonthlyAnalysis->sum(fn($row) => $row['months']->sum()),2) }}</th></tr></tfoot></table></section>
    @elseif($analysisView === 'summary')
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Summary</p><h3>Customer sales ranking</h3></div></div><table><thead><tr><th>Current receivables</th><th>Amount (UGX)</th><th>%</th><th>Rank</th></tr></thead><tbody>@forelse($customerReport as $row)<tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ number_format($row['amount'],2) }}</td><td>{{ number_format($row['share'],1) }}%</td><td>{{ $row['rank'] }}</td></tr>@empty<tr><td colspan="4">No approved sales in this period.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th><th>{{ number_format($reportTotal,2) }}</th><th>100%</th><th></th></tr></tfoot></table></section>
    @elseif($analysisView === 'monthly')

    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Analysis (MoM)</p><h3>Customer sales by month</h3><p>The twelve months ending in the selected To date.</p></div></div><table><thead><tr><th>Current receivables</th>@foreach($analysisMonths as $month)<th>{{ $month['label'] }}</th>@endforeach</tr></thead><tbody>@forelse($customerMonthlyAnalysis as $row)<tr><td><strong>{{ $row['name'] }}</strong></td>@foreach($analysisMonths as $month)<td>{{ ($row['months'][$month['key']] ?? 0) > 0 ? number_format($row['months'][$month['key']],2) : '-' }}</td>@endforeach</tr>@empty<tr><td colspan="13">No approved sales in these months.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th>@foreach($analysisMonths as $month)<th>{{ number_format($customerMonthlyAnalysis->sum(fn($row)=>$row['months'][$month['key']] ?? 0),2) }}</th>@endforeach</tr></tfoot></table></section>

    @elseif($analysisView === 'product_amount')
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Analysis (Product Type) by amount</p><h3>Customer purchases by product value</h3></div></div><table><thead><tr><th>Current receivables</th>@foreach($analysisProductColumns as $product)<th>{{ $product }}</th>@endforeach</tr></thead><tbody>@forelse($customerProductAnalysis as $row)<tr><td><strong>{{ $row['name'] }}</strong></td>@foreach($analysisProductColumns as $product)<td>{{ ($row['amounts'][$product] ?? 0) > 0 ? number_format($row['amounts'][$product],2) : '-' }}</td>@endforeach</tr>@empty<tr><td>No approved sales in this period.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th>@foreach($analysisProductColumns as $product)<th>{{ number_format($customerProductAnalysis->sum(fn($row)=>$row['amounts'][$product] ?? 0),2) }}</th>@endforeach</tr></tfoot></table></section>

    @else
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Analysis (Product Type) by quantity</p><h3>Customer purchases by product quantity</h3></div></div><table><thead><tr><th>Current receivables</th>@foreach($analysisProductColumns as $product)<th>{{ $product }}</th>@endforeach</tr></thead><tbody>@forelse($customerProductAnalysis as $row)<tr><td><strong>{{ $row['name'] }}</strong></td>@foreach($analysisProductColumns as $product)<td>{{ ($row['quantities'][$product] ?? 0) > 0 ? number_format($row['quantities'][$product],2) : '0' }}</td>@endforeach</tr>@empty<tr><td>No approved sales in this period.</td></tr>@endforelse</tbody><tfoot><tr><th>Total</th>@foreach($analysisProductColumns as $product)<th>{{ number_format($customerProductAnalysis->sum(fn($row)=>$row['quantities'][$product] ?? 0),2) }}</th>@endforeach</tr></tfoot></table></section>
    @endif
</section>
@endif
@else
@php
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
        <article><small>Awaiting approval</small><strong>{{ number_format($pendingApprovalCount) }}</strong><span>Finance then CEO must approve</span></article>
    </div>
    <div class="sales-report__grid">
        <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Customer analysis</p><h3>Frequent customers</h3></div></div><div class="sales-ranking">@forelse($customerReport->take(8) as $row)<div><b>{{ $row['rank'] }}</b><span><strong>{{ $row['name'] }}</strong><small>{{ $row['sales'] }} purchase{{ $row['sales'] === 1 ? '' : 's' }} &middot; {{ number_format($row['share'],1) }}%</small></span><em>UGX {{ number_format($row['amount']) }}</em></div>@empty<p class="muted">No customer sales in this period.</p>@endforelse</div></section>
    </div>
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Entry audit</p><h3>Recorded sales</h3></div></div><table><thead><tr><th>Business date</th><th>Recorded at</th><th>Recorded by</th><th>Receipt</th><th>Customer</th><th>Products sold</th><th>Total</th></tr></thead><tbody>@forelse($reportSales->sortByDesc('created_at') as $sale)<tr><td>{{ $sale->sale_date->format('d M Y') }}</td><td>{{ $sale->created_at->format('d M Y, H:i') }}@if($sale->updated_at->gt($sale->created_at->copy()->addSecond()))<small class="data-sub">Updated {{ $sale->updated_at->format('d M Y, H:i') }}</small>@endif</td><td>{{ $sale->seller?->displayName() ?? 'System' }}</td><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer_name }}</td><td>@foreach($sale->lines as $line)<div><strong>{{ $line->description }}</strong> &middot; {{ number_format($line->quantity,2) }} {{ Str::plural($line->unit,$line->quantity) }}</div>@endforeach</td><td><strong>UGX {{ number_format($sale->total) }}</strong></td></tr>@empty<tr><td colspan="7">No sales found for these dates.</td></tr>@endforelse</tbody></table></section>
    <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Product performance</p><h3>What was sold</h3></div></div><table><thead><tr><th>Product</th><th>Quantity</th><th>Sales value</th></tr></thead><tbody>@forelse($productReport as $row)<tr><td><strong>{{ $row['name'] }}</strong></td><td>{{ number_format($row['quantity'],2) }} {{ Str::plural($row['unit'], $row['quantity']) }}</td><td>UGX {{ number_format($row['amount']) }}</td></tr>@empty<tr><td colspan="3">No sales found for these dates.</td></tr>@endforelse</tbody></table></section>
</section>
@endif
@endsection
