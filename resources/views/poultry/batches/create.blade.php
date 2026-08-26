@extends('layouts.app')

@section('title', 'New batch')

@section('content')

    <div class="page-head">
        <h2>New batch</h2>
        <p>Record a delivery of birds.</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('batches.store') }}">
        @csrf

        @include('poultry.batches._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Open batch</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('batches.index') }}">Cancel</a>
        </div>
    </form>

@endsection

