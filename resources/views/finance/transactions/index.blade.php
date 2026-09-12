@extends('layouts.app')
@section('title','Finance Daily Workbook')
@section('content')
<div class="page-head page-head--split"><div><h2>{{ $selectedAccount ? $selectedAccount.' Account' : 'Finance Daily Workbook' }}</h2><p>{{ $selectedAccount ? 'Complete transaction history and running balance for this account.' : 'Daily entries recorded by Finance are shown here. Sales entries remain under Approved approvals.' }}</p></div><div class="form-actions">@if($selectedAccount)<a class="btn btn--quiet" href="{{ route('finance.transactions.index', [], false) }}">&larr; All accounts</a>@endif @if($user->canDo('transactions','create'))<a class="btn btn--inline" href="{{ route('finance.transactions.create') }}">New entry</a>@endif</div></div>
<form method="GET" class="card finance-register-filters">
    @if($selectedAccount)<input type="hidden" name="account" value="{{ $selectedAccount }}">@endif
    <div class="finance-filter-primary">
        <div class="field finance-filter-search"><label>Search</label><input name="search" value="{{ request('search') }}" placeholder="Item, account or description"></div>
        <div class="field"><label>Money flow</label><select name="type"><option value="">All entries</option><option value="money_paid" @selected(request('type')==='money_paid')>Money spent</option><option value="money_received" @selected(request('type')==='money_received')>Money received</option><option value="transfer" @selected(request('type')==='transfer')>Transfers</option></select></div>
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}" required></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to }}" required></div>
        <button class="btn">Apply</button><a class="btn btn--quiet" href="{{ route('finance.transactions.index', $selectedAccount ? ['account'=>$selectedAccount, 'from'=>now()->toDateString(), 'to'=>now()->toDateString()] : [], false) }}">Today</a>
    </div>
    <details class="finance-more-filters" @if(request('item')||request('department')) open @endif><summary>More filters</summary><div>
        <div class="field"><label>Specific item</label><select name="item"><option value="">All items</option>@foreach($expenseItems as $expenseItem)<option value="{{ $expenseItem }}" @selected(request('item')===$expenseItem)>{{ $expenseItem }}</option>@endforeach</select></div>
        <div class="field"><label>Department</label><select name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)request('department')===(string)$department->id)>{{ $department->name }}</option>@endforeach</select></div>
    </div></details>
</form>
@if(!$selectedAccount)
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><p class="card-label">Account balances</p><h3>Where the money is held</h3></div><small>Balances as at {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</small></div>
    <table class="exec-table"><thead><tr><th>Account</th><th>Balance</th></tr></thead><tbody>
        @forelse($accountBalances as $account => $balance)
            <tr><td><a href="{{ route('finance.transactions.index', ['account'=>$account], false) }}"><strong>{{ $account }}</strong></a></td><td><strong>UGX {{ number_format($balance, 2) }}</strong></td></tr>
        @empty
            <tr><td colspan="2">No account balances are available.</td></tr>
        @endforelse
    </tbody></table>
</section>
@endif
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><p class="card-label">Finance workbook</p><h3>{{ $selectedAccount ? $selectedAccount.' transactions' : 'Daily entries' }}</h3></div><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></div>
    <table class="exec-table">
        <thead><tr><th>Date & time</th><th>Type</th><th>Account / description</th><th>Reference</th><th>Money in</th><th>Money out</th><th>{{ $selectedAccount ? 'Account balance' : 'Cash on Hand balance' }}</th></tr></thead>
        <tbody>
            <tr><td colspan="6"><strong>{{ $selectedAccount ? $selectedAccount.' balance brought forward' : 'Cash on Hand brought forward' }}</strong></td><td><strong>UGX {{ number_format($selectedAccount ? $selectedAccountOpening : $cashOpening, 2) }}</strong></td></tr>
            @forelse($transactions as $t)
                <tr><td>{{ $t->transaction_date->format('d M Y') }}<small class="table-sub">Entered {{ $t->created_at->format('H:i') }} by {{ $t->recorder?->displayName() ?? 'System' }}</small></td><td>{{ Str::headline($t->type) }}</td><td>{{ $t->account }}@if($t->type === 'transfer') &rarr; {{ $t->counterparty }}@endif<small class="table-sub">{{ $t->description }}</small></td><td>{{ $t->reference ?: '—' }}</td><td>{{ $selectedAccount ? ((float)$t->account_money_in > 0 ? 'UGX '.number_format((float)$t->account_money_in, 2) : '—') : (in_array($t->type, ['money_received', 'transfer'], true) ? 'UGX '.number_format((float) $t->amount, 2) : '—') }}@if(!$selectedAccount && $t->type === 'transfer')<small class="table-sub">Into {{ $t->counterparty }}</small>@endif</td><td>{{ $selectedAccount ? ((float)$t->account_money_out > 0 ? 'UGX '.number_format((float)$t->account_money_out, 2) : '—') : (in_array($t->type, ['money_paid', 'transfer'], true) ? 'UGX '.number_format((float) $t->amount, 2) : '—') }}@if(!$selectedAccount && $t->type === 'transfer')<small class="table-sub">From {{ $t->account }}</small>@endif</td><td><strong>UGX {{ number_format((float) ($selectedAccount ? $t->account_running_balance : $t->cash_running_balance), 2) }}</strong>@if(!$selectedAccount && $t->type === 'transfer' && $t->counterparty === 'Cash on Hand')<small class="table-sub">Cash withdrawn from bank</small>@endif</td></tr>
            @empty
                <tr><td colspan="7">No Finance entries were recorded for these dates.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="4">Total movement</th><th>UGX {{ number_format($selectedAccount ? $transactions->sum('account_money_in') : $moneyIn + $transferTotal, 2) }}</th><th>UGX {{ number_format($selectedAccount ? $transactions->sum('account_money_out') : $moneyOut + $transferTotal, 2) }}</th><th>UGX {{ number_format($selectedAccount ? $selectedAccountRunning : $cashClosing, 2) }}</th></tr></tfoot>
    </table>
</section>
<section class="exec-panel table-wrap"><div class="section-heading"><div><p class="eyebrow">Non-cash stock use</p><h2>Farm Consumption</h2><p>Products taken for farm, department, or staff use. These entries are not sales or payments.</p></div></div><table class="exec-table"><thead><tr><th>Date</th><th>Product</th><th>Quantity</th><th>Department / taken by</th><th>Note</th><th>Recorded by</th></tr></thead><tbody>@forelse($storeConsumption as $use)<tr><td>{{ $use->movement_date->format('d M Y') }}</td><td>{{ $use->item?->name }}</td><td>{{ number_format((float)$use->quantity,2) }} {{ $use->item?->unit }}</td><td>{{ $use->supplier_or_recipient ?: 'Farm use' }}</td><td>{{ $use->notes ?: '—' }}</td><td>{{ $use->recorder?->displayName() }}</td></tr>@endforeach @foreach($salesConsumption as $use)<tr><td>{{ $use->sale_date->format('d M Y') }}</td><td>{{ $use->lines->map(fn($line)=>$line->description)->join(', ') }}</td><td>{{ $use->lines->map(fn($line)=>number_format((float)$line->quantity,2).' '.$line->unit)->join(', ') }}</td><td>{{ $use->consumed_by }}</td><td>{{ $use->notes ?: '—' }}</td><td>{{ $use->seller?->displayName() }}</td></tr>@endforeach @if($storeConsumption->isEmpty()&&$salesConsumption->isEmpty())<tr><td colspan="6">No farm consumption has been recorded.</td></tr>@endif</tbody></table></section>
<style>.finance-register-filters{padding:14px;margin-bottom:14px}.finance-filter-primary{display:grid;grid-template-columns:minmax(220px,1.6fr) minmax(150px,.8fr) minmax(140px,.7fr) minmax(140px,.7fr) auto auto;align-items:end;gap:9px}.finance-register-filters .field{margin:0}.finance-register-filters .btn{min-height:43px}.finance-more-filters{margin-top:10px;border-top:1px solid #eee1d5;padding-top:9px}.finance-more-filters summary{width:max-content;color:#8b1e2d;cursor:pointer;font-size:12px;font-weight:800}.finance-more-filters>div{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;margin-top:10px}@media(max-width:1050px){.finance-filter-primary{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-filter-search{grid-column:1/-1}}@media(max-width:580px){.finance-filter-primary,.finance-more-filters>div{grid-template-columns:1fr}.finance-filter-search{grid-column:auto}}</style>
@endsection
