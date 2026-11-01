@extends('layouts.app')

@section('title', 'KPI Dashboard')

@section('content')
    <div class="page-head">
        <h2>HR KPI Dashboard</h2>
        <p>Current month: {{ $period }}</p>
    </div>

    <div class="grid">
        <div class="card"><p class="card-label">Attendance coverage</p><span class="figure">{{ $attendanceRate }}%</span><span class="figure-unit">{{ $metrics['attendance'] }} of {{ $metrics['active_staff'] }} active employees</span></div>
        <div class="card"><p class="card-label">Training records</p><span class="figure">{{ $metrics['training'] }}</span><span class="figure-unit">completed this month</span></div>
        <div class="card"><p class="card-label">Contracts ending soon</p><span class="figure {{ $metrics['contracts_expiring'] ? 'figure--alarm' : '' }}">{{ $metrics['contracts_expiring'] }}</span><span class="figure-unit">within 30 days</span></div>
        <div class="card"><p class="card-label">Pending deductions</p><span class="figure {{ $metrics['pending_penalties'] ? 'figure--alarm' : '' }}">{{ $metrics['pending_penalties'] }}</span><span class="figure-unit">awaiting approval</span></div>
        <div class="card"><p class="card-label">Welfare support</p><span class="figure">@money($metrics['welfare_amount'])</span><span class="figure-unit">provided this month</span></div>
    </div>
@endsection
