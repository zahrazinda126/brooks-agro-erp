@extends('layouts.app')
@section('title', $view === 'staff' ? 'Staff Accounts' : 'Accounts')
@section('content')
<div class="page-head"><p class="eyebrow">Finance</p><h2>{{ $view === 'staff' ? 'Staff Accounts' : 'Accounts' }}</h2></div>
@if($view === 'staff')

    <form method="GET" class="card sales-report__filter"><input type="hidden" name="view" value="staff"><div class="field"><label>Find staff member</label><input name="search" value="{{ request('search') }}" placeholder="Name or staff number"></div><button class="btn">Search</button></form>
    <section class="exec-panel table-wrap"><table><thead><tr><th>Staff number</th><th>Staff member</th><th>Department</th><th>Account balance</th><th></th></tr></thead><tbody>@forelse($staff as $employee)<tr><td>{{ $employee->staff_no }}</td><td><strong>{{ $employee->fullName() }}</strong></td><td>{{ $employee->department?->name ?: '—' }}</td><td><strong>@money($employee->wallet_balance, 2)</strong></td><td><a class="btn btn--small" href="{{ route('payroll.salary-accounts.show', $employee) }}">Open account</a></td></tr>@empty<tr><td colspan="5">No staff accounts found.</td></tr>@endforelse</tbody></table></section>
@else
    <div class="summary-grid accounts-directory">
        <a class="summary-card" href="{{ route('owner.customers', ['view'=>'accounts']) }}"><span>Accounts</span><strong>Customer Accounts</strong><small>Sales, payments, balances and prepayments</small></a>
        <a class="summary-card" href="{{ route('finance.transactions.index') }}"><span>Accounts</span><strong>Bank & Farm Accounts</strong><small>Cash, bank and farm-account transactions</small></a>
        <a class="summary-card" href="{{ route('finance.accounts.index', ['view'=>'staff']) }}"><span>Accounts</span><strong>Staff Accounts</strong><small>Salary credits, withdrawals and balances</small></a>
    </div>
@endif
@pushOnce('styles')<style>.accounts-directory a{color:inherit;text-decoration:none}.accounts-directory a:hover{transform:translateY(-2px)}</style>@endPushOnce
@endsection
