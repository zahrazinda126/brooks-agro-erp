@extends('layouts.app')
@section('title', 'On the Farm Now')
@section('content')
<div class="page-head"><h2>On the farm now</h2><p>Everyone checked into farm accommodation today, staff and builders alike. Use the headcount for kitchen and welfare planning.</p></div>

<nav class="view-switch" aria-label="Headcount">
    <span aria-current="page">Total <span class="view-switch__count">{{ $people->count() }}</span></span>
    @foreach($byType as $type => $count)<span>{{ $type }} <span class="view-switch__count">{{ $count }}</span></span>@endforeach
</nav>

<div class="card table-wrap">
<table class="exec-table">
    <thead><tr><th>Name</th><th>Role</th><th>Where they sleep</th><th>Arrived</th><th>Expected to leave</th><th>Pay</th></tr></thead>
    <tbody>
    @forelse($people as $p)
        @php $leaves = $p->expected_checkout_on ?: $p->expected_end_date; @endphp
        <tr>
            <td><strong>{{ $p->first_name }} {{ $p->last_name }}</strong><small class="table-sub">{{ $p->staff_no }}</small></td>
            <td>{{ $p->project ? 'Builder · '.$p->project : ($p->job_title ?: ucfirst((string) $p->employment_type)) }}</td>
            <td>{{ $p->hostel }} · Room {{ $p->room_number }}</td>
            <td>{{ \Carbon\Carbon::parse($p->checked_in_on)->format('d M Y') }}</td>
            <td>{{ $leaves ? \Carbon\Carbon::parse($leaves)->format('d M Y') : '—' }}</td>
            <td>{{ $p->on_payroll ? 'Payroll' : 'Paid by Finance' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Nobody is checked into farm accommodation. HR gives people a room under Staff Accommodation.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
