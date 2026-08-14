@if($canActAtThisStage)
    @php
        $nextStep = $approval->steps->firstWhere('stage', $approval->level + 1);
        $nextRole = $nextStep ? match($nextStep->role_code) {
            'finance-manager' => 'Finance Manager', 'ceo' => 'CEO', 'director' => 'Director',
            default => ucfirst(str_replace('-', ' ', $nextStep->role_code)),
        } : null;
    @endphp
    <section class="decision-bar" aria-labelledby="decision-title">
        <div>
            <p class="eyebrow">Action required</p>
            <h2 id="decision-title">{{ $nextRole ? 'Approve and send to '.$nextRole : 'Give final approval' }}</h2>
            @if($approval->approvable instanceof \App\Models\Budget)
                <p>Approval funds every item by default. Open “Review budget exceptions” only when an item should be partial, unfunded, or cancelled.</p>
            @endif
        </div>
        <form method="POST" action="{{ route('approvals.decide', $approval) }}" class="decision-form">
            @csrf
            <label for="decision_note">Decision note <span class="muted">(optional for approval)</span></label>
            <textarea id="decision_note" name="decision_note" rows="2" placeholder="Add a condition or explain a rejection">{{ old('decision_note') }}</textarea>
            @error('decision_note')<p class="error">{{ $message }}</p>@enderror
            @error('decision')<p class="error">{{ $message }}</p>@enderror
            <div class="form-actions form-actions--flush">
                <button class="btn btn--inline" name="decision" value="approved">{{ $nextRole ? 'Approve & send' : 'Final approval' }}</button>
                <button class="btn btn--quiet btn--inline" name="decision" value="rejected">Reject entire request</button>
            </div>
        </form>
    </section>
@elseif($approval->decisionMaker)
    <div class="notice"><strong>{{ ucfirst($approval->status) }}</strong> by {{ $approval->decisionMaker->displayName() }} on {{ $approval->decided_at->format('d M Y, H:i') }}.@if($approval->decision_note) {{ $approval->decision_note }}@endif</div>
@endif
