@extends('layouts.app')

@section('title', 'Performance Reviews')

@section('content')
    <div class="page-head">
        <h2>Automatic worker performance</h2>
        <p>{{ $period }} · 70% attendance and 30% recorded work activity. HR can view this score but cannot edit it.</p>
    </div>

    <div class="data-list">
        <div class="data-head"><span>Staff no.</span><span>Employee</span><span>Present</span><span>Activity</span><span>Score</span></div>
        @forelse ($performance as $row)
            <div class="data-row">
                <span class="data-cell mono" data-label="Staff no.">{{ $row['employee']->staff_no }}</span>
                <span class="data-cell data-cell--strong" data-label="Employee">{{ $row['employee']->fullName() }}<em class="data-sub">{{ $row['employee']->department?->name }}</em></span>
                <span class="data-cell" data-label="Present">{{ $row['present_days'] }} of {{ $expectedDays }} work days</span>
                <span class="data-cell" data-label="Activity">{{ $row['activity_days'] }} day(s) with records</span>
                <span class="data-cell" data-label="Score"><strong>{{ $row['score'] }}%</strong></span>
            </div>
        @empty
            <div class="card empty"><p class="muted" style="margin:0;">No active employees are on the register.</p></div>
        @endforelse
    </div>
@endsection
