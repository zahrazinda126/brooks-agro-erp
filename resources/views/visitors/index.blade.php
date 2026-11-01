@extends('layouts.app')
@section('title', 'Visitor Register')
@section('content')
<form method="GET" action="{{ route('visitors.index') }}" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="v-q">Search</label><input id="v-q" type="search" name="search" value="{{ $search }}" placeholder="Name, organisation, host or vehicle"></div>
        <div class="field"><label for="v-status">Status</label><select id="v-status" name="status"><option value="">All visitors</option><option value="inside" @selected(request('status')==='inside')>On the farm now ({{ $inside }})</option></select></div>
        <div class="field"><label for="v-from">From</label><input id="v-from" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label for="v-to">To</label><input id="v-to" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('visitors.index') }}">Reset</a></div>
    </div>
    <p class="muted u-mt-12">{{ $inside }} on the farm now · {{ $today }} {{ $today === 1 ? 'visitor' : 'visitors' }} today</p>
</form>
<div class="card table-card"><table><thead><tr><th>Visitor</th><th>Visiting</th><th>Purpose</th><th>Arrived</th><th>Vehicle / items</th><th>Status</th></tr></thead><tbody>
@forelse($visitors as $visitor)<tr><td><strong>{{ $visitor->visitor_name }}</strong><br><span class="muted">{{ $visitor->organization ?: 'Private visitor' }} @if($visitor->phone) · {{ $visitor->phone }}@endif</span></td><td>{{ $visitor->person_to_visit }}<br><span class="muted">Pass: {{ $visitor->pass_number ?: '—' }}</span></td><td>{{ $visitor->purpose }}</td><td>{{ $visitor->checked_in_at->format('d M Y, H:i') }}<br><span class="muted">Recorded by {{ $visitor->recorder?->displayName() }}</span></td><td>{{ $visitor->vehicle_registration ?: 'No vehicle recorded' }}<br><span class="muted">{{ $visitor->items_carried ?: 'No items recorded' }}</span></td><td>
@if($visitor->checked_out_at)<strong>Checked out</strong><br>{{ $visitor->checked_out_at->format('d M Y, H:i') }}
@elseif(auth()->user()->canDo('visitors','edit'))<form method="POST" action="{{ route('visitors.checkout',$visitor) }}">@csrf<input type="hidden" name="checked_out_at" value="{{ now()->format('Y-m-d H:i:s') }}"><button class="btn btn--small">Check out</button></form>
@else<strong>On farm</strong>@endif
</td></tr>@empty<tr><td colspan="6">No visitors have been recorded.</td></tr>@endforelse
</tbody></table>{{ $visitors->links() }}</div>
@endsection
