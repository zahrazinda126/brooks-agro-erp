@extends('layouts.app')

@section('title', 'Add a breed')

@section('content')

    <div class="page-head">
        <h2>Add a breed</h2>
        <p>Once added, it can be chosen on any batch — this time and every time after.</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('breeds.store') }}">
        @csrf
        @include('poultry.breeds._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Add breed</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('breeds.index') }}">Cancel</a>
        </div>
    </form>

@endsection
