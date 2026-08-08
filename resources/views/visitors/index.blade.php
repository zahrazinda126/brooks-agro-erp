@extends('layouts.app')
@section('title', 'Visitor Register')
@section('content')
<div class="page-head"><div><p class="eyebrow">Front office</p><h1>Visitor Register</h1><p>Record everyone entering the farm and confirm when they leave.</p></div></div>
<div class="summary-grid"><div class="summary-card"><span>Currently on farm</span><strong>{{ $inside }}</strong></div><div class="summary-card"><span>Visitors today</span><strong>{{ $today }}</strong></div></div>

@if(auth()->user()->canDo('visitors','create'))
<form class="card" method="POST" action="{{ route('visitors.store') }}">@csrf<h2>Check in a visitor</h2>
<div class="form-grid">
 <div class="field"><label>Visitor's name</label><input name="visitor_name" value="{{ old('visitor_name') }}" required></div>
 <div class="field"><label>Phone number</label><input name="phone" value="{{ old('phone') }}"></div>
 <div class="field"><label>Organisation</label><input name="organization" value="{{ old('organization') }}"></div>
 <div class="field"><label>Person being visited</label><input name="person_to_visit" value="{{ old('person_to_visit') }}" required></div>
 <div class="field field--wide"><label>Purpose of visit</label><input name="purpose" value="{{ old('purpose') }}" required></div>
 <div class="field"><label>Visit date</label><input type="date" name="visit_date" value="{{ old('visit_date',now()->toDateString()) }}" required></div>
 <div class="field"><label>Check-in time</label><input type="datetime-local" name="checked_in_at" value="{{ old('checked_in_at',now()->format('Y-m-d\TH:i')) }}" required></div>
 <div class="field"><label>Visitor pass number</label><input name="pass_number" value="{{ old('pass_number') }}"></div>
 <div class="field"><label>Vehicle registration</label><input name="vehicle_registration" value="{{ old('vehicle_registration') }}"></div>
 <div class="field"><label>Items carried in</label><input name="items_carried" value="{{ old('items_carried') }}"></div>
 <div class="field"><label>Notes</label><input name="notes" value="{{ old('notes') }}"></div>
</div>@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif<button class="btn">Check in visitor</button></form>
@endif

<div class="card"><form method="GET" class="form-grid"><div class="field"><label>Search visitors</label><input name="search" value="{{ $search }}" placeholder="Name, organisation, host or vehicle"></div><div class="field"><label>Status</label><select name="status"><option value="">All visitors</option><option value="inside" @selected(request('status')==='inside')>Currently on farm</option></select></div><div class="form-actions"><button class="btn btn--ghost">Search</button></div></form></div>
<div class="card table-card"><table><thead><tr><th>Visitor</th><th>Visiting</th><th>Purpose</th><th>Arrived</th><th>Vehicle / items</th><th>Status</th></tr></thead><tbody>
@forelse($visitors as $visitor)<tr><td><strong>{{ $visitor->visitor_name }}</strong><br><span class="muted">{{ $visitor->organization ?: 'Private visitor' }} @if($visitor->phone) · {{ $visitor->phone }}@endif</span></td><td>{{ $visitor->person_to_visit }}<br><span class="muted">Pass: {{ $visitor->pass_number ?: '—' }}</span></td><td>{{ $visitor->purpose }}</td><td>{{ $visitor->checked_in_at->format('d M Y, H:i') }}<br><span class="muted">Recorded by {{ $visitor->recorder?->displayName() }}</span></td><td>{{ $visitor->vehicle_registration ?: 'No vehicle recorded' }}<br><span class="muted">{{ $visitor->items_carried ?: 'No items recorded' }}</span></td><td>
@if($visitor->checked_out_at)<strong>Checked out</strong><br>{{ $visitor->checked_out_at->format('d M Y, H:i') }}
@elseif(auth()->user()->canDo('visitors','edit'))<form method="POST" action="{{ route('visitors.checkout',$visitor) }}">@csrf<input type="hidden" name="checked_out_at" value="{{ now()->format('Y-m-d H:i:s') }}"><button class="btn">Check out now</button></form>
@else<strong>On farm</strong>@endif
</td></tr>@empty<tr><td colspan="6">No visitors have been recorded.</td></tr>@endforelse
</tbody></table>{{ $visitors->links() }}</div>
@endsection
