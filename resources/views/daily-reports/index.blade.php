@extends('layouts.app')
@section('title','Daily Department Reports')
@section('content')
<div class="daily-report-print">
<x-report-header eyebrow="Daily reporting protocol" title="Daily Department Reports" description="Unit reports are reviewed by the CEO, then combined automatically into one report for each department." :period="\Carbon\Carbon::parse($date)->format('l, d F Y')"><x-slot:actions><form method="GET"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
@if($dailySummary && $summaryMode)
    @if($summaryMode === 'finance')
        @php
            $financeRows = $rows->get('finance', collect());
            $financeApproved = $financeRows->isNotEmpty()
                && $financeRows->every(function ($report) {
                    return $report->status === 'ceo_reviewed';
                });
        @endphp
        <section class="report-preview-heading">
            <div>
                <p class="card-label">Finance departmental report</p>
                <h2>Complete report preview</h2>
                <p>Check every section below before closing and sending the Finance report to the CEO.</p>
            </div>
            <span class="status-pill">{{ $financeApproved ? 'CEO approved' : ($closures->has('finance') ? 'Awaiting CEO review' : 'Waiting for Finance confirmation') }}</span>
        </section>
        @include('daily-reports.partials.finance-legacy', ['snapshot'=>$dailySummary, 'date'=>$date])
        @if($isCeo && $closures->has('finance'))
            @include('daily-reports.partials.ceo-department-decision', ['departmentCode'=>'finance'])
        @endif
        @if($isFinanceManager && in_array('finance',$closable??[],true) && !$closures->has('finance'))
            <form method="POST" action="{{ route('daily-reports.close-day') }}" class="notice">@csrf
                <input type="hidden" name="report_date" value="{{ $date }}">
                <input type="hidden" name="department_code" value="finance">
                <strong>Confirm the complete Finance report</strong>
                <p>Review the complete report above. One confirmation closes it and sends it to the CEO.</p>
                <button class="btn">Confirm whole report &amp; send to CEO</button>
            </form>
        @endif
    @elseif($summaryMode === 'production')
        <section class="report-preview-heading">
            <div><p class="card-label">Production departmental report</p><h2>Production report template</h2><p>Automatically filled from poultry, livestock, feed, garden, mortality and activity records for this date.</p></div>
            <span class="status-pill">{{ $closures->has('production') ? 'Sent to CEO' : 'Open' }}</span>
        </section>
        @include('daily-reports.partials.production-legacy', ['snapshot'=>$dailySummary, 'date'=>$date])
    @elseif($summaryMode === 'hr')
        <section class="report-preview-heading">
            <div><p class="card-label">HR &amp; Administration departmental report</p><h2>Complete HR daily report template</h2><p>Attendance, security, welfare, fleet, construction, ad hoc work and management observations are combined below.</p></div>
            <span class="status-pill">{{ $closures->has('hr') ? 'Sent to CEO' : 'Open' }}</span>
        </section>
        @include('daily-reports.partials.hr-legacy', ['snapshot'=>$dailySummary, 'date'=>$date, 'departmentRows'=>$rows->get('hr', collect()), 'submitted'=>$rows->get('hr', collect())->whereIn('status',['manager_shared','changes_requested','ceo_reviewed']), 'editableObservations'=>!$closures->has('hr')])
        @php
            $hrAdHocRow = $rows->get('hr', collect())->get('ad_hoc');
            $hrObservations = collect(data_get($hrAdHocRow?->details, 'management_observations', []));
        @endphp
        @if(false && !$closures->has('hr'))
        <form method="POST" action="{{ route('daily-reports.hr-observations') }}" class="card">@csrf
            <input type="hidden" name="report_date" value="{{ $date }}">
            <div class="section-heading"><div><p class="card-label">HR report observations</p><h2>Complete the narrative sections</h2><p>These entries appear directly in sections 7–10 of the consolidated HR report above.</p></div></div>
            <div class="form-grid">
                <div class="field"><label>Challenges</label><textarea name="challenges">{{ old('challenges',$hrObservations->get('challenges')) }}</textarea></div>
                <div class="field"><label>Emerging issues</label><textarea name="emerging_issues">{{ old('emerging_issues',$hrObservations->get('emerging_issues')) }}</textarea></div>
                <div class="field"><label>Recommendations / proposed actions</label><textarea name="recommendations">{{ old('recommendations',$hrObservations->get('recommendations')) }}</textarea></div>
                <div class="field"><label>Achievements</label><textarea name="achievements">{{ old('achievements',$hrObservations->get('achievements')) }}</textarea></div>
            </div>
            <button class="btn">Save HR observations</button>
        </form>
        @endif
    @else
        @include('daily-reports.partials.combined-sales-summary', ['snapshot'=>$dailySummary, 'mode'=>$summaryMode, 'date'=>$date])
    @endif
@endif
@if($hrSummary)
    @php
        $hrRows = $rows->get('hr', collect());
        $hrApproved = $hrRows->isNotEmpty()
            && $hrRows->every(function ($report) {
                return $report->status === 'ceo_reviewed';
            });
    @endphp
    <section class="report-preview-heading"><div><p class="card-label">HR &amp; Administration departmental report</p><h2>Submitted HR daily report</h2><p>The CEO reviews and approves this complete template.</p></div><span class="status-pill">{{ $hrApproved ? 'CEO approved' : 'Awaiting CEO review' }}</span></section>
    @include('daily-reports.partials.hr-legacy', ['snapshot'=>$hrSummary, 'date'=>$date, 'departmentRows'=>$rows->get('hr', collect()), 'submitted'=>$rows->get('hr', collect())->whereIn('status',['manager_shared','changes_requested','ceo_reviewed'])])
    @include('daily-reports.partials.ceo-department-decision', ['departmentCode'=>'hr'])
@endif
@if($productionSummary)
    @php
        $productionRows = $rows->get('production', collect());
        $productionApproved = $productionRows->isNotEmpty()
            && $productionRows->every(function ($report) {
                return $report->status === 'ceo_reviewed';
            });
    @endphp
    <section class="report-preview-heading"><div><p class="card-label">Production departmental report</p><h2>Submitted production report</h2><p>The CEO reviews and approves this complete template.</p></div><span class="status-pill">{{ $productionApproved ? 'CEO approved' : 'Awaiting CEO review' }}</span></section>
    @include('daily-reports.partials.production-legacy', ['snapshot'=>$productionSummary, 'date'=>$date])
    @include('daily-reports.partials.ceo-department-decision', ['departmentCode'=>'production'])
@endif
@if($isCeo)
@php
    $ceoVisibleCount = $rows->sum(function ($departmentRows, $departmentCode) use ($closures) {
        if ($departmentCode === 'finance' && ! $closures->has('finance')) return 0;
        return $departmentRows->whereIn('status', ['manager_shared', 'changes_requested', 'ceo_reviewed'])->count();
    });
@endphp
@if($ceoVisibleCount===0)<section class="exec-panel"><div class="finance-empty"><span aria-hidden="true">✓</span><strong>No unit reports submitted for this date</strong><p>There is nothing for the CEO to review yet. Drafts and units that have not started remain private to their report owners.</p></div></section>@endif
@endif
@foreach($departments as $departmentCode=>$department)
@continue($isFinanceManager && $departmentCode==='finance')
@continue($isCeo)
@php
 $departmentRows=$rows->get($departmentCode,collect()); $reviewed=$departmentRows->where('status','ceo_reviewed')->count();
 $total=$departmentRows->count(); $closed=$closures->has($departmentCode); $ready=$closed && $total>0 && $reviewed===$total;
 $ceoRows=($departmentCode==='finance' && !$closed)
    ? collect()
    : $departmentRows->whereIn('status',['manager_shared','changes_requested','ceo_reviewed']);
 $maySee=$isCeo ? $ceoRows->isNotEmpty() : ($canViewAll || isset($editable[$departmentCode]));
@endphp
@if($maySee)
<section class="card report-department">
 @if(in_array($departmentCode,$closable??[],true) && !$closed)
 <form method="POST" action="{{ route('daily-reports.close-day') }}" class="notice">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><strong>Confirm the complete {{ $department['label'] }} report</strong><p>{{ $departmentCode==='finance' ? 'Once Sales & Collections has arrived, one confirmation combines all Finance sections, closes the report, and sends it to the CEO.' : 'Review the generated sections and save comments only where needed. One confirmation closes and sends the whole departmental report to the CEO.' }}</p><button class="btn">Confirm whole report & close</button></form>
 @endif

 @if(!($isHrManager && $departmentCode==='hr'))
 @foreach($department['units'] as $unitCode=>$unitLabel)
 @php($row=$departmentRows->get($unitCode))
 @if(in_array($unitCode,$editable[$departmentCode]??[],true))
 <details class="report-unit" @if($row?->status==='changes_requested' || data_get($row?->details,'system_source')) open @endif><summary><strong>{{ $unitLabel }}</strong><span>{{ $row ? Str::headline($row->status) : 'Generated report' }}</span></summary>
  @if($row?->status==='ceo_reviewed')<div class="notice notice--ok">Reviewed by the CEO and included in the departmental report.<br><strong>CEO comment:</strong> {{ $row->review_note }}</div>
  @else
  @if(data_get($row?->details,'system_source'))
  <div class="daily-sheet" role="region" aria-label="{{ $unitLabel }} daily report sheet">
   <h3>BROOKS AGRO FARM LIMITED</h3><h4>{{ Str::upper($unitLabel) }} DAILY REPORT — {{ \Carbon\Carbon::parse($date)->format('d F Y') }}</h4>
   <table><thead><tr><th>Unit</th><th>System-generated summary</th><th>Activities and results recorded</th></tr></thead><tbody><tr><td><strong>{{ $unitLabel }}</strong></td><td>{{ $row->summary }}</td><td style="white-space:pre-line">{{ $row->activities }}</td></tr></tbody></table>
  </div>
  @if($row?->status==='changes_requested')<div class="notice"><strong>CEO requested a correction:</strong> {{ $row->review_note }}</div>@endif
  <form method="POST" action="{{ route('daily-reports.save') }}">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><input type="hidden" name="unit_code" value="{{ $unitCode }}"><input type="hidden" name="summary" value="{{ $row->summary }}"><textarea name="activities" hidden>{{ $row->activities }}</textarea><div class="form-grid"><div class="field"><label>Owner comment / challenge (optional)</label><textarea name="challenges" placeholder="Add context only when the generated report needs explanation">{{ old('challenges',$row->challenges) }}</textarea></div><div class="field"><label>Recommendation / follow-up (optional)</label><textarea name="recommendations" placeholder="Add any action the CEO should know about">{{ old('recommendations',$row->recommendations) }}</textarea></div></div>@if($departmentCode==='finance' && $unitCode==='sales_collections')<button class="btn" name="action" value="share">Submit Sales report to Finance</button>@else<button class="btn btn--quiet" name="action" value="draft">Save comment</button>@endif</form>
  @else
  <form method="POST" action="{{ route('daily-reports.save') }}">@csrf
   @if(data_get($row?->details,'system_source')==='sales')<div class="notice notice--ok"><strong>Filled automatically from recorded sales</strong><p>The transactions below already exist in the Sales Register. Review them, add any comment if needed, then share with the CEO—do not enter the sales again.</p></div>@endif
   <input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><input type="hidden" name="unit_code" value="{{ $unitCode }}">
   @if($row?->status==='changes_requested')<div class="notice"><strong>CEO requested a correction:</strong> {{ $row->review_note }}</div>@endif
   <div class="form-grid"><div class="field field--wide"><label>{{ $unitLabel }} summary</label><textarea name="summary" required>{{ old('summary',$row?->summary) }}</textarea></div><div class="field"><label>Activities / results</label><textarea name="activities">{{ old('activities',$row?->activities) }}</textarea></div><div class="field"><label>Challenges</label><textarea name="challenges">{{ old('challenges',$row?->challenges) }}</textarea></div><div class="field field--wide"><label>Recommendations / action required</label><textarea name="recommendations">{{ old('recommendations',$row?->recommendations) }}</textarea></div></div>
   @include('daily-reports.partials.structured-fields',['details'=>$row?->details ?? []])
   <div class="form-actions"><button class="btn btn--quiet" name="action" value="draft">Save draft</button><button class="btn" name="action" value="share">Share with CEO</button></div>
  </form>@endif
  @endif
 </details>
 @elseif(($isFinanceManager && $departmentCode==='finance' && $unitCode==='sales_collections' && $row?->status==='manager_shared') || ($isCeo && $row && in_array($row->status,['manager_shared','changes_requested','ceo_reviewed'])) || (($isDirector || $canViewAll) && $row?->status==='ceo_reviewed'))
 <article class="report-unit card"><div class="section-heading"><div><p class="card-label">{{ $unitLabel }}</p><h3>{{ Str::headline($row->status) }}</h3></div><small>{{ $row->preparer?->displayName() }} · {{ $row->shared_at?->format('d M Y, H:i') }}</small></div>
  <p style="white-space:pre-line">{{ $row->summary }}</p>
  @if($row->activities)<h4>Activities / results</h4><p style="white-space:pre-line">{{ $row->activities }}</p>@endif
  @if($row->challenges)<h4>Challenges</h4><p style="white-space:pre-line">{{ $row->challenges }}</p>@endif
  @if($row->recommendations)<h4>Recommendations</h4><p style="white-space:pre-line">{{ $row->recommendations }}</p>@endif
  @if($row->review_note)<div class="notice"><strong>CEO comment:</strong> {{ $row->review_note }}</div>@endif
  @if($isCeo && $row->status==='manager_shared')<form method="POST" action="{{ route('daily-reports.review',$row) }}">@csrf<div class="field"><label>CEO comment</label><textarea name="review_note" required placeholder="Comment before reviewing or returning this unit report"></textarea></div><div class="form-actions"><button class="btn btn--quiet" name="decision" value="changes">Return for correction</button><button class="btn" name="decision" value="review">Review & include</button></div></form>@endif
  @if($isDirector)<form method="POST" action="{{ route('daily-reports.comments') }}">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><input type="hidden" name="unit_report_id" value="{{ $row->id }}"><div class="field"><label>Comment to {{ $unitLabel }} report owner</label><textarea name="body" required></textarea></div><button class="btn">Send comment</button></form>@endif
 </article>
 @endif
 @endforeach
 @endif

 @if($isDirector && $ready)<form method="POST" action="{{ route('daily-reports.comments') }}">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><div class="field"><label>Comment on the complete {{ $department['label'] }} report</label><textarea name="body" required placeholder="Every concerned unit report owner will be notified"></textarea></div><button class="btn">Send department comment</button></form>@endif
 @foreach($comments->get($departmentCode,collect()) as $comment)<div class="notice"><strong>Director comment · {{ $comment->created_at->format('d M, H:i') }}</strong><br>{{ $comment->body }}</div>@endforeach
</section>
@endif
@endforeach
</div>
<style>
.daily-sheet{margin:14px 0 18px;padding:14px;background:#fff;border:1px solid #b8b1a8;overflow-x:auto;color:#17120f}.daily-sheet h3,.daily-sheet h4{text-align:center;margin:2px 0 8px}.daily-sheet h3{font-size:16px}.daily-sheet h4{font-size:14px}.daily-sheet table{width:100%;min-width:760px;border-collapse:collapse}.daily-sheet th,.daily-sheet td{border:1px solid #322b26;padding:10px;text-align:left;vertical-align:top}.daily-sheet th{background:#d9ead0;font-weight:800}.daily-sheet td:first-child{width:18%}.daily-sheet td:nth-child(2){width:30%}
</style>
@endsection
