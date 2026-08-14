@extends('layouts.app')

@section('title', 'Approval Details')
@section('content')
<div class="page-head page-head--split">
    <div><p class="eyebrow">Approval review</p><h2>{{ $approval->label() }}</h2><p>Submitted by {{ $approval->submitter?->displayName() ?? 'Unknown user' }} on {{ $approval->submitted_at->format('d M Y, H:i') }}.</p></div>
    <a class="btn btn--quiet btn--inline" href="{{ route('approvals.index', ['status'=>$approval->status]) }}">Back to approvals</a>
</div>

@include('approvals._decision-form')

<div class="summary-grid summary-grid--compact">
    <div class="summary-card"><span>Status</span><strong>{{ ucfirst($approval->status) }}</strong></div>
    @if($approval->amount())<div class="summary-card"><span>Total requested</span><strong>UGX {{ $approval->amount() }}</strong></div>@endif
    <div class="summary-card"><span>Current stage</span><strong>{{ $currentStep ? ucwords(str_replace('-',' ',$currentStep->role_code)) : 'Complete' }}</strong></div>
</div>

@if($budgetLines->isNotEmpty())
    @include('approvals._budget-funding')
@endif

@if($approval->approvable instanceof \App\Models\Purchase)
    @php $purchase=$approval->approvable; @endphp
    <div class="card table-card"><h2>Purchase details</h2><table><tbody><tr><th>Date</th><td>{{ $purchase->purchased_on->format('d M Y') }}</td></tr><tr><th>Supplier</th><td>{{ $purchase->supplier_name }}</td></tr><tr><th>Item</th><td>{{ $purchase->item }}</td></tr><tr><th>Quantity</th><td>{{ $purchase->quantity }} {{ $purchase->unit }}</td></tr><tr><th>Total</th><td><strong>UGX {{ number_format($purchase->total_cost,2) }}</strong></td></tr><tr><th>Notes</th><td>{{ $purchase->notes ?: '—' }}</td></tr></tbody></table></div>
@endif

@if($approval->approvable instanceof \App\Models\PayrollRun)
    @php $payroll=$approval->approvable->loadMissing('lines.employee'); @endphp
    <details class="card disclosure"><summary><span><strong>Payroll details</strong><small>{{ $payroll->payroll_number }} · {{ $payroll->lines->count() }} employees</small></span></summary><div class="disclosure__body table-wrap"><table class="exec-table"><thead><tr><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead><tbody>@foreach($payroll->lines as $line)<tr><td>{{ $line->employee->fullName() }}</td><td>UGX {{ number_format($line->gross_pay,2) }}</td><td>UGX {{ number_format($line->gross_pay-$line->net_pay,2) }}</td><td><strong>UGX {{ number_format($line->net_pay,2) }}</strong></td></tr>@endforeach</tbody></table></div></details>
@endif

<details class="card disclosure"><summary><span><strong>Approval history</strong><small>{{ $approval->steps->count() }} stages</small></span></summary><div class="disclosure__body timeline">@foreach($approval->steps as $step)<div class="timeline__item"><span class="status-pill">{{ ucfirst($step->status) }}</span><div><strong>{{ $step->stage }}. {{ ucwords(str_replace('-',' ',$step->role_code)) }}</strong>@if($step->decisionMaker)<p>{{ $step->decisionMaker->displayName() }} · {{ $step->decided_at->format('d M Y, H:i') }}</p>@endif @if($step->decision_note)<p>{{ $step->decision_note }}</p>@endif</div></div>@endforeach</div></details>

<details class="card disclosure"><summary><span><strong>Discussion</strong><small>{{ $approval->comments->count() }} comments</small></span></summary><div class="disclosure__body"><form method="POST" action="{{ route('approvals.comments.store',$approval) }}">@csrf<div class="field"><label for="comment">Add comment or question</label><textarea id="comment" name="comment" rows="3" required></textarea></div><button class="btn btn--inline">Post comment</button></form>@forelse($approval->comments as $comment)<article class="comment"><strong>{{ $comment->user->displayName() }}</strong><small>{{ $comment->created_at->format('d M Y, H:i') }}</small><p>{{ $comment->comment }}</p></article>@empty<p class="muted">No comments yet.</p>@endforelse</div></details>
@endsection
