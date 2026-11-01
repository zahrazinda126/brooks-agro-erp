@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="page-head">
    <p class="eyebrow">Library</p>
    <h2>Reports</h2>
    <p>Every report you may open, by department. Choose the period, then open a report: it prints in the farm's report format with Print / PDF.</p>
</div>

<form method="GET" class="card report-centre__filters" data-no-export>
    <div class="field">
        <label for="rc-department">Department</label>
        <select id="rc-department" name="department">
            <option value="">All departments</option>
            @foreach ($departments as $key => $label)
                <option value="{{ $key }}" @selected($department === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="rc-period">Period</label>
        <select id="rc-period" name="period">
            @foreach (['day' => 'Day', 'week' => 'Week (Monday – Sunday)', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'] as $key => $label)
                <option value="{{ $key }}" @selected($periodType === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="rc-date">Date</label>
        <input id="rc-date" type="date" name="date" value="{{ $date->toDateString() }}">
    </div>
    <div class="field field--actions"><button class="btn btn--inline">Show reports</button></div>
    <p class="report-centre__range">Reports open for <strong>{{ $from->format('d M Y') }}@if (! $from->isSameDay($to)) – {{ $to->format('d M Y') }}@endif</strong>. A period still running is counted up to today.</p>
</form>

@forelse ($reports as $group => $items)
    <section class="report-centre__group">
        <h3>{{ $departments[$group] }}</h3>
        <div class="report-centre__grid">
            @foreach ($items as $report)
                <article class="card report-centre__card">
                    <span class="report-centre__icon" aria-hidden="true"><svg><use href="#i-file"></use></svg></span>
                    <strong>{{ $report['title'] }}</strong>
                    <span class="report-centre__about">{{ $report['description'] }}</span>
                    <a class="btn btn--quiet report-centre__preview" href="{{ $report['url'] }}">Preview</a>
                </article>
            @endforeach
        </div>
    </section>
@empty
    <div class="card empty-state"><strong>No reports to show</strong><p>No report in this department is open to your account. Choose "All departments", or ask the System Administrator about access.</p></div>
@endforelse
@endsection

@push('styles')
<style>
.report-centre__filters{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:4px 14px;align-items:end;margin-bottom:20px}
.report-centre__filters .field{margin:0}
.report-centre__range{grid-column:1/-1;margin:6px 0 0;font-size:.85rem;color:var(--fh-muted)}
.report-centre__group{margin-bottom:22px}
.report-centre__group h3{margin:0 0 10px;font-size:1.05rem}
.report-centre__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.report-centre__card{display:flex;flex-direction:column;gap:6px;padding:18px 20px!important;margin:0!important;color:var(--fh-ink)}
.report-centre__icon{display:grid;place-items:center;width:40px;height:40px;margin-bottom:8px;border-radius:10px;background:var(--fh-primary-soft);color:var(--fh-primary)}
.report-centre__icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8}
.report-centre__card strong{font-family:var(--fh-heading);font-size:1.05rem}
.report-centre__about{font-size:.88rem;color:var(--fh-muted)}
.report-centre__preview{margin-top:auto!important;width:100%;justify-content:center}
.report-centre__card .report-centre__about{margin-bottom:10px}
</style>
@endpush
