@extends('layouts.app')

@section('title', $feed->name)

@section('content')

<div class="page-head">
    <h2>{{ $feed->name }}</h2>
    <p>{{ $feed->consumptions()->count() }} records use this feed</p>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">Check the fields marked below.</div>
@endif

<form method="POST" action="{{ route('feed-types.update', $feed) }}">
    @csrf
    @method('PUT')
    @include('poultry.feed-types._form')

    <div class="form-actions">
        <button type="submit" class="btn btn--inline">Save changes</button>
        <a class="btn btn--quiet btn--inline" href="{{ route('feed-types.index') }}">Cancel</a>
    </div>
</form>

<div class="card" style="margin-top:16px;">
    <p class="card-label">{{ $feed->is_active ? 'Stop using this feed' : 'Use this feed again' }}</p>
    <p class="muted u-m0-b12">
        @if ($feed->is_active)
            Hiding it removes it from the worker's screen. Records already
            entered keep it — a feed is never deleted, or months of history
            would lose its name.
        @else
            It will appear on the worker's screen again.
        @endif
    </p>

    <form method="POST" action="{{ route('feed-types.toggle', $feed) }}">
        @csrf
        <button type="submit" class="btn btn--quiet btn--inline">
            {{ $feed->is_active ? 'Hide it' : 'Make it available' }}
        </button>
    </form>
</div>

@endsection
