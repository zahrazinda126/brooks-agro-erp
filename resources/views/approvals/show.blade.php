@extends('layouts.app')

@section('title', 'Approval Details')
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">Approval review</p>
        <h1>{{ $approval->label() }}</h1>
        <p>Review the complete request before recording your decision.</p>
    </div>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => $approval->status]) }}">Back to queue</a>
</div>

<div class="summary-grid">
    <div class="summary-card"><span>Status</span><strong>{{ ucfirst($approval->status) }}</strong></div>
    @if($approval->amount())<div class="summary-card"><span>Total requested</span><strong>UGX {{ $approval->amount() }}</strong></div>@endif
    <div class="summary-card"><span>Submitted by</span><strong>{{ $approval->submitter?->displayName() ?? 'Unknown user' }}</strong></div>
    <div class="summary-card"><span>Submitted</span><strong>{{ $approval->submitted_at->format('d M Y, H:i') }}</strong></div>
</div>

<div class="card">
    <h2>Approval stages</h2>
    @foreach($approval->steps as $step)
        <p><strong>Stage {{ $step->stage }} — {{ ucfirst($step->role_code) }}:</strong> {{ ucfirst($step->status) }}
        @if($step->decisionMaker) by {{ $step->decisionMaker->displayName() }} on {{ $step->decided_at->format('d M Y, H:i') }}@endif
        @if($step->decision_note)<br>{{ $step->decision_note }}@endif</p>
    @endforeach
</div>

@if($approval->submission_note)
    <div class="card"><h2>Submission note</h2><p>{{ $approval->submission_note }}</p></div>
@endif
@if($approval->approvable instanceof \App\Models\Purchase)
    @php($purchase=$approval->approvable)
    <div class="card table-card"><table><tbody><tr><th>Purchase date</th><td>{{ $purchase->purchased_on->format('d M Y') }}</td></tr><tr><th>Supplier</th><td>{{ $purchase->supplier_name }}</td></tr><tr><th>Invoice/reference</th><td>{{ $purchase->invoice_number ?: '—' }}</td></tr><tr><th>Category</th><td>{{ $purchase->category }}</td></tr><tr><th>Item</th><td>{{ $purchase->item }}</td></tr><tr><th>Quantity</th><td>{{ $purchase->quantity }} {{ $purchase->unit }}</td></tr><tr><th>Unit cost</th><td>UGX {{ number_format($purchase->unit_cost,2) }}</td></tr><tr><th>Total cost</th><td><strong>UGX {{ number_format($purchase->total_cost,2) }}</strong></td></tr><tr><th>Payment status</th><td>{{ ucfirst(str_replace('_',' ',$purchase->payment_status)) }}</td></tr><tr><th>Notes</th><td>{{ $purchase->notes ?: '—' }}</td></tr></tbody></table></div>
@endif

@if($budgetLines->isNotEmpty())
    <div class="card table-card">
        <table>
            <thead><tr><th>Section</th><th>Item</th><th>Unit</th><th>Quantity</th><th>Unit price</th><th>Total</th><th>Notes</th></tr></thead>
            <tbody>
            @foreach($budgetLines as $line)
                <tr>
                    <td>{{ $line->category }}</td><td>{{ $line->item ?: '—' }}</td><td>{{ $line->unit ?: '—' }}</td>
                    <td>{{ $line->quantity !== null ? number_format((float)$line->quantity, 2) : '—' }}</td>
                    <td>{{ $line->unit_price !== null ? 'UGX '.number_format((float)$line->unit_price, 2) : '—' }}</td>
                    <td><strong>UGX {{ number_format((float)$line->planned_amount, 2) }}</strong></td>
                    <td>{{ $line->notes ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="5">Combined weekly budget</th><th>UGX {{ number_format((float)$budgetLines->sum('planned_amount'), 2) }}</th><th></th></tr></tfoot>
        </table>
    </div>
@endif

@php
    $myRoleCodes = auth()->user()->roles->pluck('code')->push(auth()->user()->role?->code)->filter();
    $canActAtThisStage = $approval->currentStep() && $myRoleCodes->contains($approval->currentStep()->role_code);
@endphp
@if($approval->status === 'pending' && auth()->user()->canDo('approvals', 'approve') && $canActAtThisStage)
    <form class="card" method="POST" action="{{ route('approvals.decide', $approval) }}">
        @csrf
        <h2>Record decision</h2>
        <div class="field"><label for="decision_note">Decision note</label><textarea id="decision_note" name="decision_note" rows="3" placeholder="Explain any conditions or reason for rejection.">{{ old('decision_note') }}</textarea></div>
        @error('decision_note')<p class="error">{{ $message }}</p>@enderror
        <div class="form-actions">
            <button class="btn" name="decision" value="approved">{{ $approval->level < $approval->required_levels ? 'Approve and send to Director' : 'Give final approval' }}</button>
            <button class="btn btn--ghost" name="decision" value="rejected">Reject budget</button>
        </div>
    </form>
@elseif($approval->decisionMaker)
    <div class="card"><h2>Decision</h2><p><strong>{{ ucfirst($approval->status) }}</strong> by {{ $approval->decisionMaker->displayName() }} on {{ $approval->decided_at->format('d M Y, H:i') }}.</p>@if($approval->decision_note)<p>{{ $approval->decision_note }}</p>@endif</div>
@endif
@endsection
