@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="page-head">
    <h2>{{ $title }}</h2>
    <p>Finance administration and control settings.</p>
</div>

@if(session('status')) <div class="alert alert--success">{{ session('status') }}</div> @endif
@if($errors->any()) <div class="alert alert--danger">{{ $errors->first() }}</div> @endif

@php $canChange = auth()->user()->canDo($screen, 'create') || auth()->user()->canDo($screen, 'edit'); @endphp

@if($canChange && in_array($screen, ['chart-of-accounts','finance-periods','finance-accounts','payment-methods','document-numbering']))
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
    @if($screen==='chart-of-accounts')<td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ ucfirst($item->type) }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
    @elseif($screen==='finance-periods')<td>{{ $item->name }}</td><td>{{ $item->starts_on }}</td><td>{{ $item->ends_on }}</td><td>{{ ucfirst($item->status) }}</td><td>@if($canChange)<form method="POST" action="{{ route('finance.admin.periods.toggle',['period'=>$item->id]) }}">@csrf<button class="btn btn--quiet">{{ $item->status==='open'?'Close':'Reopen' }}</button></form>@endif</td>
    @elseif($screen==='finance-accounts')<td>{{ $item->name }}</td><td>{{ str_replace('_',' ',ucfirst($item->kind)) }}</td><td>{{ trim(($item->institution ?? '').' '.($item->account_number ?? '')) ?: '—' }}</td><td>{{ $item->currency }}</td>
    @elseif($screen==='payment-methods')<td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
    @elseif($screen==='document-numbering')<td>{{ str_replace('_',' ',ucfirst($item->document_type)) }}</td><td>{{ $item->prefix }}-{{ str_pad($item->next_number,$item->padding,'0',STR_PAD_LEFT) }}</td><td>{{ $item->padding }}</td>
    @else<td>{{ $item->created_at }}</td><td>{{ $item->username ?? 'System' }}</td><td>{{ str_replace('_',' ',ucfirst($item->event)) }}</td><td>{{ $item->module_key }}</td><td>{{ $item->description ?? '' }}</td>@endif
</tr>@empty<tr><td colspan="6">No records yet.</td></tr>@endforelse
</tbody></table></div>
@if($screen==='audit' && method_exists($items,'links')) {{ $items->links() }} @endif
@endif
@endsection
