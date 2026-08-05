@extends('layouts.app')

@section('title', $breed->name)

@section('content')

    <div class="page-head">
        <h2>{{ $breed->name }}</h2>
        <p>Changing these figures affects future batches. Batches already open keep the dates recorded against them.</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('breeds.update', $breed) }}">
        @csrf
        @method('PUT')
        @include('poultry.breeds._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Save changes</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('breeds.index') }}">Cancel</a>
        </div>
    </form>

@endsection
