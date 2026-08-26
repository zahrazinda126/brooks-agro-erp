@extends('layouts.app')
@section('title','Daily Department Reports')
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">Daily reporting protocol</p><h1>Daily Department Reports</h1><p>Unit reports are reviewed by the CEO, then combined automatically into one report for each department.</p></div><form method="GET"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form></div>
@foreach($departments as $departmentCode=>$department)
@php
 $departmentRows=$rows->get($departmentCode,collect()); $reviewed=$departmentRows->where('status','ceo_reviewed')->count();
 $total=count($department['units']); $ready=$reviewed===$total; $maySee=$canViewAll || isset($editable[$departmentCode]);
@endphp
@if($maySee)
<section class="card report-department">
 <div class="section-heading"><div><p class="card-label">{{ $department['label'] }}</p><h2>{{ $ready ? 'Department report ready' : 'Unit reports in progress' }}</h2><p>{{ $reviewed }} of {{ $total }} units reviewed by the CEO.</p></div><span class="status-pill">{{ $reviewed }}/{{ $total }}</span></div>
 <div class="approval-timeline">@foreach($department['units'] as $unitCode=>$unitLabel)@php($row=$departmentRows->get($unitCode))<div class="approval-step"><strong>{{ $unitLabel }}</strong><span>{{ $row ? Str::headline($row->status) : 'Not started' }}</span><small>{{ $row?->preparer?->displayName() ?? 'Waiting for unit owner' }}</small></div>@endforeach</div>

 @foreach($department['units'] as $unitCode=>$unitLabel)
 @php($row=$departmentRows->get($unitCode))
 @if(in_array($unitCode,$editable[$departmentCode]??[],true))
 <details class="report-unit" @if($row?->status==='changes_requested') open @endif><summary><strong>{{ $unitLabel }}</strong><span>{{ $row ? Str::headline($row->status) : 'New report' }}</span></summary>
  @if($row?->status==='ceo_reviewed')<div class="notice notice--ok">Reviewed by the CEO and included in the departmental report.<br><strong>CEO comment:</strong> {{ $row->review_note }}</div>
  @else
  <form method="POST" action="{{ route('daily-reports.save') }}">@csrf
   <input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><input type="hidden" name="unit_code" value="{{ $unitCode }}">
   @if($row?->status==='changes_requested')<div class="notice"><strong>CEO requested a correction:</strong> {{ $row->review_note }}</div>@endif
   <div class="form-grid"><div class="field field--wide"><label>{{ $unitLabel }} summary</label><textarea name="summary" required>{{ old('summary',$row?->summary) }}</textarea></div><div class="field"><label>Activities / results</label><textarea name="activities">{{ old('activities',$row?->activities) }}</textarea></div><div class="field"><label>Challenges</label><textarea name="challenges">{{ old('challenges',$row?->challenges) }}</textarea></div><div class="field field--wide"><label>Recommendations / action required</label><textarea name="recommendations">{{ old('recommendations',$row?->recommendations) }}</textarea></div></div>
   @include('daily-reports.partials.structured-fields',['details'=>$row?->details ?? []])
   <div class="form-actions"><button class="btn btn--quiet" name="action" value="draft">Save draft</button><button class="btn" name="action" value="share">Share with CEO</button></div>
  </form>@endif
 </details>
 @elseif(($isCeo && $row && in_array($row->status,['manager_shared','changes_requested','ceo_reviewed'])) || (($isDirector || $canViewAll) && $row?->status==='ceo_reviewed'))
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

 @if($isDirector && $ready)<form method="POST" action="{{ route('daily-reports.comments') }}">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $departmentCode }}"><div class="field"><label>Comment on the complete {{ $department['label'] }} report</label><textarea name="body" required placeholder="Every concerned unit report owner will be notified"></textarea></div><button class="btn">Send department comment</button></form>@endif
 @foreach($comments->get($departmentCode,collect()) as $comment)<div class="notice"><strong>Director comment · {{ $comment->created_at->format('d M, H:i') }}</strong><br>{{ $comment->body }}</div>@endforeach
</section>
@endif
@endforeach
@endsection
