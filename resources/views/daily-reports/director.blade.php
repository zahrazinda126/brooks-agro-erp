@extends('layouts.app')
@section('title','Departmental Daily Reports')
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">Director briefing</p><h1>Departmental Daily Reports</h1><p>One complete report per department, built from the records entered across that department.</p></div><form method="GET"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form></div>
@foreach($departments as $code=>$department)
@php
$departmentRows=$rows->get($code,collect()); $required=count($department['units']); $reviewed=$departmentRows->where('status','ceo_reviewed')->count(); $ready=$reviewed===$required; $snapshot=$snapshots[$code]; $submitted=$departmentRows->whereIn('status',['manager_shared','changes_requested','ceo_reviewed']);
@endphp
<section class="exec-panel department-report">
 <div class="section-heading"><div><p class="card-label">Daily departmental report</p><h2>{{ $department['label'] }}</h2><p>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</p></div><span class="status-pill">{{ $ready ? 'CEO reviewed' : 'Preview · awaiting CEO review' }}</span></div>
 @if($submitted->isNotEmpty())
 <details class="disclosure"><summary><span><strong>Manager notes and CEO review</strong><small>Supporting narrative for this departmental report</small></span></summary><div class="disclosure__body">
 @foreach($submitted as $submission)<article class="report-unit"><div class="section-heading"><div><h3>Department contribution</h3><p>{{ $submission->preparer?->displayName() }} · {{ $submission->shared_at?->format('d M Y, H:i') }}</p></div><span class="status-pill">{{ Str::headline($submission->status) }}</span></div><p style="white-space:pre-line">{{ $submission->summary }}</p>@if($submission->activities)<h4>Activities and results</h4><p style="white-space:pre-line">{{ $submission->activities }}</p>@endif @if($submission->challenges)<h4>Challenges</h4><p style="white-space:pre-line">{{ $submission->challenges }}</p>@endif @if($submission->recommendations)<h4>Recommendations</h4><p style="white-space:pre-line">{{ $submission->recommendations }}</p>@endif @if($submission->review_note)<div class="notice"><strong>CEO comment:</strong> {{ $submission->review_note }}</div>@endif</article>@endforeach
 </div></details>@endif
 @if($code==='production')
  @include('daily-reports.partials.production-legacy', ['snapshot'=>$snapshot, 'date'=>$date])
 @elseif($code==='hr')
  @include('daily-reports.partials.hr-legacy', ['snapshot'=>$snapshot, 'date'=>$date, 'departmentRows'=>$departmentRows, 'submitted'=>$submitted])
 @else
  @include('daily-reports.partials.finance-legacy', ['snapshot'=>$snapshot, 'date'=>$date])
 @endif
 <form method="POST" action="{{ route('daily-reports.comments') }}" class="report-comment-form">@csrf<input type="hidden" name="report_date" value="{{ $date }}"><input type="hidden" name="department_code" value="{{ $code }}"><div class="field"><label>Director comment to {{ $department['label'] }}</label><textarea name="body" required></textarea></div><button class="btn">Send comment</button></form>
 @foreach($comments->get($code,collect()) as $comment)<div class="notice"><strong>Director comment · {{ $comment->created_at->format('d M, H:i') }}</strong><br>{{ $comment->body }}</div>@endforeach
</section>
@endforeach
@endsection
