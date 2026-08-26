@extends('layouts.app')
@section('title','Customer Balances & Prepayments')
@section('content')
<div class="page-head page-head--split">
    <div><p class="eyebrow">Finance</p><h2>Customer balances & prepayments</h2><p>Track money customers owe and money received in advance for future sales.</p></div>
    @if(auth()->user()->canDo('receivables','create'))<a class="btn btn--inline" href="#record-prepayment">Record prepayment</a>@endif
</div>
<div class="summary-grid">
    <div class="summary-card"><span>Outstanding from customers</span><strong>UGX {{ number_format($outstanding) }}</strong><small>Unpaid customer balances</small></div>
    <div class="summary-card"><span>Available customer prepayments</span><strong>UGX {{ number_format($credits->sum(fn($credit)=>(float)$credit->amount-(float)$credit->amount_used)) }}</strong><small>Credit not yet used on a sale</small></div>
</div>
<section class="card" id="prepayments">
    <div class="section-heading"><div><p class="card-label">Customer credit</p><h3>Available prepayments</h3><p>These amounts are liabilities until Sales records what the customer bought.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Received</th><th>Customer</th><th>Reference</th><th>Received amount</th><th>Used on sales</th><th>Available</th></tr></thead><tbody>
    @forelse($credits as $credit)<tr><td>{{ $credit->received_date->format('d M Y') }}</td><td>{{ $credit->customer_name }}</td><td>{{ $credit->reference ?: '—' }}</td><td>UGX {{ number_format((float)$credit->amount) }}</td><td>UGX {{ number_format((float)$credit->amount_used) }}</td><td><strong>UGX {{ number_format((float)$credit->amount-(float)$credit->amount_used) }}</strong></td></tr>
    @empty<tr><td colspan="6" class="empty">No unused customer prepayments.</td></tr>@endforelse
    </tbody></table></div>
</section>
@if(auth()->user()->canDo('receivables','create'))
<form method="POST" action="{{ route('finance.receivables.prepayment') }}" class="card" id="record-prepayment">@csrf
    <div class="section-heading"><div><p class="card-label">Money received in advance</p><h3>Record customer prepayment</h3><p>This increases cash and customer credit. It does not create sales revenue yet.</p></div></div>
    <div class="form-grid">
        <div class="field"><label>Customer</label><input name="customer_name" value="{{ old('customer_name') }}" list="prepayment-customer-list" placeholder="Type or choose customer" required autocomplete="off"><datalist id="prepayment-customer-list">@foreach($customers as $customer)<option value="{{ $customer->name }}"></option>@endforeach</datalist></div>
        <div class="field"><label>Date received</label><input type="date" name="received_date" value="{{ old('received_date',now()->toDateString()) }}" required></div>
        <div class="field"><label>Amount (UGX)</label><input type="number" name="amount" min="1" step="0.01" value="{{ old('amount') }}" required></div>
        <div class="field"><label>Receiving account</label><select name="farm_account" required>@foreach($farmAccounts as $account)<option value="{{ $account }}" @selected(old('farm_account')===$account)>{{ $account }}</option>@endforeach</select></div>
        <div class="field"><label>Payment method</label><select name="payment_method" required>@foreach(['cash'=>'Cash','bank'=>'Bank','mobile_money'=>'Mobile money','in_kind'=>'In kind / barter'] as $code=>$label)<option value="{{ $code }}" @selected(old('payment_method')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label>Receipt / reference</label><input name="reference" value="{{ old('reference') }}" placeholder="Receipt, bank or mobile-money reference"></div>
    </div>
    <div class="form-actions"><button class="btn">Record prepayment</button></div>
</form>
@endif
<section class="card table-card">
    <div class="section-heading"><div><p class="card-label">Amounts due</p><h3>Customer balances</h3></div></div>
    <table><thead><tr><th>Invoice date</th><th>Customer</th><th>Reference</th><th>Due date</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
    @forelse($items as $item)<tr><td>{{ $item->invoice_date->format('d M Y') }}</td><td>{{ $item->customer_name }}</td><td>{{ $item->invoice_number ?: '—' }}</td><td>{{ $item->due_date?->format('d M Y') ?: '—' }}</td><td class="num">UGX {{ number_format((float)$item->amount-(float)$item->amount_paid) }}</td><td>{{ ucfirst($item->status) }}</td></tr>
    @empty<tr><td colspan="6" class="empty">No customer balances recorded yet.</td></tr>@endforelse
    </tbody></table>
</section>
{{ $items->links() }}
@endsection
