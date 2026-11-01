@extends('layouts.app')
@section('title', "CEO's Daily Report")
@section('content')
<div class="daily-report-print">
<x-report-header eyebrow="Daily reporting protocol" title="CEO's Daily Report" description="Prepared by {{ $report->author?->displayName() }} and sent {{ $report->submitted_at->format('d M Y, H:i') }}." :period="$report->report_date->format('l, d F Y')"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>

<section class="report-preview-heading">
    <div><p class="card-label">Report to the Director</p><h2>Complete report</h2></div>
    <span class="status-pill">{{ $report->director_comment ? 'Commented' : ($report->read_at ? 'Read by Director' : 'Sent to Director') }}</span>
</section>

@include('ceo.daily-report._sheet', ['facts' => $facts, 'report' => $report, 'date' => $report->report_date])

@if($canComment)
    <form method="POST" action="{{ route('ceo.reports.comment', $report) }}" class="card no-print">@csrf
        <div class="field"><label for="r-comment">{{ $report->director_comment ? 'Change your comment' : 'Comment for the CEO' }}</label><textarea id="r-comment" name="director_comment" rows="3" maxlength="3000" required>{{ old('director_comment', $report->director_comment) }}</textarea></div>
        @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
        <div class="form-actions"><button class="btn">Send comment</button></div>
    </form>
@endif
</div>
@endsection
