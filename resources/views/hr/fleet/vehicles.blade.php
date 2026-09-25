@extends('layouts.app')
@section('title', 'Company Vehicle Register')
@section('content')
<div class="page-head page-head--split"><div><p class="eyebrow">HR &amp; Administration</p><h2>Company Vehicle Register</h2><p>Register company vehicles before they are used in fleet journeys.</p></div><a class="btn btn--quiet btn--inline" href="{{ route('hr.fleet.index') }}">Open fleet journeys</a></div>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="notice notice--alarm"><strong>{{ $errors->first() }}</strong></div>@endif

@if($canManage)
<details class="card disclosure" open><summary><strong>Register company vehicle</strong></summary><div class="disclosure__body"><form method="POST" action="{{ route('hr.vehicles.store') }}" class="form-grid">@csrf
    <div class="field"><label>Registration number</label><input name="registration_number" value="{{ old('registration_number') }}" placeholder="e.g. UBA 123A" required></div>
    <div class="field"><label>Make and model</label><input name="make_model" value="{{ old('make_model') }}" placeholder="e.g. Toyota Hilux"></div>
    <div class="field"><label>Vehicle type</label><input name="vehicle_type" value="{{ old('vehicle_type') }}" placeholder="e.g. Pickup, truck, motorcycle"></div>
    <div class="field"><label>Capacity</label><input type="number" min="0" step="0.01" name="capacity" value="{{ old('capacity') }}" placeholder="Passengers or tonnes"></div>
    <div class="field"><label>Default driver</label><input name="default_driver_name" value="{{ old('default_driver_name') }}"></div>
    <div class="field"><label>Current kilometre reading</label><input type="number" min="0" step="0.1" name="current_odometer_km" value="{{ old('current_odometer_km') }}"></div>
    <div class="field"><label>Next service at (km)</label><input type="number" min="0" step="0.1" name="next_service_odometer_km" value="{{ old('next_service_odometer_km') }}"></div>
    <div class="field field--wide"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div>
    <div class="form-actions"><button class="btn">Register vehicle</button></div>
</form></div></details>
@endif

<section class="card table-card"><div class="section-heading"><div><p class="card-label">Registered company vehicles</p><h3>Vehicle register</h3></div></div><table class="exec-table"><thead><tr><th>Registration</th><th>Vehicle</th><th>Capacity</th><th>Default driver</th><th>Current km</th><th>Next service</th><th>Status</th></tr></thead><tbody>
@forelse($vehicles as $vehicle)<tr><td><strong>{{ $vehicle->registration_number }}</strong></td><td>{{ $vehicle->make_model ?: 'Not recorded' }}<small>{{ $vehicle->vehicle_type ?: 'Type not recorded' }}</small></td><td>{{ $vehicle->capacity === null ? 'Not recorded' : number_format($vehicle->capacity, 2) }}</td><td>{{ $vehicle->default_driver_name ?: 'Not assigned' }}</td><td>{{ $vehicle->current_odometer_km === null ? 'Not recorded' : number_format($vehicle->current_odometer_km, 1).' km' }}</td><td>{{ $vehicle->next_service_odometer_km === null ? 'Not set' : number_format($vehicle->next_service_odometer_km, 1).' km' }}</td><td><span class="status-pill">{{ $vehicle->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
@empty<tr><td colspan="7">No company vehicles have been registered yet.</td></tr>@endforelse
</tbody></table></section>
@endsection
