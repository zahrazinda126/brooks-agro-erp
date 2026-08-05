@extends('layouts.app')

@section('title', 'Edit ' . $batch->code)

@section('content')

    <div class="page-head">
        <h2>{{ $batch->label() }}</h2>
        <p>Received {{ $batch->received_date->format('j M Y') }}</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('batches.update', $batch) }}">
        @csrf
        @method('PUT')

        @include('poultry.batches._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Save changes</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('batches.show', $batch) }}">Cancel</a>
        </div>
    </form>

@endsection
