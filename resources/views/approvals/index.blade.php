@extends('layouts.app')

@section('title', 'Approval Queue')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">Executive control</p><h1>Approval Queue</h1><p>Review submitted requests and leave a clear decision trail.</p></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <a class="btn {{ $status === 'pending' ? '' : 'btn--ghost' }}" href="{{ route('approvals.index') }}">Pending</a>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => 'approved']) }}">Approved</a>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => 'sent']) }}">Sent to Director</a>
    <a class="btn btn--ghost" href="{{ route('approvals.index', ['status' => 'rejected']) }}">Rejected</a>
</div>

@forelse($approvals as $approval)
    @php
        $viewerIsDirector = auth()->user()->roles->pluck('code')->push(auth()->user()->role?->code)->filter()->contains('director');
        $directorStep = $viewerIsDirector ? $approval->steps->firstWhere('role_code', 'director') : null;
        $levelsBeforeDirector = $directorStep
            ? $approval->steps->where('stage', '<', $directorStep->stage)->whereNotIn('status', ['approved'])->count()
            : 0;
    @endphp
    <article class="card" style="margin-bottom:1rem">
        <div class="page-head">
            <div><p class="card-label">{{ ucfirst(str_replace('-', ' ', $approval->module_key)) }}</p><h2>{{ $approval->label() }}</h2></div>
            <strong>{{ $approval->status === 'pending' ? 'SUBMITTED' : strtoupper($approval->status) }}</strong>
        </div>
        @if($approval->amount())<p><strong>UGX {{ $approval->amount() }}</strong></p>@endif
        <p>{{ $approval->submission_note }}</p>
        @if($approval->status === 'pending' && $approval->currentStep())<p><strong>Stage {{ $approval->level }} of {{ $approval->required_levels }}:</strong> awaiting {{ ucfirst($approval->currentStep()->role_code) }}</p>@endif
        @if($directorStep && $approval->status === 'pending')
            <p class="muted">
                @if($directorStep->status === 'pending')
                    <strong>Ready for Director approval.</strong> This is the final approval level.
                @else
                    <strong>{{ $levelsBeforeDirector }} {{ Str::plural('approval level', $levelsBeforeDirector) }} remaining before the Director can decide.</strong>
                    The submitted request is visible now for advance review.
                @endif
            </p>
        @endif
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
