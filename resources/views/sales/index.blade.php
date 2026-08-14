@extends('layouts.app')
@section('title', 'Shop Sales')
@section('content')
<div class="page-head">
    <p class="eyebrow">Retail shop</p>
    <h2>{{ $screen === 'setup' ? 'Selling Prices' : 'Record Daily Sale' }}</h2>
    <p>{{ $screen === 'setup' ? 'Update the current market price used when recording sales.' : 'Enter a completed customer sale and issue its receipt.' }}</p>
</div>

<nav class="unit-tabs" aria-label="Sales workspace">
    <a class="{{ $screen === 'sale' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'sale']) }}">Record Daily Sale</a>
    <a class="{{ $screen === 'setup' ? 'active' : '' }}" href="{{ route('sales.index', ['screen' => 'setup']) }}">Selling Prices</a>
</nav>

@if($screen === 'sale')
<section class="exec-panel">
    <form method="POST" action="{{ route('sales.store') }}" id="saleForm">
        @csrf
        <input type="hidden" name="payment_type" value="cash">
        <input type="hidden" name="amount_paid" value="0">
        <input type="hidden" name="discount" value="0">
        <div class="form-grid">
            <div class="field"><label>Sale date</label><input type="date" name="sale_date" value="{{ old('sale_date', now()->toDateString()) }}" required></div>
            <div class="field"><label>Customer name</label><input name="walk_in_customer" value="{{ old('walk_in_customer') }}" list="sales-customer-list" placeholder="Type or choose a customer"><datalist id="sales-customer-list">@foreach($customers as $customer)<option value="{{ $customer->name }}"></option>@endforeach</datalist><span class="field-hint">Start typing to find an existing customer. A new name is saved automatically; leave blank for a one-time customer.</span></div>
            <div class="field"><label>Payment method</label><select name="payment_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile_money">Mobile money</option></select></div>
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
                    <div class="field field--wide"><label>Product</label><select data-product required><option value="">Choose product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->unit_price }}" data-unit="{{ $product->unit }}" data-category="{{ $product->category }}">{{ ucfirst($product->category) }} · {{ $product->name }} ({{ $product->unit }})</option>@endforeach</select></div>
                    <div class="field"><label>Unit</label><input data-unit readonly></div>
                    <div class="field"><label>Quantity</label><input data-quantity type="number" step="0.01" min="0.01" required></div>
                    <div class="field"><label>Unit price (UGX)</label><input data-price type="number" step="0.01" min="0" readonly required></div>
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
    const body = document.getElementById('saleLines'), template = document.getElementById('saleLineTemplate'); let index = 0;
    const total = () => { let sum = 0; body.querySelectorAll('.entry-line').forEach(row => { const value = (parseFloat(row.querySelector('[data-quantity]').value) || 0) * (parseFloat(row.querySelector('[data-price]').value) || 0); row.querySelector('[data-line-total]').textContent = 'UGX ' + Math.round(value).toLocaleString(); sum += value; }); document.getElementById('invoiceTotal').textContent = 'UGX ' + Math.round(sum).toLocaleString(); };
    const add = () => { const row = template.content.firstElementChild.cloneNode(true), i = index++, product = row.querySelector('[data-product]'), unit = row.querySelector('[data-unit]'), quantity = row.querySelector('[data-quantity]'), price = row.querySelector('[data-price]'), category = row.querySelector('[data-category]'); row.querySelector('[data-line-number]').textContent = `Item ${i + 1}`; product.name = `lines[${i}][product_id]`; unit.name = `lines[${i}][unit]`; quantity.name = `lines[${i}][quantity]`; price.name = `lines[${i}][unit_price]`; category.name = `lines[${i}][category]`; product.onchange = () => { const option = product.selectedOptions[0]; unit.value = option?.dataset.unit || ''; price.value = option?.dataset.price || ''; category.value = option?.dataset.category || ''; total(); }; quantity.oninput = total; row.querySelector('[data-remove]').onclick = () => { row.remove(); total(); }; body.appendChild(row); };
    document.getElementById('addSaleLine').onclick = add; add();
})();
</script>
@else
<section class="exec-panel table-wrap">
    <div class="section-head"><div><p class="eyebrow">Market pricing</p><h3>Current selling prices</h3></div><p>Products are maintained by stock control. Change only the selling price here.</p></div>
    <table class="exec-table"><thead><tr><th>Category</th><th>Product</th><th>Unit</th><th>Current price</th><th>New market price</th></tr></thead><tbody>
    @forelse($products as $product)
        <tr><td>{{ ucfirst($product->category) }}</td><td><strong>{{ $product->name }}</strong></td><td>{{ $product->unit }}</td><td>UGX {{ number_format((float) $product->unit_price, 2) }}</td><td><form method="POST" action="{{ route('sales.products.price', $product) }}" class="form-actions">@csrf @method('PUT')<input aria-label="New price" type="number" min="0" step="0.01" name="unit_price" value="{{ $product->unit_price }}" required><input aria-label="Effective date" type="date" name="effective_from" value="{{ now()->toDateString() }}" required><button class="btn btn--small">Update price</button></form></td></tr>
    @empty<tr><td colspan="5">No products have been configured by stock control.</td></tr>@endforelse
    </tbody></table>
</section>
@endif
@endsection
