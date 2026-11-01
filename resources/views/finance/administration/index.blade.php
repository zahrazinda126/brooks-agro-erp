@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="page-head">
    <h2>{{ $title }}</h2>
    <p>Finance administration and control settings.</p>
</div>

<nav class="unit-tabs" aria-label="Finance setup sections">
@foreach(['chart-of-accounts'=>'Accounts','finance-periods'=>'Periods','finance-accounts'=>'Cash & Banks','payment-methods'=>'Payment Methods','document-numbering'=>'Numbering','tax-settings'=>'Tax','budget-settings'=>'Budget','finance-imports'=>'Import','audit'=>'Audit Trail'] as $key=>$label)
    @if(auth()->user()->canAccess($key) || ($key==='audit' && auth()->user()->canAccess('audit')))<a class="{{ $screen===$key?'active':'' }}" href="{{ route('finance.admin.index',['screen'=>$key]) }}">{{ $label }}</a>@endif
@endforeach
</nav>
@if($screen==='audit')
<form method="GET" action="{{ route('finance.admin.index', ['screen' => 'audit']) }}" class="card filter-row-card">
    <div class="filter-row">
        <div class="field"><label for="au-q">Search</label><input id="au-q" type="search" name="q" value="{{ request('q') }}" placeholder="e.g. SAL-0251, bill, Poultry"></div>
        <div class="field"><label for="au-from">From</label><input id="au-from" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label for="au-to">To</label><input id="au-to" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="field"><label for="au-user">Person</label><select id="au-user" name="user"><option value="">Everyone</option>@foreach($auditUsers as $person)<option value="{{ $person->id }}" @selected(request('user') == $person->id)>{{ $person->name }} ({{ $person->username }})</option>@endforeach</select></div>
        <div class="field"><label for="au-module">Area</label><select id="au-module" name="module"><option value="">All areas</option>@foreach($auditModules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ Str::headline($module) }}</option>@endforeach</select></div>
        <div class="field"><label for="au-event">Action</label><select id="au-event" name="event"><option value="">All actions</option>@foreach($auditEvents as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ Str::ucfirst(str_replace('_', ' ', $event)) }}</option>@endforeach</select></div>
        <div class="filter-row__actions"><button class="btn btn--small">Apply</button><a class="btn btn--quiet btn--small" href="{{ route('finance.admin.index', ['screen' => 'audit']) }}">Reset</a></div>
    </div>
    <p class="muted u-mt-12">{{ number_format($items->total()) }} {{ Str::plural('entry', $items->total()) }}</p>
</form>
@endif

@if(session('status')) <div class="alert alert--success">{{ session('status') }}</div> @endif
@if($errors->any()) <div class="alert alert--danger">{{ $errors->first() }}</div> @endif

@php $canChange = auth()->user()->canDo($screen, 'create') || auth()->user()->canDo($screen, 'edit'); @endphp

@if($canChange && in_array($screen, ['finance-periods','finance-accounts','payment-methods','document-numbering']))
<form method="POST" action="{{ route('finance.admin.store', ['screen'=>$screen]) }}" class="card" style="margin-bottom:1rem">
    @csrf
    <h3>Add {{ rtrim($title, 's') }}</h3>
    <div class="form-grid">
        @if($screen === 'chart-of-accounts')
            <div class="field"><label>Account code</label><input name="code" value="{{ old('code') }}" required></div>
            <div class="field"><label>Account name</label><input name="name" value="{{ old('name') }}" required></div>
            <div class="field"><label>Account type</label><select name="type" required>@foreach(['asset'=>'Asset','liability'=>'Liability','equity'=>'Equity','income'=>'Income','cogs'=>'Cost of goods sold','expense'=>'Expense'] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
        @elseif($screen === 'finance-periods')
            <div class="field"><label>Period name</label><input name="name" placeholder="August 2026" required></div>
            <div class="field"><label>Start date</label><input type="date" name="starts_on" required></div>
            <div class="field"><label>End date</label><input type="date" name="ends_on" required></div>
        @elseif($screen === 'finance-accounts')
            <div class="field"><label>Account name</label><input name="name" required></div>
            <div class="field"><label>Type</label><select name="kind"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile_money">Mobile money</option></select></div>
            <div class="field"><label>Currency</label><input name="currency" value="UGX" maxlength="3" required></div>
            <div class="field"><label>Institution</label><input name="institution"></div>
            <div class="field"><label>Account number</label><input name="account_number"></div>
        @elseif($screen === 'payment-methods')
            <div class="field"><label>Code</label><input name="code" placeholder="cheque" required></div>
            <div class="field"><label>Name</label><input name="name" placeholder="Cheque" required></div>
        @else
            <div class="field"><label>Document type</label><input name="document_type" placeholder="purchase_order" required></div>
            <div class="field"><label>Prefix</label><input name="prefix" placeholder="PO" required></div>
            <div class="field"><label>Next number</label><input type="number" name="next_number" value="1" min="1" required></div>
            <div class="field"><label>Number length</label><input type="number" name="padding" value="5" min="1" max="12" required></div>
        @endif
    </div>
    <div class="form-actions"><button class="btn">Save</button></div>
</form>
@endif

@if(in_array($screen, ['tax-settings','budget-settings']))
<form method="POST" action="{{ route('finance.admin.store', ['screen'=>$screen]) }}" class="card">
    @csrf
    <div class="form-grid">
    @foreach($items as $item)
        <div class="field"><label>{{ $item->label }}</label><input name="settings[{{ $item->id }}]" value="{{ old('settings.'.$item->id, $item->value) }}" @disabled(!$canChange)><small>{{ $item->description }}</small></div>
    @endforeach
    </div>
    @if($canChange)<div class="form-actions"><button class="btn">Save settings</button></div>@endif
</form>
@elseif($screen === 'finance-imports')
<div class="card">
    <h3>Workbook import control</h3>
    <p>The management workbook has been analysed. Import remains in reconciliation mode so duplicate or unbalanced entries are not posted automatically.</p>
    <p><strong>Analysed source:</strong> July 2025–June 2026 Management Report</p>
    <p><strong>Extract:</strong> {{ file_exists(storage_path('app/finance-workbook-extract.json')) ? 'Ready for reviewed mapping' : 'Not yet generated' }}</p>
</div>
@forelse($items as $batch)@php($summary = json_decode($batch->summary ?? '{}', true) ?: [])
<div class="card u-mt-12">
    <h3>Import batch #{{ $batch->id }} · {{ $batch->filename }} <x-status-pill :status="$batch->status" /></h3>
    <div class="summary-grid summary-grid--compact">
        <x-stat-card label="Rows staged">{{ number_format($batch->source_rows) }}</x-stat-card>
        <x-stat-card label="Period">{{ $summary['date_min'] ?? '—' }} to {{ $summary['date_max'] ?? '—' }}</x-stat-card>
        <x-stat-card label="Exact duplicates">{{ number_format($summary['duplicates'] ?? 0) }}</x-stat-card>
        <x-stat-card label="Not QA-reviewed">{{ number_format($summary['unreviewed'] ?? 0) }}</x-stat-card>
        <x-stat-card label="Revenue reconciles">{{ ($summary['revenue_reconciles'] ?? null) === null ? '—' : (($summary['revenue_reconciles'] ?? false) ? 'Yes' : 'No') }}</x-stat-card>
        <x-stat-card label="Posted to books">{{ number_format($batch->imported_rows) }}</x-stat-card>
    </div>
    <table><thead><tr><th>Workbook type</th><th class="num">Rows</th><th class="num">Amount</th></tr></thead><tbody>
    @foreach($summary['by_type'] ?? [] as $type => $totals)<tr><td>{{ str_replace('_', ' ', $type) }}</td><td class="num">{{ number_format($totals['rows']) }}</td><td class="num">@money($totals['amount'])</td></tr>@endforeach
    </tbody></table>
    @if(!empty($summary['unmatched_accounts']))<p class="u-mt-12"><strong>{{ count($summary['unmatched_accounts']) }} account names are not in the chart of accounts.</strong> Most frequent: {{ collect($summary['unmatched_accounts'])->take(8)->map(fn($n, $name) => $name.' ('.$n.')')->join(', ') }}.</p>@endif
    <p class="muted">Staged rows are held for review and are not posted to the books. Re-run <code>php artisan finance:stage-workbook</code> after correcting the workbook; review decisions are kept.</p>
</div>
@empty
<div class="card u-mt-12"><p>No workbook has been staged yet. Run <code>php artisan finance:stage-workbook</code> to stage the extract for review.</p></div>
@endforelse
@endif

@if(!in_array($screen, ['tax-settings','budget-settings','finance-imports']))
<div class="card table-wrap"><table><thead><tr>
    @if($screen==='chart-of-accounts')<th>Code</th><th>Account</th><th>Type</th><th>Status</th>
    @elseif($screen==='finance-periods')<th>Period</th><th>Start</th><th>End</th><th>Status</th><th>Action</th>
    @elseif($screen==='finance-accounts')<th>Account</th><th>Type</th><th>Institution / Number</th><th>Currency</th>
    @elseif($screen==='payment-methods')<th>Code</th><th>Payment method</th><th>Status</th>
    @elseif($screen==='document-numbering')<th>Document</th><th>Next reference</th><th>Length</th>
    @else<th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Details</th>@endif
</tr></thead><tbody>
@forelse($items as $item)<tr>
    @if($screen==='chart-of-accounts')<td>{{ $item->code }}</td><td class="{{ !empty($item->is_group) ? 'coa-group' : (!empty($item->parent_id) ? 'coa-child' : '') }}">{{ $item->name }}@if(!empty($item->workbook_label) && $item->workbook_label !== $item->name)<small class="coa-workbook">Workbook: {{ $item->workbook_label }}</small>@endif</td><td>{{ ucfirst($item->type) }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
    @elseif($screen==='finance-periods')<td>{{ $item->name }}</td><td>{{ $item->starts_on }}</td><td>{{ $item->ends_on }}</td><td>{{ ucfirst($item->status) }}</td><td>@if($canChange)<form method="POST" action="{{ route('finance.admin.periods.toggle',['period'=>$item->id]) }}">@csrf<button class="btn btn--quiet">{{ $item->status==='open'?'Close':'Reopen' }}</button></form>@endif</td>
    @elseif($screen==='finance-accounts')<td>{{ $item->name }}</td><td>{{ str_replace('_',' ',ucfirst($item->kind)) }}</td><td>{{ trim(($item->institution ?? '').' '.($item->account_number ?? '')) ?: '—' }}</td><td>{{ $item->currency }}</td>
    @elseif($screen==='payment-methods')<td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
    @elseif($screen==='document-numbering')<td>{{ str_replace('_',' ',ucfirst($item->document_type)) }}</td><td>{{ $item->prefix }}-{{ str_pad($item->next_number,$item->padding,'0',STR_PAD_LEFT) }}</td><td>{{ $item->padding }}</td>
    @else<td class="u-nowrap">{{ $item->created_at->format('d M Y') }}<small class="table-sub">{{ $item->created_at->format('H:i:s') }}</small></td><td>{{ $item->user?->name ?? 'System' }}<small class="table-sub">{{ $item->user?->username ?? $item->username }}</small></td><td>{{ str_replace('_',' ',ucfirst($item->event)) }}</td><td>{{ Str::headline($item->module_key) }}</td><td>{{ $item->description ?? '' }}</td>@endif
</tr>@empty<tr><td colspan="6">No records yet.</td></tr>@endforelse
</tbody></table></div>
@if($screen==='audit' && method_exists($items,'links')) {{ $items->links() }} @endif
@endif

@if($canChange && $screen === 'chart-of-accounts')
<details class="card disclosure u-mt-16" @if($errors->any()) open @endif>
    <summary><span><strong>Add Chart of Account</strong><small>Create another account only when it is needed.</small></span></summary>
    <div class="disclosure__body"><form method="POST" action="{{ route('finance.admin.store', ['screen'=>$screen]) }}" class="form-grid">
    @csrf
    <div class="form-grid">
        <div class="field"><label>Account code</label><input name="code" value="{{ old('code') }}" required></div>
        <div class="field"><label>Account name</label><input name="name" value="{{ old('name') }}" required></div>
        <div class="field"><label>Account type</label><select name="type" required>@foreach(['asset'=>'Asset','liability'=>'Liability','equity'=>'Equity','income'=>'Income','cogs'=>'Cost of goods sold','expense'=>'Expense'] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
    </div>
    <div class="form-actions"><button class="btn">Save</button></div>
    </form></div>
</details>
@endif
@endsection
