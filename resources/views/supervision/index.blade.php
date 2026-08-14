@extends('layouts.app')
@section('title','Daily Supervision')
@section('content')
<div class="page-head"><div><p class="eyebrow">Operational verification</p><h1>Daily Supervision</h1><p>Workers submit work to the appointed supervisor. Only approved records become official reporting data.</p></div></div>

@if($isCeo)
<form class="card" method="POST" action="{{ route('supervision.assignments.store') }}">@csrf<h2>Appoint a daily supervisor</h2><p class="muted">Any active farm worker with an account may be appointed temporarily. This does not change their permanent HR role.</p><div class="form-grid">
<div class="field"><label>Farm worker</label><select name="supervisor_id" required><option value="">Choose worker</option>@foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->displayName() }} · {{ $worker->employee?->job_title }}</option>@endforeach</select></div>
<div class="field"><label>Farm unit</label><select name="unit_code" required>@foreach($units as $unit)<option value="{{ $unit }}">{{ ucfirst($unit) }}</option>@endforeach</select></div>
<div class="field"><label>Starts</label><input type="date" name="starts_on" value="{{ now()->toDateString() }}" required></div><div class="field"><label>Ends</label><input type="date" name="ends_on"></div>
<div class="field"><label>Shift</label><select name="shift"><option value="all_day">All day</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="night">Night</option></select></div><div class="field"><label>Notes</label><input name="notes"></div></div><button class="btn">Appoint supervisor</button></form>
@endif

@if($isCeo||$isDirector)<div class="card table-card"><h2>Supervisor appointments</h2><table><thead><tr><th>Supervisor</th><th>Unit</th><th>Period</th><th>Shift</th><th>Status</th><th></th></tr></thead><tbody>@forelse($assignments as $a)<tr><td>{{ $a->supervisor->displayName() }}</td><td>{{ ucfirst($a->unit_code) }}</td><td>{{ $a->starts_on->format('d M Y') }} – {{ $a->ends_on?->format('d M Y') ?? 'Until ended' }}</td><td>{{ ucwords(str_replace('_',' ',$a->shift)) }}</td><td>{{ $a->is_active?'Active':'Ended' }}</td><td>@if($isCeo&&$a->is_active)<form method="POST" action="{{ route('supervision.assignments.end',$a) }}">@csrf<button class="btn btn--ghost">End assignment</button></form>@endif</td></tr>@empty<tr><td colspan="6">No supervisors appointed yet.</td></tr>@endforelse</tbody></table></div>@endif

@if($mine->isNotEmpty())
<div class="card table-card"><h2>Daily entries awaiting verification</h2><p class="muted">Open an entry and inspect its full details before making a decision.</p><table><thead><tr><th>Date</th><th>Unit</th><th>Entry</th><th></th></tr></thead><tbody>@forelse($pendingRecords as $record)<tr><td>{{ \Carbon\Carbon::parse($record->date)->format('d M Y') }}</td><td>{{ ucfirst($record->unit) }}</td><td>{{ $record->label }}</td><td><a class="btn" href="{{ route('supervision.records.show',[$record->type,$record->id]) }}">View details</a></td></tr>@empty<tr><td colspan="4">No daily records await your review.</td></tr>@endforelse</tbody></table></div>

<div class="card table-card"><h2>Supervisor decisions</h2><p class="muted">Entries already reviewed by the appointed supervisor.</p><table><thead><tr><th>Entry date</th><th>Unit</th><th>Entry</th><th>Decision</th><th>Supervisor</th><th>Reviewed</th><th>Note</th></tr></thead><tbody>@forelse($reviewedRecords as $record)<tr><td>{{ \Carbon\Carbon::parse($record->date)->format('d M Y') }}</td><td>{{ ucfirst($record->unit) }}</td><td><a href="{{ route('supervision.records.show',[$record->type,$record->id]) }}">{{ $record->label }}</a></td><td>{{ ucfirst($record->decision) }}</td><td>{{ $record->reviewer }}</td><td>{{ $record->reviewed_at ? \Carbon\Carbon::parse($record->reviewed_at)->format('d M Y, H:i') : '—' }}</td><td>{{ $record->note ?: '—' }}</td></tr>@empty<tr><td colspan="7">No supervisor decisions have been recorded yet.</td></tr>@endforelse</tbody></table></div>
@endif

@endsection
