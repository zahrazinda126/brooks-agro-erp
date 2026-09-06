@php
    $decisionRows = $rows->get($departmentCode, collect());
    $isApproved = $decisionRows->isNotEmpty() && $decisionRows->every(fn ($report) => $report->status === 'ceo_reviewed');
    $hasReturned = $decisionRows->contains(fn ($report) => $report->status === 'changes_requested');
    $existingNote = $decisionRows->pluck('review_note')->filter()->first();
@endphp
@if($isApproved)
    <div class="notice notice--ok"><strong>Approved by the CEO</strong>@if($existingNote)<p>{{ $existingNote }}</p>@endif</div>
@else
    <form method="POST" action="{{ route('daily-reports.department.review') }}" class="card no-print">
        @csrf
        <input type="hidden" name="report_date" value="{{ $date }}">
        <input type="hidden" name="department_code" value="{{ $departmentCode }}">
        @if($hasReturned)<div class="notice"><strong>This report was returned for correction.</strong><p>Review the corrected template before making a new decision.</p></div>@endif
        <div class="field">
            <label>CEO review comment <small>(optional when approving; required when returning)</small></label>
            <textarea name="review_note" placeholder="Add a comment only when necessary">{{ old('review_note') }}</textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn--quiet" name="decision" value="changes">Return for correction</button>
            <button class="btn" name="decision" value="approve">Approve complete report</button>
        </div>
    </form>
@endif
