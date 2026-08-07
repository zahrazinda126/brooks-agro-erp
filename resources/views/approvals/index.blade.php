@extends('layouts.app')

@section('title', 'Approval Queue')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">Executive control</p><h1>Approval Queue</h1><p>Review submitted requests and leave a clear decision trail.</p></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <a class="btn {{ $status === 'pending' ? '' : 'btn--ghost' }}" href="{{ route('approvals.index') }}">Pending</a>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => 'approved']) }}">Approved</a>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => 'rejected']) }}">Rejected</a>
</div>

@forelse($approvals as $approval)
    <article class="card" style="margin-bottom:1rem">
        <div class="page-head">
            <div><p class="card-label">{{ ucfirst(str_replace('-', ' ', $approval->module_key)) }}</p><h2>{{ $approval->label() }}</h2></div>
            <strong>{{ strtoupper($approval->status) }}</strong>
        </div>
        @if($approval->amount())<p><strong>UGX {{ $approval->amount() }}</strong></p>@endif
        <p>{{ $approval->submission_note }}</p>
        @if($approval->status === 'pending' && $approval->currentStep())<p><strong>Stage {{ $approval->level }} of {{ $approval->required_levels }}:</strong> awaiting {{ ucfirst($approval->currentStep()->role_code) }}</p>@endif
        <p class="muted">Submitted by {{ $approval->submitter?->displayName() ?? 'Unknown user' }} on {{ $approval->submitted_at->format('d M Y, H:i') }}@if($approval->due_at) · Due {{ $approval->due_at->diffForHumans() }}@endif</p>

        <p><a class="btn" href="{{ route('approvals.show', $approval) }}">View full details</a></p>
        @if($approval->status !== 'pending' && $approval->decisionMaker)
            <p>Decided by {{ $approval->decisionMaker->displayName() }}{{ $approval->decision_note ? ': '.$approval->decision_note : '' }}</p>
        @endif
    </article>
@empty
    <div class="card"><p>There are no {{ $status }} requests in this queue.</p></div>
@endforelse

{{ $approvals->links() }}
@endsection
