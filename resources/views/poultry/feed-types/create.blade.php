@extends('layouts.app')

@section('title', 'Add a feed')

@section('content')

<div class="page-head">
    <h2>Add a feed</h2>
    <p>Once added, workers can record it straight away.</p>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">
        Check the fields marked below.
    </div>
@endif

<form method="POST" action="{{ route('feed-types.store') }}">
    @csrf
    @include('poultry.feed-types._form')

    <div class="form-actions">
        <button type="submit" class="btn btn--inline">Add feed</button>
        <a class="btn btn--quiet btn--inline" href="{{ route('feed-types.index') }}">Cancel</a>
    </div>
</form>

@endsection
