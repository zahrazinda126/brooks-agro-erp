@extends('layouts.app')
@section('title', $isReader ? "PA's Daily Reports" : 'Past Reports')
@section('content')
<form method="GET" action="{{ route('pa.reports.index') }}" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="r-from">From</label><input id="r-from" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label for="r-to">To</label><input id="r-to" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('pa.reports.index') }}">Reset</a></div>
    </div>
</form>

<div class="card table-wrap">
<table class="exec-table">
    <thead><tr><th>Date</th>@if($isReader)<th>From</th>@endif<th>Projects / farm activities</th><th>Matters for the Chairman</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($reports as $r)
        <tr>
            <td><a href="{{ route('pa.reports.show', $r) }}"><strong>{{ $r->report_date->format('D, d M Y') }}</strong></a></td>
            @if($isReader)<td>{{ $r->author?->displayName() }}</td>@endif
            <td>{{ \Illuminate\Support\Str::limit(collect($r->activities ?? [])->map(fn ($a) => trim(($a['project'] ? $a['project'].': ' : '').$a['activity']))->implode('; ') ?: (string) $r->did_today, 90) ?: '—' }}</td>
            <td>{{ $r->issues ? \Illuminate\Support\Str::limit($r->issues, 60) : '—' }}</td>
            <td>
                @if(! $r->isSubmitted())<x-status-pill status="draft" label="Draft" />
                @elseif($r->director_comment)<x-status-pill status="reviewed" label="Commented" />
                @elseif($r->read_at)<x-status-pill status="received" label="Read" />
                @else<x-status-pill status="submitted" label="{{ $isReader ? 'New' : 'Sent' }}" />@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="{{ $isReader ? 5 : 4 }}" class="muted">No reports yet.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $reports->links() }}
</div>
@endsection
