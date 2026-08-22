@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="page-head">
        <div>
            <h2>{{ $reports[$selectedReport] }}</h2>
            <p>{{ $period }} · HR-only operational report</p>
        </div>
    </div>

    <form method="GET" action="{{ route('hr.reports.index') }}" class="card filters">
        <div class="field">
            <label for="report">Report</label>
            <select id="report" name="report">
                @foreach ($reports as $key => $label)
                    <option value="{{ $key }}" @selected($selectedReport === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label for="from">From</label><input id="from" name="from" type="date" value="{{ request('from', now()->startOfMonth()->format('Y-m-d')) }}"></div>
        <div class="field"><label for="to">To</label><input id="to" name="to" type="date" value="{{ request('to', now()->format('Y-m-d')) }}"></div>
        <div class="field field--actions"><button class="btn btn--inline" type="submit">Run report</button></div>
    </form>

    <div class="form-actions">
        <a class="btn btn--inline" href="{{ route('hr.reports.export', request()->only(['report', 'from', 'to'])) }}">Download branded Excel</a>
    </div>

    <div class="data-list">
        <div class="data-head" style="grid-template-columns: repeat({{ count($headers) }}, minmax(110px, 1fr));">
            @foreach ($headers as $header)<span>{{ $header }}</span>@endforeach
        </div>
        @forelse ($rows as $row)
            <div class="data-row" style="grid-template-columns: repeat({{ count($headers) }}, minmax(110px, 1fr));">
                @foreach ($row as $index => $value)
                    <span class="data-cell" data-label="{{ $headers[$index] }}">{{ $value ?? '—' }}</span>
                @endforeach
            </div>
        @empty
            <div class="card empty"><p class="muted" style="margin:0;">No records match this report period.</p></div>
        @endforelse
    </div>
@endsection
