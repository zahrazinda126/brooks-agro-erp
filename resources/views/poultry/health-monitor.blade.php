@extends('layouts.app')
@section('title', $taskTitle)
@section('content')
@include('partials.monitor-group', ['group' => 'health', 'current' => $task])

@php $canExamine = $examineLinks !== []; @endphp
@if($recordLinks || $canExamine)
<div class="screen-actions">@if($canExamine)<button type="button" class="btn btn--small btn--quiet" onclick="document.getElementById('examine-deaths').showModal()">Examine deaths @if(array_sum(array_column($examineLinks, 1)))<span class="view-switch__count">{{ array_sum(array_column($examineLinks, 1)) }}</span>@endif</button>@endif @if($recordLinks)<button type="button" class="btn btn--small" onclick="document.getElementById('record-health').showModal()">+ Record {{ $task === 'deaths' ? 'deaths' : 'treatment' }}</button>@endif</div>
@endif
@if($recordLinks)
<dialog id="record-health" class="page-dialog" aria-label="Choose what to record"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>What are you recording?</h3>
    <div class="group-choices" data-keep-link>@foreach($recordLinks as $label => $href)<a class="group-choice" href="{{ $href }}"><strong>{{ $label }}</strong></a>@endforeach</div>
</dialog>
@endif

@if($withdrawals->isNotEmpty())
<div class="notice notice--alarm"><strong>Do not sell yet — animals under medicine withdrawal</strong>
    <ul>@foreach($withdrawals as $w)<li>{{ $w['unit'] }} {{ $w['group'] }}: no {{ $w['what'] }} sold until <strong>{{ $w['until']->format('d M Y') }}</strong></li>@endforeach</ul>
</div>
@endif

@if($canExamine)
<dialog id="examine-deaths" class="page-dialog" aria-label="Choose which deaths to examine"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Which deaths are you examining?</h3>
    <div class="group-choices" data-keep-link>@foreach($examineLinks as $label => [$href, $count])<a class="group-choice" href="{{ $href }}"><strong>{{ $label }}</strong><small>{{ $count ? $count.' waiting' : 'Nothing waiting' }}</small></a>@endforeach</div>
</dialog>
@endif
<form method="GET" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="hm-from">From</label><input id="hm-from" type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label for="hm-to">To</label><input id="hm-to" type="date" name="to" value="{{ $to }}"></div>
        <div class="field"><label for="hm-unit">Unit</label><select id="hm-unit" name="unit" onchange="this.form.batch.value = ''; this.form.submit()"><option value="all">All units</option>@foreach($units as $key => $label)<option value="{{ $key }}" @selected($unit === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="hm-batch">Batch / group</label><select id="hm-batch" name="batch"><option value="">All batches and groups</option>
            @foreach($groups as $u => $list)
                @continue($unit !== 'all' && $unit !== $u)
                @if($list->isNotEmpty())<optgroup label="{{ $units[$u] }}">@foreach($list as $g)<option value="{{ $u }}:{{ $g['id'] }}" @selected($batch === $u.':'.$g['id'])>{{ $g['code'] }}</option>@endforeach</optgroup>@endif
            @endforeach
        </select></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button></div>
    </div>
</form>

<div class="card table-wrap">
<table>
    <thead><tr><th>Date</th><th>Unit</th><th>Batch / group</th><th>{{ $task === 'deaths' ? 'Type' : 'Event' }}</th><th>{{ $task === 'deaths' ? 'Cause' : 'Medicine / condition' }}</th><th>{{ $task === 'deaths' ? 'Lost' : 'Animals' }}</th><th>{{ $task === 'deaths' ? 'Status / disposal' : 'Withdrawal' }}</th><th>Recorded by</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr><td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['group'] ?? '—' }}</td><td>{{ $row['event'] }}</td><td>{{ $row['detail'] }}</td><td>{{ number_format((float) $row['count']) }}</td><td>{{ $row['extra'] ?? '—' }}</td><td>{{ $row['by'] ?? '—' }}</td></tr>
    @empty
        <tr><td colspan="8" class="muted">No {{ strtolower($taskTitle) }} recorded for these filters.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
