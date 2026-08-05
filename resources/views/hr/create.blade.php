@extends('layouts.app')

@section('title', 'Add employee')

@section('content')

    <div class="page-head">
        <h2>Add employee</h2>
        <p>Registering someone does not give them a login. That is a separate step.</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('employees.store') }}">
        @csrf

        @include('hr._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Save employee</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('employees.index') }}">Cancel</a>
        </div>
    </form>

@endsection
