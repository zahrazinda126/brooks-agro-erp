@extends('layouts.app')
@section('title','Finance Daily Workbook')
@section('content')
@unless(! $selectedAccount && $view === 'balances')
<div class="page-head page-head--split"><div><h2>{{ $selectedAccount ? $selectedAccount.' Account' : ['register'=>'Finance Daily Workbook','consumption'=>'Farm Consumption','balances'=>'Account Balances'][$view] }}</h2><p>{{ $selectedAccount ? 'Complete transaction history and running balance for this account.' : ['register'=>'Daily entries recorded by Finance are shown here. Sales entries remain under Approved approvals.','consumption'=>'Products taken for farm, department or staff use. These entries are not sales or payments.','balances'=>'Where the money is held, per account, as at the chosen date. Open an account to see its full history.'][$view] }}</p></div><div class="form-actions">@if($selectedAccount)<a class="btn btn--quiet" href="{{ route('finance.transactions.index', [], false) }}">&larr; All accounts</a>@endif @if($user->canDo('transactions','create') && $view === 'register')<a class="btn btn--inline" href="{{ route('finance.transactions.create') }}">New entry</a>@endif</div></div>
@endunless
@if($view === 'register')
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
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><p class="card-label">Finance workbook</p><h3>{{ $selectedAccount ? $selectedAccount.' transactions' : 'Daily entries' }}</h3></div><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></div>
    <table class="exec-table">
        <thead><tr><th>Date & time</th><th>Transaction</th><th>From account</th><th>To account</th><th>Description</th><th>Reference</th><th>Amount</th><th>{{ $selectedAccount ? 'Account balance' : 'Cash on Hand balance' }}</th></tr></thead>
        <tbody>
            <tr><td colspan="7"><strong>{{ $selectedAccount ? $selectedAccount.' balance brought forward' : 'Cash on Hand brought forward' }}</strong></td><td><strong>@money($selectedAccount ? $selectedAccountOpening : $cashOpening, 2)</strong></td></tr>
            @forelse($transactions as $t)
                <tr><td>{{ $t->transaction_date->format('d M Y') }}<small class="table-sub">Entered {{ $t->created_at->format('H:i') }} by {{ $t->recorder?->displayName() ?? 'System' }}</small></td><td>{{ Str::headline($t->type) }}</td><td>{{ $t->from_account }}</td><td>{{ $t->to_account }}</td><td>{{ $t->description }}@if($t->reversed_at)<small class="table-sub"><x-status-pill status="reversed" label="Reversed" /> {{ $t->reversed_at->format('d M Y') }} — {{ $t->reversal_reason }}</small>@endif @if($t->reversal_of_id)<small class="table-sub">Reversal of entry #{{ $t->reversal_of_id }}</small>@endif</td><td>{{ $t->reference ?: '—' }}@if($t->isReversible() && $user->canDo('transactions','create'))<details class="reverse-entry no-print"><summary>Reverse</summary><form method="POST" action="{{ route('finance.transactions.reverse', $t) }}" data-confirm="Post an equal and opposite entry to reverse this one? The original stays in the register.">@csrf<input name="reversal_reason" required maxlength="500" placeholder="Reason for reversal" aria-label="Reason for reversal"><button class="btn btn--quiet btn--small">Reverse entry</button></form></details>@endif</td><td><strong>@money($t->amount, 2)</strong></td><td><strong>@money(($selectedAccount ? $t->account_running_balance : $t->cash_running_balance), 2)</strong></td></tr>
            @empty
                <tr><td colspan="8">No Finance entries were recorded for these dates.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><th colspan="6">{{ $selectedAccount ? 'Account movement total' : 'Cash movement total' }}</th><th>@money($transactions->sum('amount'), 2)</th><th>Ending balance: @money($selectedAccount ? $selectedAccountRunning : $cashClosing, 2)</th></tr></tfoot>
    </table>
</section>
@elseif($view === 'consumption')
<section class="exec-panel table-wrap"><table class="exec-table"><thead><tr><th>Date</th><th>Product</th><th>Quantity</th><th>Department / taken by</th><th>Note</th><th>Recorded by</th></tr></thead><tbody>@forelse($storeConsumption as $use)<tr><td>{{ $use->movement_date->format('d M Y') }}</td><td>{{ $use->item?->name }}</td><td>{{ number_format((float)$use->quantity,2) }} {{ $use->item?->unit }}</td><td>{{ $use->supplier_or_recipient ?: 'Farm use' }}</td><td>{{ $use->notes ?: '—' }}</td><td>{{ $use->recorder?->displayName() }}</td></tr>@endforeach @foreach($salesConsumption as $use)<tr><td>{{ $use->sale_date->format('d M Y') }}</td><td>{{ $use->lines->map(fn($line)=>$line->description)->join(', ') }}</td><td>{{ $use->lines->map(fn($line)=>number_format((float)$line->quantity,2).' '.$line->unit)->join(', ') }}</td><td>{{ $use->consumed_by }}</td><td>{{ $use->notes ?: '—' }}</td><td>{{ $use->seller?->displayName() }}</td></tr>@endforeach @if($storeConsumption->isEmpty()&&$salesConsumption->isEmpty())<tr><td colspan="6">No farm consumption has been recorded.</td></tr>@endif</tbody></table></section>
@else
@php
    $abRows = collect($accountBalances)->map(fn ($balance, $name) => ['name' => $name, 'balance' => (float) $balance,
        'kind' => preg_match('/mobile money/i', $name) ? 'mobile' : (preg_match('/bank|i&m/i', $name) ? 'bank' : (preg_match('/^cash|petty/i', $name) ? 'cash' : 'other')),
        'status' => abs((float) $balance) < 0.005 ? 'zero' : ((float) $balance < 0 ? 'overdrawn' : 'active')])->values();
    $abPositive = $abRows->where('status', 'active'); $abNegative = $abRows->where('status', 'overdrawn');
@endphp
<section class="ab" data-keep-link>

    <form method="GET" class="ab-card ab-date"><input type="hidden" name="view" value="balances">
        <div class="ab-date__field"><label for="ab-to">Balances as at <b>*</b></label><input id="ab-to" type="date" name="to" value="{{ $to }}" required></div>
        <button class="ab-btn ab-btn--primary">Apply</button>
        <a class="ab-btn ab-btn--ghost" href="{{ route('finance.transactions.index', ['view' => 'balances'], false) }}">Today</a>
    </form>


    <section class="ab-card ab-list">
        <div class="ab-tools">
            <label class="ab-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Search accounts..." aria-label="Search accounts" data-ab-search></label>
            <select class="ab-select" aria-label="Account type" data-ab-kind><option value="">All Accounts</option><option value="bank">Bank accounts</option><option value="mobile">Mobile money</option><option value="cash">Cash</option><option value="other">Other accounts</option></select>
            <label class="ab-filter"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"/></svg><select aria-label="Filter by status" data-ab-status><option value="">Filter</option><option value="active">Active</option><option value="zero">Zero balance</option><option value="overdrawn">Overdrawn</option></select></label>
            <a class="ab-more" href="{{ route('finance.transactions.index', ['view' => 'register'], false) }}" title="Open the register" aria-label="Open the register">&#8942;</a>
        </div>
        <div class="ab-table">
            <table>
                <thead><tr><th>#</th><th>Account</th><th>Balance (UGX) <span class="ab-sort" data-ab-sort="balance">&#8597;</span></th><th>Status <span class="ab-sort" data-ab-sort="status">&#8597;</span></th><th>Actions</th></tr></thead>
                <tbody data-ab-body>
                @forelse($abRows as $i => $row)
                    <tr data-name="{{ mb_strtolower($row['name']) }}" data-kind="{{ $row['kind'] }}" data-status="{{ $row['status'] }}" data-balance="{{ $row['balance'] }}">
                        <td class="ab-n">{{ $i + 1 }}</td>
                        <td><a href="{{ route('finance.transactions.index', ['account' => $row['name']], false) }}">{{ $row['name'] }}</a></td>
                        <td class="{{ $row['status'] === 'overdrawn' ? 'ab-neg' : '' }}">UGX {{ number_format($row['balance'], 2) }}</td>
                        <td><span class="ab-pill ab-pill--{{ $row['status'] }}">{{ ['zero' => 'Zero Balance', 'active' => 'Active', 'overdrawn' => 'Overdrawn'][$row['status'] ] }}</span></td>
                        <td><a class="ab-more ab-more--row" href="{{ route('finance.transactions.index', ['account' => $row['name']], false) }}" title="Open {{ $row['name'] }}" aria-label="Open {{ $row['name'] }}">&middot;&middot;&middot;</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ab-empty">No account balances are available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ab-foot"><span data-ab-count>Showing 1 to {{ $abRows->count() }} of {{ $abRows->count() }} accounts</span><span class="ab-pager"><span class="is-off">&lsaquo;</span><span class="is-current">1</span><span class="is-off">&rsaquo;</span></span></div>
    </section>
</section>
<script>
(function () {
    var body = document.querySelector('[data-ab-body]'); if (!body) return;
    var q = document.querySelector('[data-ab-search]'), kind = document.querySelector('[data-ab-kind]'), status = document.querySelector('[data-ab-status]'), count = document.querySelector('[data-ab-count]');
    var rows = Array.from(body.querySelectorAll('tr[data-name]'));
    function apply() {
        var term = (q.value || '').toLowerCase(), shown = 0;
        rows.forEach(function (r) {
            var ok = (!term || r.dataset.name.indexOf(term) !== -1) && (!kind.value || r.dataset.kind === kind.value) && (!status.value || r.dataset.status === status.value);
            r.hidden = !ok;
            if (ok) { shown++; r.querySelector('.ab-n').textContent = shown; }
        });
        count.textContent = 'Showing ' + (shown ? 1 : 0) + ' to ' + shown + ' of ' + rows.length + ' accounts';
    }
    [q, kind, status].forEach(function (el) { el.addEventListener('input', apply); el.addEventListener('change', apply); });
    var dir = {};
    document.querySelectorAll('[data-ab-sort]').forEach(function (s) {
        s.addEventListener('click', function () {
            var key = s.dataset.abSort; dir[key] = -(dir[key] || -1);
            var order = { overdrawn: 0, zero: 1, active: 2 };
            rows.sort(function (a, b) { return dir[key] * (key === 'balance' ? (parseFloat(a.dataset.balance) - parseFloat(b.dataset.balance)) : (order[a.dataset.status] - order[b.dataset.status])); });
            rows.forEach(function (r) { body.appendChild(r); });
            apply();
        });
    });
})();
</script>
@endif
@pushOnce('styles')<style>.finance-register-filters{padding:14px;margin-bottom:14px}.finance-filter-primary{display:grid;grid-template-columns:minmax(220px,1.6fr) minmax(150px,.8fr) minmax(140px,.7fr) minmax(140px,.7fr) auto auto;align-items:end;gap:9px}.finance-register-filters .field{margin:0}.finance-register-filters .btn{min-height:43px}.finance-more-filters{margin-top:10px;border-top:1px solid #eee1d5;padding-top:9px}.finance-more-filters summary{width:max-content;color:#8b1e2d;cursor:pointer;font-size:12px;font-weight:800}.finance-more-filters>div{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;margin-top:10px}@media(max-width:1050px){.finance-filter-primary{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-filter-search{grid-column:1/-1}}@media(max-width:580px){.finance-filter-primary,.finance-more-filters>div{grid-template-columns:1fr}.finance-filter-search{grid-column:auto}}</style>@endPushOnce
@endsection
