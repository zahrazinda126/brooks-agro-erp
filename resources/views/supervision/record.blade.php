@extends('layouts.app')
@section('title','Daily Entry Details')
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">Daily supervision</p><h1>{{ $label }}</h1><p>{{ ucfirst($unit) }} · {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</p></div><a class="btn btn--ghost" href="{{ route('supervision.index') }}">Back to supervision</a></div>

<div class="card">
    <h2>Submitted record</h2>
    <p><strong>Submitted by:</strong> {{ $recorder?->displayName() ?? 'Unknown user' }}</p>
    <p><strong>Current status:</strong> {{ ucfirst($status === 'queried' ? 'returned' : $status) }}</p>
    <div class="table-card"><table><tbody>@foreach($details as $name=>$value)<tr><th>{{ $name }}</th><td>{{ $value === null || $value === '' ? '—' : $value }}</td></tr>@endforeach</tbody></table></div>
</div>

@if($lines->isNotEmpty())
<div class="card table-card"><h2>Egg breakdown</h2><table><thead><tr><th>Grade</th><th>Trays</th><th>Loose eggs</th><th>Total eggs</th></tr></thead><tbody>@foreach($lines as $line)<tr><td>{{ $line->name }}</td><td>{{ number_format($line->trays) }}</td><td>{{ number_format($line->loose_eggs) }}</td><td>{{ number_format($line->total_eggs) }}</td></tr>@endforeach</tbody></table></div>
@endif

@if($canReview)
<form class="card" method="POST" action="{{ route('supervision.records.review',[$type,$record->id]) }}">@csrf
    <h2>Supervisor decision</h2><p class="muted">Approve only after checking all the details above.</p>
    <div class="field"><label>Review note</label><textarea name="review_note" rows="3" placeholder="Required when returning the entry">{{ old('review_note') }}</textarea></div>
    <button class="btn" name="decision" value="approved">Approve entry</button>
    <button class="btn btn--ghost" name="decision" value="returned">Return for correction</button>
</form>
@elseif($status === 'submitted')
<div class="notice">You may view this entry, but only its appointed supervisor can approve or return it.</div>
@endif
@endsection
