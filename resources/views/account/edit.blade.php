@extends('layouts.app')

@section('title', 'My Account')

@section('content')
<div class="account-page">
    <header class="account-hero">
        <div class="account-avatar">{{ strtoupper(substr($account->firstName(), 0, 1).substr($account->displayName(), -1)) }}</div>
        <div>
            <span class="finance-panel-kicker">Personal account</span>
            <h2>{{ $account->displayName() }}</h2>
            <p>Update the details used to identify you when you sign in and approve company work.</p>
        </div>
    </header>

    <div class="account-layout">
        <section class="form-card account-form-card">
            <div class="section-heading"><div><p class="card-label">Account details</p><h3>Your profile</h3></div></div>
            <form method="POST" action="{{ route('account.update') }}">
                @csrf @method('PUT')
                <div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $account->name) }}" required autocomplete="name"><small>This name appears on approvals, reports and the dashboard.</small></div>
                <div class="form-grid">
                    @if($account->employee_id)<div class="field"><label>Username</label><input value="{{ $account->username }}" readonly aria-readonly="true"><small>The login username is the staff number. It changes automatically if HR changes the staff number.</small></div>@else<div class="field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username', $account->username) }}" required autocomplete="username"></div>@endif
                    <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" value="{{ old('email', $account->email) }}" autocomplete="email" placeholder="name@brooksagro.ug"></div>
                </div>
                <div class="account-role"><span>Access level</span><strong>{{ $account->roleSummary() }}</strong><small>Roles and permissions are controlled separately to protect company records.</small></div>
                <div class="form-actions"><button class="btn">Save account details</button><a class="btn btn--quiet" href="{{ route('dashboard') }}">Back to dashboard</a></div>
            </form>
        </section>
        <aside class="account-security">
            <span class="account-security__icon">&#128274;</span>
            <p class="card-label">Security</p><h3>Password & sign-in</h3>
            <p>Your password is kept on a separate secure screen. Changing your username does not change your password.</p>
            <a class="btn btn--quiet btn--inline" href="{{ route('password.change') }}">Change password</a>
            <dl><div><dt>Last sign-in</dt><dd>{{ $account->last_login_at?->format('d M Y, H:i') ?? 'Not recorded' }}</dd></div><div><dt>Account status</dt><dd>{{ $account->is_active ? 'Active' : 'Inactive' }}</dd></div></dl>
        </aside>
    </div>
</div>
@endsection
