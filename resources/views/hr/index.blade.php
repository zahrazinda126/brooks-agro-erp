@extends('layouts.app')

@section('title', 'Human Resources')

@section('content')

    <div class="page-head page-head--split">
        <div>
            <h2>Staff register</h2>
            <p>{{ $employees->total() }} {{ Str::plural('person', $employees->total()) }} listed</p>
        </div>

        @if ($user->canDo('hr', 'create'))
            <a class="btn btn--inline" href="{{ route('employees.create') }}">Add employee</a>
        @endif
    </div>

    <form method="GET" action="{{ route('employees.index') }}" class="card filters">
        <div class="field">
            <label for="q">Search</label>
            <input id="q" name="q" type="search" value="{{ $filters['q'] }}"
                   placeholder="Name, staff number or phone">
        </div>

        <div class="field">
            <label for="department">Department</label>
            <select id="department" name="department">
                <option value="">All departments</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected((int) $filters['department'] === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" @selected($filters['status'] === 'active')>Active ({{ $counts['active'] }})</option>
                <option value="suspended" @selected($filters['status'] === 'suspended')>Suspended ({{ $counts['suspended'] }})</option>
                <option value="terminated" @selected($filters['status'] === 'terminated')>Left ({{ $counts['terminated'] }})</option>
                <option value="all" @selected($filters['status'] === 'all')>Everyone</option>
            </select>
        </div>
        <div class="field"><label for="from">Hired from</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}"></div>
        <div class="field"><label for="to">Hired to</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}"></div>

        <div class="field field--actions">
            <button type="submit" class="btn btn--inline">Apply</button>
            @if ($filters['q'] || $filters['department'] || $filters['status'] !== 'active' || $filters['from'] || $filters['to'])
                <a class="btn btn--quiet btn--inline" href="{{ route('employees.index') }}">Clear</a>
            @endif
        </div>
    </form>

    @if ($employees->isEmpty())
        <div class="card empty">
            <p class="card-label">Nobody here</p>
            <p class="muted" style="margin:0 0 14px;">
                @if ($filters['q'] || $filters['department'])
                    No one matches those filters.
                @else
                    The register is empty. Add your first employee to begin.
                @endif
            </p>
            @if ($user->canDo('hr', 'create'))
                <a class="btn btn--inline" href="{{ route('employees.create') }}">Add employee</a>
            @endif
        </div>
    @else
        <div class="data-list">
            <div class="data-head">
                <span>Staff no</span>
                <span>Name</span>
                <span>Department</span>
                <span>Phone</span>
                <span>Login</span>
            </div>

            @foreach ($employees as $employee)
                <a class="data-row" href="{{ route('employees.show', $employee) }}">
                    <span class="data-cell" data-label="Staff no">
                        <span class="mono">{{ $employee->staff_no }}</span>
                    </span>

                    <span class="data-cell data-cell--strong" data-label="Name">
                        {{ $employee->fullName() }}
                        @if ($employee->status !== 'active')
                            <span class="pill pill--muted">{{ $employee->status === 'terminated' ? 'Left' : 'Suspended' }}</span>
                        @endif
                        <em class="data-sub">{{ $employee->job_title ?: ucfirst($employee->employment_type) }}</em>
                    </span>

                    <span class="data-cell" data-label="Department">
                        {{ $employee->department?->name ?? '—' }}
                    </span>

                    <span class="data-cell" data-label="Phone">
                        <span class="mono">{{ $employee->phone ?: '—' }}</span>
                    </span>

                    <span class="data-cell" data-label="Login">
                        @if ($employee->user)
                            <span class="pill {{ $employee->user->is_active ? '' : 'pill--muted' }}">
                                {{ $employee->user->is_active ? $employee->user->username : 'disabled' }}
                            </span>
                        @else
                            <span class="muted">none</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        <div class="pager">{{ $employees->links() }}</div>
    @endif

@endsection
