@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="page-head"><h2>{{ $title }}</h2><p>Choose what you want to see, then pick the unit with the Unit filter.</p></div>
@include('partials.monitor-group', ['group' => $group, 'current' => null])
@endsection
