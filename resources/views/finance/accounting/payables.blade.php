@extends('layouts.app')
@section('title','Accounts Payable')
@section('content')
<div class="page-head"><h2>Accounts Payable</h2><p>Supplier bills the farm still has to pay.</p></div><div class="summary-grid"><div class="summary-card"><span>Outstanding to suppliers</span><strong>UGX {{ number_format($outstanding) }}</strong></div></div>
<div class="card table-card"><table><thead><tr><th>Invoice date</th><th>Supplier</th><th>Reference</th><th>Due date</th><th class="num">Amount due</th><th>Status</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->invoice_date->format('d M Y') }}</td><td>{{ $item->supplier_name }}</td><td>{{ $item->invoice_number ?: '—' }}</td><td>{{ $item->due_date?->format('d M Y') ?: '—' }}</td><td class="num">UGX {{ number_format((float)$item->amount-(float)$item->amount_paid) }}</td><td>{{ ucfirst($item->status) }}</td></tr>@empty<tr><td colspan="6" class="empty">No supplier bills recorded yet.</td></tr>@endforelse</tbody></table></div>{{ $items->links() }}
@endsection
