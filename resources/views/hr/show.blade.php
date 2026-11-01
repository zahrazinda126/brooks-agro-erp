@extends('layouts.app')

@section('title', $employee->fullName())

@section('content')

    {{-- Shown once, immediately after an account is created or reset.
         The password is never stored in readable form, so this screen is
         the only chance to write it down. --}}
    @if (session('credentials'))
        @php
            $cred = session('credentials');
        @endphp
        <div class="credentials">
            <p class="card-label">Give these to {{ $employee->first_name }} now</p>
            <p class="muted u-m0-b14">
                This password is shown once and cannot be recovered. If it is lost, reset it.
            </p>

            <div class="credentials-pair">
                <div>
                    <span class="figure-unit">Username</span>
                    <span class="cred-value mono">{{ $cred['username'] }}</span>
                </div>
                <div>
                    <span class="figure-unit">Temporary password</span>
                    <span class="cred-value mono">{{ $cred['password'] }}</span>
                </div>
            </div>

            <p class="muted" style="margin:14px 0 0;font-size:.875rem;">
                They will be asked to choose their own password when they first sign in.
            </p>
        </div>
    @endif

    <div class="page-head page-head--split">
        <div>
            <h2>{{ $employee->fullName() }}</h2>
            <p>
                <span class="mono">{{ $employee->staff_no }}</span>
                · {{ $employee->job_title ?: ucfirst($employee->employment_type) }}
                @if ($employee->status !== 'active')
                    <span class="pill pill--muted">{{ $employee->status === 'terminated' ? 'Left' : 'Suspended' }}</span>
                @endif
            </p>
        </div>

        @if ($user->canDo('hr', 'edit'))
            <a class="btn btn--quiet btn--inline" href="{{ route('employees.edit', $employee) }}">Edit staff details</a>
        @endif
    </div>

    <div class="detail-grid">

        <div class="card">
            <p class="card-label">Contact</p>
            <dl class="facts">
                <dt>Phone</dt><dd class="mono">{{ $employee->phone ?: '—' }}</dd>
                <dt>Other phone</dt><dd class="mono">{{ $employee->alt_phone ?: '—' }}</dd>
                <dt>Email</dt><dd>{{ $employee->email ?: '—' }}</dd>
                <dt>Address</dt><dd>{{ $employee->address ?: '—' }}</dd>
                <dt>Next of kin</dt>
                <dd>
                    {{ $employee->next_of_kin_name ?: '—' }}
                    @if ($employee->next_of_kin_phone)
                        <br><span class="mono">{{ $employee->next_of_kin_phone }}</span>
                    @endif
                </dd>
            </dl>
        </div>

        <div class="card">
            <p class="card-label">Employment</p>
            <dl class="facts">
                <dt>Department</dt><dd>{{ $employee->department?->name ?? '—' }}</dd>
                <dt>Job role</dt><dd>{{ $employee->role?->name ?? '—' }}</dd>
                <dt>Type</dt><dd>{{ ucfirst($employee->employment_type) }}</dd>
                <dt>Hired</dt><dd>{{ $employee->hire_date?->format('j M Y') ?? '—' }}</dd>
                @if ($employee->end_date)
                    <dt>Left</dt><dd>{{ $employee->end_date->format('j M Y') }}</dd>
                @endif
                <dt>National ID</dt><dd class="mono">{{ $employee->national_id ?: '—' }}</dd>
                <dt>ID picture</dt><dd>@if($employee->national_id_photo)<a href="{{ route('employees.national-id-photo', $employee) }}" target="_blank" rel="noopener">View ID picture</a>@else Not uploaded @endif</dd>
            </dl>
        </div>

        <div class="card">
            <p class="card-label">Pay</p>
            <span class="figure">{{ number_format((float) $employee->base_salary) }}</span>
            <span class="figure-unit">UGX · {{ $employee->pay_frequency }}</span>

            <dl class="facts" style="margin-top:14px;">
                <dt>Method</dt>
                <dd>{{ $employee->payment_method ? ucfirst(str_replace('_', ' ', $employee->payment_method)) : '—' }}</dd>

                @if ($employee->payment_method === 'bank')
                    <dt>Bank</dt><dd>{{ $employee->bank_name ?: '—' }}</dd>
                    <dt>Account</dt><dd class="mono">{{ $employee->bank_account ?: '—' }}</dd>
                @elseif ($employee->payment_method === 'mobile_money')
                    <dt>Number</dt><dd class="mono">{{ $employee->mobile_money_number ?: '—' }}</dd>
                @endif
            </dl>
            @if ($user->canAccess('payroll'))
                <a class="btn btn--quiet btn--inline" href="{{ route('payroll.salary-accounts.show', $employee) }}">Open salary account</a>
            @endif
        </div>

        {{-- The login. Separate from everything above on purpose. --}}
        <div class="card">
            <p class="card-label">System login</p>

            @if ($employee->user)
                <dl class="facts">
                    <dt>Username</dt><dd class="mono">{{ $employee->user->username }}</dd>
                    <dt>Access role</dt><dd>{{ $employee->user->role?->name ?? '—' }}</dd>
                    <dt>Status</dt>
                    <dd>
                        @if (! $employee->user->is_active)
                            <span class="pill pill--muted">Disabled</span>
                        @elseif ($employee->user->isLocked())
                            <span class="pill pill--alarm">Locked</span>
                        @elseif ($employee->user->must_change_password)
                            <span class="pill">Awaiting first sign-in</span>
                        @else
                            <span class="pill">Active</span>
                        @endif
                    </dd>
                    <dt>Last signed in</dt>
                    <dd>{{ $employee->user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                </dl>

                @if ($user->canDo('hr', 'edit') && $employee->user->id !== $user->id)
                    <div class="button-row">
                        <a class="btn btn--quiet btn--inline" href="{{ route('employees.account.edit', $employee) }}">Edit login</a>
                        <form method="POST" action="{{ route('employees.account.reset', $employee) }}">
                            @csrf
                            <button type="submit" class="btn btn--quiet btn--inline"
                                    onclick="return confirm('Reset the password for {{ $employee->user->username }}? The current password stops working immediately.')">
                                Reset password
                            </button>
                        </form>

                        <form method="POST" action="{{ route('employees.account.toggle', $employee) }}">
                            @csrf
                            <button type="submit" class="btn btn--quiet btn--inline"
                                    onclick="return confirm('{{ $employee->user->is_active ? 'Disable' : 'Enable' }} this login?')">
                                {{ $employee->user->is_active ? 'Disable login' : 'Enable login' }}
                            </button>
                        </form>
                    </div>
                @endif
            @else
                <p class="muted u-m0-b14">
                    No login. {{ $employee->first_name }} cannot sign in — which is normal for
                    staff who never use the system.
                </p>

                @if ($user->canDo('hr', 'edit') && $employee->status === 'active')
                    <a class="btn btn--inline" href="{{ route('employees.account.create', $employee) }}">
                        Issue a login
                    </a>
                @elseif ($employee->status !== 'active')
                    <p class="muted" style="margin:0;font-size:.875rem;">
                        Logins can only be issued to active staff.
                    </p>
                @endif
            @endif
        </div>
    </div>

    @if ($employee->notes)
        <div class="card">
            <p class="card-label">Notes</p>
            <p class="u-m0 u-pre-wrap">{{ $employee->notes }}</p>
        </div>
    @endif

    <p class="u-mt-18">
        <a class="btn btn--quiet btn--inline" href="{{ route('employees.index') }}">Back to register</a>
    </p>

    @if($user->canDo('hr', 'delete') && $employee->user?->id !== $user->id)
        <section class="danger-zone"><div><strong>Remove worker</strong><p>Disables their login and removes them from the active register. Payroll, approvals and audit history are retained.</p></div><form method="POST" action="{{ route('employees.destroy',$employee) }}">@csrf @method('DELETE')<button class="btn btn--danger" onclick="return confirm('Remove {{ $employee->fullName() }} and disable their login? Historical records will be retained.')">Remove worker</button></form></section>
    @endif

@endsection
