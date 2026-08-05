@extends('layouts.app')

@section('title', 'Edit ' . $employee->fullName())

@section('content')

    <div class="page-head">
        <h2>{{ $employee->fullName() }}</h2>
        <p class="mono">{{ $employee->staff_no }}</p>
    </div>

    @if ($errors->any())
        <div class="notice notice--alarm">
            Check the fields marked below — {{ $errors->count() }} {{ Str::plural('item', $errors->count()) }} need attention.
        </div>
    @endif

    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf
        @method('PUT')

        @include('hr._form')

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Save changes</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('employees.show', $employee) }}">Cancel</a>
        </div>
    </form>

@endsection
