@extends('layouts.app')
@section('title', 'HR Data Entry')
@section('content')
<div class="page-head"><p class="eyebrow">Human Resources</p><h2>HR Data Entry</h2><p>Start the correct HR record here. Each entry is kept in its proper register for reporting and audit.</p></div>
<div class="summary-grid" aria-label="HR data-entry choices">
    @if($canManage)<a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('employees.create') }}"><span>Staff records</span><strong>Add employee</strong><small>Create the worker profile, contract and access details.</small></a>@endif
    <a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.accommodation.index') }}"><span>Accommodation</span><strong>Register hostel and rooms</strong><small>Add buildings, bed spaces, worker allocations and issued items.</small></a>
    <a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.records.index', ['module' => 'attendance']) }}"><span>Daily people records</span><strong>Record attendance</strong><small>Capture staff attendance and attendance-related observations.</small></a>
    <a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.records.index', ['module' => 'welfare']) }}"><span>Staff welfare</span><strong>Record welfare support</strong><small>Record welfare assistance, medical support or other staff support.</small></a>
    <a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.welfare-budget.index') }}"><span>Weekly planning</span><strong>Prepare welfare budget</strong><small>Record planned welfare needs for the week.</small></a>
    @if($canRecordFleet)<a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.vehicles.index') }}"><span>Fleet register</span><strong>Register company vehicle</strong><small>Register the vehicle before recording its journeys.</small></a>@endif
    @if($canRecordFleet)<a class="summary-card" style="color:inherit;text-decoration:none" href="{{ route('hr.fleet.index') }}"><span>Fleet movement</span><strong>Record vehicle journey</strong><small>Select a registered company vehicle and enter the journey details.</small></a>@endif
</div>
@endsection
