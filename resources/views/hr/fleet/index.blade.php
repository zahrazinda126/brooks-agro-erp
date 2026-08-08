@extends('layouts.app')
@section('title','Daily Fleet Management')
@section('content')
<div class="page-head"><p class="eyebrow">HR & Administration</p><h2>Daily Fleet Management</h2><p>Vehicle movements, drivers, fuel consumption, issues and maintenance.</p></div>
@if($canRecord)
<form method="POST" action="{{ route('hr.fleet.store') }}" class="exec-panel">@csrf
<h3>Add fleet record</h3><div class="form-grid">
<div class="field"><label>Date</label><input type="date" name="recorded_on" value="{{ old('recorded_on',now()->toDateString()) }}" required></div>
<div class="field"><label>Vehicle ID</label><input name="vehicle_id" value="{{ old('vehicle_id') }}" required></div>
<div class="field"><label>Driver name</label><input name="driver_name" value="{{ old('driver_name') }}" required></div>
<div class="field"><label>Set-off date and time</label><input type="datetime-local" name="set_off_at" value="{{ old('set_off_at') }}"></div>
<div class="field"><label>Pick-up point</label><input name="pickup_point" value="{{ old('pickup_point') }}"></div>
<div class="field"><label>Destination</label><input name="destination" value="{{ old('destination') }}"></div>
<div class="field"><label>Number of people</label><input type="number" min="0" name="people_count" value="{{ old('people_count') }}"></div>
<div class="field"><label>Fuel consumed (litres)</label><input type="number" min="0" step="0.01" name="fuel_consumption_litres" value="{{ old('fuel_consumption_litres') }}"></div>
<div class="field"><label>Fuel receipt/reference</label><input name="fuel_receipt" value="{{ old('fuel_receipt') }}"></div>
<div class="field field--wide"><label>Purpose</label><textarea name="purpose" required>{{ old('purpose') }}</textarea></div>
<div class="field field--wide"><label>Vehicle issues</label><textarea name="vehicle_issues">{{ old('vehicle_issues') }}</textarea></div>
<div class="field field--wide"><label>Vehicle maintenance</label><textarea name="maintenance">{{ old('maintenance') }}</textarea></div>
</div><div class="form-actions"><button class="btn">Save fleet record</button></div></form>
@endif
<section class="exec-panel table-wrap"><h3>Fleet records</h3><table class="exec-table"><thead><tr><th>Date</th><th>Vehicle</th><th>Driver</th><th>Journey</th><th>People</th><th>Purpose</th><th>Fuel</th><th>Issues / maintenance</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->recorded_on->format('d M Y') }}</td><td><strong>{{ $record->vehicle_id }}</strong></td><td>{{ $record->driver_name }}</td><td>{{ $record->pickup_point ?: '—' }} → {{ $record->destination ?: '—' }}@if($record->set_off_at)<br><small>{{ $record->set_off_at->format('d M Y, H:i') }}</small>@endif</td><td>{{ $record->people_count ?? '—' }}</td><td>{{ $record->purpose }}</td><td>{{ $record->fuel_consumption_litres !== null ? number_format((float)$record->fuel_consumption_litres,2).' L' : '—' }}@if($record->fuel_receipt)<br><small>{{ $record->fuel_receipt }}</small>@endif</td><td>{{ $record->vehicle_issues ?: 'No issues' }}@if($record->maintenance)<br><small>Maintenance: {{ $record->maintenance }}</small>@endif</td></tr>
@empty<tr><td colspan="8">No fleet records have been entered yet.</td></tr>@endforelse
</tbody></table></section>{{ $records->links() }}
@endsection
