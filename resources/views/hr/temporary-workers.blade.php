@extends('layouts.app')
@section('title', 'Temporary Workers')
@section('content')
@php
    $closeBtn = '<button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest(\'dialog\').close()">&times;</button>';
@endphp
<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('register-temporary').showModal()">+ Register temporary worker</button></div>
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<div class="card table-wrap">
<table class="exec-table">
    <thead><tr><th>Name</th><th>Role</th><th>Project</th><th>Room</th><th>Arrived</th><th>Expected to leave</th><th></th></tr></thead>
    <tbody>
    @forelse($people as $p)
        <tr>
            <td><strong>{{ $p->fullName() }}</strong><small class="table-sub">{{ $p->staff_no }} · {{ $p->phone }}</small></td>
            <td>{{ $p->job_title ?: 'Temporary' }}</td>
            <td>{{ $p->project ?: '—' }}</td>
            <td>
                @if($p->stay){{ $p->stay->hostel }} · Room {{ $p->stay->room_number }}
                @elseif($rooms->isNotEmpty())
                    <form method="POST" action="{{ route('hr.temporary.room', $p) }}" class="inline-assign">@csrf<select name="staff_hostel_room_id" required aria-label="Room for {{ $p->fullName() }}"><option value="">No room yet — assign</option>@foreach($rooms as $r)<option value="{{ $r->id }}">{{ $r->hostel }} · Room {{ $r->room_number }}</option>@endforeach</select><button class="btn btn--small">Assign</button></form>
                @else<span class="muted">No room yet (no free beds)</span>@endif
            </td>
            <td>{{ $p->hire_date?->format('d M Y') ?? '—' }}</td>
            <td>{{ $p->expected_end_date ? \Carbon\Carbon::parse($p->expected_end_date)->format('d M Y') : '—' }}</td>
            <td><form method="POST" action="{{ route('hr.temporary.checkout', $p) }}" data-confirm="{{ $p->fullName() }} has left the farm? This checks them out of their room.">@csrf<button class="btn btn--quiet btn--small">Check out</button></form></td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No temporary workers at the farm.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<dialog id="register-temporary" class="page-dialog" aria-label="Register temporary worker">{!! $closeBtn !!}<h3>Register temporary worker</h3>
<form method="POST" action="{{ route('hr.temporary.store') }}">@csrf<input type="hidden" name="_dialog" value="register-temporary">
    <div class="form-grid">
        <div class="field"><label for="tw-first">First name</label><input id="tw-first" name="first_name" value="{{ old('first_name') }}" required maxlength="80"></div>
        <div class="field"><label for="tw-last">Surname</label><input id="tw-last" name="last_name" value="{{ old('last_name') }}" required maxlength="80"></div>
        <div class="field"><label for="tw-phone">Phone</label><input id="tw-phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="30"></div>
        <div class="field"><label for="tw-nin">National ID <small>(optional)</small></label><input id="tw-nin" name="national_id" value="{{ old('national_id') }}" maxlength="20"></div>
        <div class="field"><label for="tw-role">Role</label><select id="tw-role" name="role" required>@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>@endforeach</select></div>
        <div class="field"><label for="tw-room">Room <small>(optional)</small></label><select id="tw-room" name="staff_hostel_room_id"><option value="">No room</option>@foreach($rooms as $r)<option value="{{ $r->id }}" @selected(old('staff_hostel_room_id') == $r->id)>{{ $r->hostel }} · Room {{ $r->room_number }}</option>@endforeach</select></div>
        <div class="field"><label for="tw-from">Arrived</label><input id="tw-from" type="date" name="arrived_on" value="{{ old('arrived_on', now()->toDateString()) }}" required></div>
        <div class="field"><label for="tw-to">Expected to leave</label><input id="tw-to" type="date" name="expected_end_date" value="{{ old('expected_end_date') }}"></div>
    </div>
    <p class="field-hint">Temporary workers get a staff number, have no login and are not paid through payroll.</p>
    <div class="form-actions"><button class="btn">Register</button></div>
</form></dialog>
<script>(() => { const o = @json(old('_dialog')); const d = o && document.getElementById(o); if (d && d.showModal) d.showModal(); })();</script>
@endsection
