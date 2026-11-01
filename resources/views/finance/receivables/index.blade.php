@extends('layouts.app')
@section('title','Customer Balances & Prepayments')
@section('content')
@if(! in_array($tab, ['board', 'balances', 'prepayments'], true))
<div class="page-head page-head--split">
    <div><p class="eyebrow">Finance</p><h2>{{ ['balances' => 'Customer balances', 'prepayments' => 'Customer prepayments', 'record' => 'Record a prepayment'][$tab] }}</h2><p>{{ ['balances' => 'Money customers still owe the farm.', 'prepayments' => 'Money received in advance and not yet used on a sale. These are liabilities until Sales records what the customer bought.', 'record' => 'Money received before a sale. This increases cash and customer credit; it is not sales revenue yet.'][$tab] }}</p></div>
</div>
@endif
@if($tab === 'board')
@include('finance.receivables._board', ['b' => $board])
@elseif($tab === 'balances')
<section class="card table-card">
    <table><thead><tr><th>Invoice date</th><th>Customer</th><th>Reference</th><th>Due date</th><th class="num">Balance</th><th>Status</th></tr></thead><tbody>
    @forelse($items as $item)<tr><td>{{ $item->invoice_date->format('d M Y') }}</td><td>{{ $item->customer_name }}</td><td>{{ $item->invoice_number ?: '—' }}</td><td>{{ $item->due_date?->format('d M Y') ?: '—' }}</td><td class="num">@money($item->amount-(float)$item->amount_paid)</td><td>{{ ucfirst($item->status) }}</td></tr>
    @empty<tr><td colspan="6" class="empty">No customer balances recorded yet.</td></tr>@endforelse
    </tbody></table>
</section>
{{ $items->links() }}
@elseif($tab === 'prepayments')
{{-- Prepayments: the list, with Record prepayment on the same page (the separate Record Payment tab was removed). --}}
@if(auth()->user()->canDo('receivables','create'))
<div class="prepay-actions"><button type="button" class="btn btn--inline" data-open-prepayment>Record prepayment</button></div>
<dialog class="prepay-dialog" id="record-prepayment" @if($errors->any() || $openRecord) open @endif>
<form method="POST" action="{{ route('finance.receivables.prepayment') }}" class="prepay-form">@csrf
    <header><div><p class="eyebrow">Customers</p><h2>Record a prepayment</h2><p>Money received before a sale. It increases cash and customer credit; it is not sales revenue yet.</p></div><button type="button" class="prepay-close" data-close-prepayment aria-label="Close">×</button></header>
    @if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif
    <div class="form-grid">
        <div class="field"><label>Customer</label><input name="customer_name" value="{{ old('customer_name') }}" list="prepayment-customer-list" placeholder="Type or choose customer" required autocomplete="off"><datalist id="prepayment-customer-list">@foreach($customers as $customer)<option value="{{ $customer->name }}"></option>@endforeach</datalist></div>
        <div class="field"><label>Date received</label><input type="date" name="received_date" value="{{ old('received_date',now()->toDateString()) }}" required></div>
        <div class="field"><label>Amount (UGX)</label><input type="number" name="amount" min="1" step="0.01" value="{{ old('amount') }}" required></div>
        <div class="field"><label>Receiving account</label><select name="farm_account" required>@foreach($farmAccounts as $account)<option value="{{ $account }}" @selected(old('farm_account')===$account)>{{ $account }}</option>@endforeach</select></div>
        <div class="field"><label>Payment method</label><select name="payment_method" required>@foreach($paymentMethods as $code=>$label)<option value="{{ $code }}" @selected(old('payment_method','cash')===$code)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label>Receipt / reference</label><input name="reference" value="{{ old('reference') }}" placeholder="Receipt, bank or mobile-money reference"></div>
    </div>
    <div class="form-actions"><button type="button" class="btn btn--quiet" data-close-prepayment>Cancel</button><button class="btn">Record prepayment</button></div>
</form>
</dialog>
@endif
<section class="card" id="prepayments">
    <div class="table-wrap"><table><thead><tr><th>Received</th><th>Customer</th><th>Reference</th><th>Received amount</th><th>Used on sales</th><th>Available</th></tr></thead><tbody>
    @forelse($credits as $credit)<tr><td>{{ $credit->received_date->format('d M Y') }}</td><td>{{ $credit->customer_name }}</td><td>{{ $credit->reference ?: '—' }}</td><td>@money($credit->amount)</td><td>@money($credit->amount_used)</td><td><strong>@money($credit->amount-(float)$credit->amount_used)</strong></td></tr>
    @empty<tr><td colspan="6" class="empty">No unused customer prepayments.</td></tr>@endforelse
    </tbody></table></div>
</section>
@push('styles')<style>
.prepay-actions{display:flex;justify-content:flex-end;margin:0 0 12px}
.prepay-dialog{width:min(760px,calc(100vw - 32px));max-height:calc(100vh - 48px);padding:0;border:0;border-radius:16px;box-shadow:0 18px 48px rgba(30,20,20,.25)}
.prepay-dialog::backdrop{background:rgba(30,22,25,.45)}
.prepay-form{display:grid;gap:12px;padding:20px 22px}
.prepay-form header{display:flex;justify-content:space-between;gap:12px}.prepay-form h2{margin:0}.prepay-form header p{margin:4px 0 0;color:var(--fh-muted,#666)}
.prepay-close{border:0;background:none;font-size:1.5rem;cursor:pointer;line-height:1;align-self:flex-start}
</style>@endpush
<script>
(function () {
    var d = document.getElementById('record-prepayment');
    if (!d) return;
    document.querySelectorAll('[data-open-prepayment]').forEach(function (b) { b.addEventListener('click', function () { d.showModal ? d.showModal() : d.setAttribute('open', ''); }); });
    document.querySelectorAll('[data-close-prepayment]').forEach(function (b) { b.addEventListener('click', function () { d.close ? d.close() : d.removeAttribute('open'); }); });
})();
</script>
@endif
@endsection
