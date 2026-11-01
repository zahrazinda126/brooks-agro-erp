@extends('layouts.app')
@section('title', "Today's Report")
@section('content')
<div class="daily-report-print">
<x-report-header eyebrow="Daily reporting protocol" title="PA Daily Report" description="Directives, poultry production and the Chairman's engagements fill in from the system. Add the projects / farm activities, then send the report to the Director." :period="$date->format('l, d F Y')"><x-slot:actions><form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="Report date" onchange="this.form.submit()"></form><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>

<section class="report-preview-heading">
    <div><p class="card-label">Report to the Director</p><h2>Complete report preview</h2><p>Check every section before sending. A sent report can't be changed.</p></div>
    <span class="status-pill">{{ $report->isSubmitted() ? 'Sent to Director' : ($report->exists ? 'Draft' : 'Open') }}</span>
</section>

@if($report->isSubmitted())
    @include('pa.daily-report._sheet', ['facts' => $facts, 'report' => $report, 'date' => $date])
    <div class="notice no-print">Sent {{ $report->submitted_at->format('d M, H:i') }}{{ $report->read_at ? ' · read by the Director' : '' }}. <a href="{{ route('pa.reports.show', $report) }}">Open the sent report</a></div>
@else
<form method="POST" action="{{ route('pa.daily-report.save') }}">@csrf
    <input type="hidden" name="report_date" value="{{ $date->toDateString() }}">
    @include('pa.daily-report._sheet', ['facts' => $facts, 'report' => $report, 'date' => $date, 'editable' => true])
    @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
    <div class="form-actions no-print">
        <button class="btn btn--ghost" name="action" value="draft" formnovalidate>Save draft</button>
        <button class="btn" name="action" value="submit" data-confirm="Send the report for {{ $date->format('d M Y') }} to the Director? It can't be changed after.">Send to Director</button>
    </div>
</form>
@endif
</div>
@endsection
