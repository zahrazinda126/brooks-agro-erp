@extends('layouts.app')

@section('title', 'Issue a login')

@section('content')

    <div class="page-head">
        <h2>Issue a login</h2>
        <p>For {{ $employee->fullName() }} · <span class="mono">{{ $employee->staff_no }}</span></p>
    </div>

    <div class="notice">
        A temporary password will be generated and shown once on the next screen.
        {{ $employee->first_name }} must change it at first sign-in.
    </div>

    <form method="POST" action="{{ route('employees.account.store', $employee) }}">
        @csrf

        <div class="card">
            <div class="form-grid">
                <div class="field"><label>Username</label><input value="{{ $employee->staff_no ?: 'Add a staff number first' }}" readonly aria-readonly="true"><small>The login username is the staff number. It changes automatically if HR changes the staff number.</small></div>

                <div class="field @error('email') field--error @enderror">
                    <label for="email">Email <span class="req">required</span></label>
                    <input id="email" name="email" type="email" maxlength="190" autocomplete="email"
                           value="{{ old('email', $employee->email) }}" required>
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field @error('role_id') field--error @enderror">
                    <label for="role_id">Access role <span class="req">required</span></label>
                    <select id="role_id" name="role_id" required>
                        <option value="">Choose a role</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}"
                                @selected((int) old('role_id', $employee->role_id ?? 0) === $r->id)>
                                {{ $r->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-hint">Decides which screens they can open. You can only assign roles below your own level.</p>
                </div>

                <div class="field">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">Same as their employment record</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}"
                                @selected((int) old('department_id', $employee->department_id ?? 0) === $d->id)>
                                {{ $d->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="field-hint">Access is granted by role and by department together.</p>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--inline">Create login</button>
            <a class="btn btn--quiet btn--inline" href="{{ route('employees.show', $employee) }}">Cancel</a>
        </div>
    </form>

@endsection
