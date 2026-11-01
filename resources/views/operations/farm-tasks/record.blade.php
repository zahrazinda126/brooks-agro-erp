@extends('layouts.app')
@section('title', $task->task_name)
@section('content')
@php
    $statusPill = ['submitted' => ['pending', 'Awaiting confirmation'], 'approved' => ['approved', 'Confirmed'], 'returned' => ['inactive', 'Returned']];
@endphp
<div class="page-head"><h2>{{ $task->task_name }}</h2><p>{{ $unitName }}@if($task->note) · {{ $task->note }}@endif</p></div>

<form method="POST" action="{{ route('my-tasks.store', $task->id) }}" class="exec-panel">@csrf
    @if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif
    <div class="form-grid">
        <div class="field"><label for="mt-date">Date</label><input id="mt-date" type="date" name="done_on" value="{{ old('done_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
        @if($task->measure)
            <div class="field"><label for="mt-qty">Collected ({{ $task->measure }})</label><input id="mt-qty" type="number" name="quantity" min="0.01" step="0.01" value="{{ old('quantity') }}" required inputmode="decimal"></div>
            <div class="field field--wide"><label for="mt-details">Notes <small>(optional)</small></label><textarea id="mt-details" name="details" maxlength="2000">{{ old('details') }}</textarea></div>
        @else
            <div class="field field--wide"><label for="mt-details">What was done and where</label><textarea id="mt-details" name="details" maxlength="2000" required placeholder="e.g. Sprayed houses 1–4 against mites">{{ old('details') }}</textarea></div>
        @endif
    </div>
    <div class="form-actions"><button class="btn">Save</button><a class="btn btn--quiet" href="{{ route('daily.index') }}">Cancel</a></div>
</form>

@if($recent->isNotEmpty())
<section class="card table-wrap u-mt-12"><h3>What you recorded</h3>
<table class="exec-table"><thead><tr><th>Date</th>@if($task->measure)<th class="num">Collected</th>@endif<th>Details</th><th>Status</th></tr></thead><tbody>
@foreach($recent as $e)<tr><td>{{ \Carbon\Carbon::parse($e->done_on)->format('d M Y') }}</td>@if($task->measure)<td class="num">{{ number_format((float) $e->quantity, fmod((float) $e->quantity, 1) ? 2 : 0) }} {{ $e->measure }}</td>@endif<td>{{ $e->details ?: '—' }}</td><td><x-status-pill :status="$statusPill[$e->verification_status][0] ?? 'pending'" :label="$statusPill[$e->verification_status][1] ?? ucfirst($e->verification_status)" /></td></tr>@endforeach
</tbody></table></section>
@endif
@endsection
