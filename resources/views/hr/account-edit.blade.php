@extends('layouts.app')

@section('title', 'Edit Worker Login')

@section('content')
<div class="page-head"><span class="finance-panel-kicker">Worker account</span><h2>Edit {{ $employee->fullName() }}'s login</h2><p>Change how this worker signs in and which work areas they can access.</p></div>
<form method="POST" action="{{ route('employees.account.update', $employee) }}">
    @csrf @method('PUT')
    <section class="form-card">
        <div class="form-grid">
            <div class="field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username', $account->username) }}" required autocapitalize="none"><small class="field-hint">Staff number such as BAF 001, or lowercase letters, numbers, dots, dashes or underscores.</small></div>
            <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" value="{{ old('email', $account->email) }}"></div>
            <div class="field"><label for="role_id">Access role</label><select id="role_id" name="role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((int)old('role_id',$account->role_id)===$role->id)>{{ $role->name }}</option>@endforeach</select><small class="field-hint">Only roles below your own authority can be assigned.</small></div>
            <div class="field"><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">Use employment department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int)old('department_id',$account->department_id)===$department->id)>{{ $department->name }}</option>@endforeach</select></div>
        </div>
    </section>
    <div class="form-actions"><button class="btn">Save login details</button><a class="btn btn--quiet" href="{{ route('employees.show',$employee) }}">Cancel</a></div>
</form>
@endsection
