@extends('layouts.app')

@section('title', 'Approval Details')
@section('content')
<div class="page-head page-head--split">
    <div><p class="eyebrow">Approval review</p><h2>{{ $approval->label() }}</h2><p>Submitted by {{ $approval->submitter?->displayName() ?? 'Unknown user' }} on {{ $approval->submitted_at->format('d M Y, H:i') }}.</p></div>
    <a class="btn btn--quiet btn--inline" href="{{ route('approvals.index', ['status'=>$approval->status]) }}">Back to approvals</a>
</div>

@include('approvals._decision-form')

<div class="summary-grid summary-grid--compact">
    <x-stat-card label="Status">{{ ucfirst($approval->status) }}</x-stat-card>
    @if($approval->amount())<x-stat-card label="Total requested">UGX {{ $approval->amount() }}</x-stat-card>@endif
    <x-stat-card label="Current stage">{{ $currentStep ? ucwords(str_replace('-',' ',$currentStep->role_code)) : 'Complete' }}</x-stat-card>
</div>

@if($budgetLines->isNotEmpty())
    @include('approvals._budget-funding')
@endif

@if($approval->approvable instanceof \App\Models\Purchase)
    @php $purchase=$approval->approvable; @endphp
    <div class="card table-card"><h2>Purchase details</h2><table><tbody><tr><th>Date</th><td>{{ $purchase->purchased_on->format('d M Y') }}</td></tr><tr><th>Supplier</th><td>{{ $purchase->supplier_name }}</td></tr><tr><th>Item</th><td>{{ $purchase->item }}</td></tr><tr><th>Quantity</th><td>{{ $purchase->quantity }} {{ $purchase->unit }}</td></tr><tr><th>Total</th><td><strong>@money($purchase->total_cost, 2)</strong></td></tr><tr><th>Notes</th><td>{{ $purchase->notes ?: '—' }}</td></tr></tbody></table></div>
@endif

@if($approval->approvable instanceof \App\Models\PayrollRun)
    @php $payroll=$approval->approvable->loadMissing('lines.employee'); @endphp
    <details class="card disclosure"><summary><span><strong>Payroll details</strong><small>{{ $payroll->payroll_number }} · {{ $payroll->lines->count() }} employees</small></span></summary><div class="disclosure__body table-wrap"><table class="exec-table"><thead><tr><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead><tbody>@foreach($payroll->lines as $line)<tr><td>{{ $line->employee->fullName() }}</td><td>@money($line->gross_pay, 2)</td><td>@money($line->gross_pay-$line->net_pay, 2)</td><td><strong>@money($line->net_pay, 2)</strong></td></tr>@endforeach</tbody></table></div></details>
@endif

@if($approval->approvable instanceof \App\Models\Sale)
    @php $sale=$approval->approvable->loadMissing(['lines','payments','seller']); @endphp
    <div class="card table-card"><h2>{{ $sale->is_staff_consumption ? 'Farm products used internally' : 'Sale details' }}</h2><table><tbody><tr><th>Reference</th><td>{{ $sale->invoice_number }}</td></tr><tr><th>Date</th><td>{{ $sale->sale_date->format('d M Y') }}</td></tr><tr><th>{{ $sale->is_staff_consumption ? 'Used by / purpose' : 'Customer' }}</th><td>{{ $sale->is_staff_consumption ? $sale->consumed_by : $sale->customer_name }}</td></tr><tr><th>Recorded by</th><td>{{ $sale->seller?->displayName() }}</td></tr><tr><th>Payment</th><td>{{ $sale->is_staff_consumption ? 'None — internal farm use' : Str::headline($sale->payment_method) }}</td></tr><tr><th>{{ $sale->is_staff_consumption ? 'Reference value' : 'Total' }}</th><td><strong>@money($sale->total, 2)</strong></td></tr></tbody></table><h3>Items</h3><table><thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead><tbody>@foreach($sale->lines as $line)<tr><td>{{ $line->description }}</td><td>{{ number_format((float)$line->quantity,2) }} {{ $line->unit }}</td><td>@money($line->unit_price)</td><td>@money($line->line_total)</td></tr>@endforeach</tbody></table></div>
@endif

<details class="card disclosure"><summary><span><strong>Approval history</strong><small>{{ $approval->steps->count() }} stages</small></span></summary><div class="disclosure__body timeline">@foreach($approval->steps as $step)<div class="timeline__item"><x-status-pill :status="$step->status" /><div><strong>{{ $step->stage }}. {{ ucwords(str_replace('-',' ',$step->role_code)) }}</strong>@if($step->decisionMaker)<p>{{ $step->decisionMaker->displayName() }} · {{ $step->decided_at->format('d M Y, H:i') }}</p>@endif @if($step->decision_note)<p>{{ $step->decision_note }}</p>@endif</div></div>@endforeach</div></details>

<details class="card disclosure"><summary><span><strong>Discussion</strong><small>{{ $approval->comments->count() }} comments</small></span></summary><div class="disclosure__body"><form method="POST" action="{{ route('approvals.comments.store',$approval) }}">@csrf<div class="field"><label for="comment">Add comment or question</label><textarea id="comment" name="comment" rows="3" required></textarea></div><button class="btn btn--inline">Post comment</button></form>@forelse($approval->comments as $comment)<article class="comment"><strong>{{ $comment->user->displayName() }}</strong><small>{{ $comment->created_at->format('d M Y, H:i') }}</small><p>{{ $comment->comment }}</p></article>@empty<p class="muted">No comments yet.</p>@endforelse</div></details>
@endsection
