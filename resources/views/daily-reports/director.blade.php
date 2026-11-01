@extends('layouts.app')
@section('title','Departmental Daily Reports')
@section('content')
<x-report-header eyebrow="Director briefing" title="Departmental Daily Reports" description="One complete report per department, released after CEO approval." :period="\Carbon\Carbon::parse($date)->format('l, d F Y')"><x-slot:actions><form method="GET"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
@if(! $open)
<section class="exec-panel report-links">
 @foreach($departments as $code=>$department)
  @php
   $ready = $readyDepartments->contains($code);
  @endphp
  @if($ready)<a class="report-link" href="{{ route('daily-reports.index', ['date'=>$date, 'department'=>$code]) }}">@else<div class="report-link is-waiting">@endif
   <span><strong>{{ $department['label'] }}</strong><small>Daily departmental report</small></span>
   <span class="status-pill">{{ $ready ? 'CEO approved' : 'Awaiting CEO approval' }}</span>@if($ready)<span class="report-link__go" aria-hidden="true">›</span>@endif
  @if($ready)</a>@else</div>@endif
 @endforeach
 @if($isDirector)
 @if($ceoReport)<a class="report-link" href="{{ route('ceo.reports.show', $ceoReport) }}">@else<div class="report-link is-waiting">@endif
  <span><strong>Chief Executive Officer</strong><small>CEO's daily report{{ $ceoReport ? ' · '.$ceoReport->author?->displayName().' · sent '.$ceoReport->submitted_at?->format('H:i') : '' }}</small></span>
  <span class="status-pill">{{ $ceoReport ? ($ceoReport->read_at ? 'Read' : 'New') : 'Not yet sent' }}</span>@if($ceoReport)<span class="report-link__go" aria-hidden="true">›</span>@endif
 @if($ceoReport)</a>@else</div>@endif
 @endif
 @php
  // The PA writes his report from here too: until it is sent, his row opens the report template.
  $paWrites = ! $isDirector && ! $paReport;
 @endphp
 @if($paReport)<a class="report-link" href="{{ route('pa.reports.show', $paReport) }}">@elseif($paWrites)<a class="report-link report-link--write" href="{{ route('pa.daily-report', ['date' => $date]) }}">@else<div class="report-link is-waiting">@endif
  <span><strong>Personal Assistant</strong><small>{{ $paWrites ? 'Write and send your daily report for this day' : "PA's daily report".($paReport ? ' · '.$paReport->author?->displayName().' · sent '.$paReport->submitted_at?->format('H:i') : '') }}</small></span>
  <span class="status-pill">{{ $paReport ? ($paReport->read_at ? 'Read' : ($isDirector ? 'New' : 'Sent')) : ($paWrites ? 'Write report' : 'Not yet sent') }}</span>@if($paReport || $paWrites)<span class="report-link__go" aria-hidden="true">›</span>@endif
 @if($paReport || $paWrites)</a>@else</div>@endif
</section>
@else
<p class="u-mb-12 no-print"><a class="btn btn--quiet btn--small" href="{{ route('daily-reports.index', ['date'=>$date]) }}">← All reports for this day</a></p>
@foreach(array_intersect_key($departments, [$open => true]) as $code=>$department)
@php
$departmentRows=$rows->get($code,collect());
$required=count($department['units']);
$reviewed=$departmentRows->where('status','ceo_reviewed')->count();
$ready=$readyDepartments->contains($code);
$submitted=$departmentRows->where('status','ceo_reviewed');
@endphp
<section class="exec-panel department-report">
 <div class="section-heading"><div><p class="card-label">Daily departmental report</p><h2>{{ $department['label'] }}</h2><p>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</p></div><span class="status-pill">{{ $ready ? 'CEO approved' : 'Awaiting CEO approval' }}</span></div>
 @if(! $ready)
  <div class="notice"><strong>Report not yet available.</strong><br></div>
 @else
  @php($snapshot=$snapshots[$code])
  @if($submitted->isNotEmpty())
  <details class="disclosure"><summary><span><strong>Manager notes and CEO review</strong><small>Approved supporting narrative for this departmental report</small></span></summary><div class="disclosure__body">
  @foreach($submitted as $submission)<article class="report-unit"><div class="section-heading"><div><h3>Department contribution</h3><p>{{ $submission->preparer?->displayName() }} · {{ $submission->shared_at?->format('d M Y, H:i') }}</p></div><span class="status-pill">CEO approved</span></div><p class="u-pre-line">{{ $submission->summary }}</p>@if($submission->activities)<h4>Activities and results</h4><p class="u-pre-line">{{ $submission->activities }}</p>@endif @if($submission->challenges)<h4>Challenges</h4><p class="u-pre-line">{{ $submission->challenges }}</p>@endif @if($submission->recommendations)<h4>Recommendations</h4><p class="u-pre-line">{{ $submission->recommendations }}</p>@endif @if($submission->review_note)<div class="notice"><strong>CEO comment:</strong> {{ $submission->review_note }}</div>@endif</article>@endforeach
  </div></details>@endif
  @if($code==='production')
   @include('daily-reports.partials.production-legacy', ['snapshot'=>$snapshot, 'date'=>$date])
  @elseif($code==='hr')
   @include('daily-reports.partials.hr-legacy', ['snapshot'=>$snapshot, 'date'=>$date, 'departmentRows'=>$departmentRows, 'submitted'=>$submitted])
  @else
   @include('daily-reports.partials.finance-legacy', ['snapshot'=>$snapshot, 'date'=>$date])
  @endif
  @if($isDirector)<form method="POST" action="{{ route('daily-reports.comments') }}" class="report-comment-form">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $code }}"><div class="field"><label>Director comment to {{ $department['label'] }}</label><textarea name="body" required></textarea></div><button class="btn">Send comment</button></form>@endif
  @foreach($comments->get($code,collect()) as $comment)<div class="notice"><strong>Director comment · {{ $comment->created_at->format('d M, H:i') }}</strong><br>{{ $comment->body }}</div>@endforeach
 @endif
</section>
@endforeach
@endif
@pushOnce('styles')<style>.report-links{display:grid;gap:8px;padding:12px}.report-link{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid #eadfd3;border-radius:12px;background:#fff;color:inherit;text-decoration:none}a.report-link:hover{border-color:#c89663;background:#fffaf3}.report-link>span:first-child{flex:1}.report-link strong{display:block;font-size:1rem}.report-link small{display:block;color:#806b5d;margin-top:2px}.report-link.is-waiting{opacity:.65}.report-link__go{font-size:1.5rem;color:#8b1e2d;line-height:1}.report-link--write{border-color:#c89663;background:#fffaf3}.report-link--write .status-pill{background:#8b1e2d;color:#fff}</style>@endPushOnce
@endsection
