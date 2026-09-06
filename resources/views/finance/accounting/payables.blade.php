@extends('layouts.app')
@section('title','Accounts Payable')
@section('content')
<div class="page-head"><h2>Supplier Balances</h2><p>Record supplier bills and monitor what the farm still has to pay.</p></div>
<div class="summary-grid"><div class="summary-card"><span>Outstanding to suppliers</span><strong>UGX {{ number_format($outstanding) }}</strong></div></div>

@if($user->canDo('payables','create'))
<details class="card disclosure" @if($errors->any()) open @endif>
    <summary><span><strong>Record supplier bill</strong><small>Add an unpaid invoice or other amount owed to a supplier.</small></span></summary>
    <div class="disclosure__body">
        <form method="POST" action="{{ route('finance.payables.store') }}" class="form-grid">
            @csrf
            <div class="field"><label>Supplier</label><input name="supplier_name" value="{{ old('supplier_name') }}" list="supplier-bill-list" placeholder="Choose or type supplier name" required><datalist id="supplier-bill-list">@foreach($suppliers as $supplier)<option value="{{ $supplier }}"></option>@endforeach</datalist></div>
            <div class="field"><label>Invoice / reference number</label><input name="invoice_number" value="{{ old('invoice_number') }}" placeholder="Supplier invoice number"></div>
            <div class="field"><label>Invoice date</label><input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required></div>
            <div class="field"><label>Due date</label><input type="date" name="due_date" value="{{ old('due_date') }}"></div>
            <div class="field"><label>Total amount (UGX)</label><input type="number" min="1" step="0.01" name="amount" value="{{ old('amount') }}" required></div>
            <div class="field field--wide"><label>What was supplied?</label><textarea name="description" required placeholder="Describe the goods or service on this bill">{{ old('description') }}</textarea></div>
            <div class="field field--actions"><button class="btn">Save supplier bill</button></div>
        </form>
    </div>
</details>
@endif
<form method="GET" class="card filters"><div class="field"><label>Invoice from</label><input type="date" name="from" value="{{ request('from') }}"></div><div class="field"><label>Invoice to</label><input type="date" name="to" value="{{ request('to') }}"></div><div class="field field--actions"><button class="btn btn--inline">Apply</button><a class="btn btn--quiet" href="{{ route('finance.payables.index') }}">Clear</a></div></form>

<div class="card table-card"><table><thead><tr><th>Invoice date</th><th>Supplier</th><th>Description</th><th>Reference</th><th>Due date</th><th class="num">Original bill</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>@forelse($items as $item)@php($balance=(float)$item->amount-(float)$item->amount_paid)<tr><td>{{ $item->invoice_date->format('d M Y') }}</td><td><strong>{{ $item->supplier_name }}</strong></td><td>{{ $item->description }}</td><td>{{ $item->invoice_number ?: '—' }}</td><td>{{ $item->due_date?->format('d M Y') ?: '—' }}</td><td class="num">UGX {{ number_format((float)$item->amount) }}</td><td class="num">UGX {{ number_format((float)$item->amount_paid) }}</td><td class="num"><strong>UGX {{ number_format($balance) }}</strong></td><td><span class="status-pill">{{ $balance<=0?'Paid':((float)$item->amount_paid>0?'Partly paid':'Unpaid') }}</span></td><td>@if($balance>0&&$user->canDo('transactions','create'))<a class="btn btn--small" href="{{ route('finance.transactions.create',['payable_id'=>$item->id]) }}">Pay / Part-pay</a>@endif</td></tr>@empty<tr><td colspan="10" class="empty">No supplier bills recorded yet.</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
@endsection
