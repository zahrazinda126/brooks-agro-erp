@extends('layouts.app')
@section('title', "Director's Meetings")
@section('content')
{{-- The Director's calendar: upcoming meetings and scheduling. --}}
@php
    $pa = $board;
@endphp
<section class="pa-dashboard pa-single">
<section class="exec-panel"><div class="section-heading"><div><p class="card-label">Director's calendar</p><h3>Upcoming meetings</h3></div></div>@forelse($pa['upcomingMeetings'] as $meeting)<div class="pa-meeting"><time><strong>{{ $meeting->starts_at->format('d') }}</strong>{{ $meeting->starts_at->format('M') }}</time><div><strong>{{ $meeting->title }}</strong><small>{{ $meeting->starts_at->format('H:i') }} · {{ $meeting->venue?:'Venue pending' }}</small></div></div>@empty<p class="muted">No upcoming meetings.</p>@endforelse<details class="disclosure"><summary>Schedule meeting</summary><div class="disclosure__body"><form method="POST" action="{{ route('executive-follow-up.meetings.store') }}">@csrf<div class="field"><label>Group / person</label><input name="host" placeholder="e.g. Mr Makumbi"></div><div class="field"><label>Event</label><input name="title" required placeholder="e.g. Wedding, Appointment at Masengere"></div><div class="field"><label>Reason</label><select name="reason"><option value="">Choose</option>@foreach(['Invitation','Appointment','Visit','Meeting','Other'] as $why)<option>{{ $why }}</option>@endforeach</select></div><div class="field"><label>Location</label><input name="venue"></div><div class="field"><label>Date and time</label><input type="datetime-local" name="starts_at" required></div><div class="field"><label>Ends</label><input type="datetime-local" name="ends_at"></div><div class="field"><label>Contact</label><input name="contact" inputmode="tel" placeholder="07…"></div><div class="field"><label>Participants</label><textarea name="participants"></textarea></div><div class="field"><label>Agenda</label><textarea name="agenda"></textarea></div><button class="btn">Schedule</button></form></div></details></section>
</section>
@endsection
