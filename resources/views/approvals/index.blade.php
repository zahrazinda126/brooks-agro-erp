@extends('layouts.app')

@section('title', 'Approval Queue')
@section('content')
@if($ownApprovalRoles->isNotEmpty())
<details class="card disclosure" style="margin-bottom:1rem"><summary><span><strong>Approval cover while I am absent</strong><small>Temporarily authorize another active user to approve on your behalf</small></span></summary><div class="disclosure__body">
    <form method="POST" action="{{ route('approvals.delegations.store') }}" class="form-grid">@csrf
        <div class="field"><label>Approval role</label><select name="role_code" required>@foreach($ownApprovalRoles as $role)<option value="{{ $role }}">{{ Str::headline($role) }}</option>@endforeach</select></div>
        <div class="field"><label>Person covering</label><select name="delegate_id" required><option value="">Choose person</option>@foreach($delegateUsers as $person)<option value="{{ $person->id }}">{{ $person->displayName() }}</option>@endforeach</select></div>
        <div class="field"><label>From</label><input type="date" name="starts_on" value="{{ now()->toDateString() }}" required></div>
        <div class="field"><label>Until</label><input type="date" name="ends_on" value="{{ now()->addWeek()->toDateString() }}" required></div>
        <div class="field field--wide"><label>Reason</label><textarea name="reason" placeholder="Annual leave, travel, illness, etc."></textarea></div>
        <button class="btn">Assign approval cover</button>
    </form>
    @foreach($delegations->where('is_active',true) as $delegation)<div class="data-row"><span><strong>{{ $delegation->delegate?->displayName() }}</strong><small>{{ Str::headline($delegation->role_code) }} · {{ $delegation->starts_on->format('d M') }}–{{ $delegation->ends_on->format('d M Y') }}</small></span><form method="POST" action="{{ route('approvals.delegations.revoke',$delegation) }}">@csrf @method('DELETE')<button class="btn btn--quiet btn--small">Revoke</button></form></div>@endforeach
</div></details>
@endif

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
