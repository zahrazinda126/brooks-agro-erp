@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="executive-workspace executive-workspace--{{ $screen }}">
<header class="executive-workspace__head"><div><p class="eyebrow">Chief Executive Office</p><h2>{{ $title }}</h2><p>Executive oversight based on current system records.</p></div><div class="executive-workspace__period">{{ now()->format('F Y') }}</div></header>
<nav class="executive-workspace__nav" aria-label="CEO workspace">
@foreach(['kpis'=>'Dashboard','reports'=>'Reports','messages'=>'Messages','notifications'=>'Approvals','security'=>'Users','settings'=>'Settings'] as $key=>$label)
    <a class="{{ $screen===$key?'is-active':'' }}" href="{{ route('executive.index',['screen'=>$key]) }}">{{ $label }}</a>
@endforeach
</nav>

@if($screen === 'kpis')
<div class="exec-kpi-grid">
@foreach([['💵','Money received','UGX '.number_format($data['income']),'positive'],['💸','Money paid','UGX '.number_format($data['spending']),'negative'],['✅','Pending approvals',$data['pending'],'warning'],['⏰','Overdue approvals',$data['overdue'],'danger'],['👥','Active employees',$data['employees'],'neutral'],['🚜','Registered assets',$data['assets'],'neutral'],['📋','Approval exposure','UGX '.number_format($data['purchases']),'warning']] as [$icon,$label,$value,$tone])
<article class="exec-kpi exec-kpi--{{ $tone }}"><span class="exec-kpi__icon">{{ $icon }}</span><div><small>{{ $label }}</small><strong>{{ $value }}</strong></div></article>
@endforeach
</div>
@php $chartMax=max(1,$data['trend']->max(fn($row)=>max($row['income'],$row['spending']))); $approvalTotal=max(1,$data['pending']+$data['overdue']); @endphp
<div class="exec-chart-grid">
<section class="exec-panel"><div class="exec-panel__head"><div><p class="card-label">Cash movement</p><h3>Six-month trend</h3></div><div class="chart-legend"><span><i class="legend-income"></i>Received</span><span><i class="legend-spend"></i>Paid</span></div></div><div class="bar-chart">
@foreach($data['trend'] as $row)<div class="bar-chart__month"><div class="bar-chart__bars"><i class="bar-chart__bar bar-chart__bar--income" style="height:{{ max(3,round($row['income']/$chartMax*100)) }}%" title="Received @money($row['income'])"></i><i class="bar-chart__bar bar-chart__bar--spend" style="height:{{ max(3,round($row['spending']/$chartMax*100)) }}%" title="Paid @money($row['spending'])"></i></div><span>{{ $row['label'] }}</span></div>@endforeach
</div></section>
<section class="exec-panel"><p class="card-label">Decision workload</p><h3>Approval health</h3><div class="approval-ring" style="--progress:{{ round($data['overdue']/$approvalTotal*100) }}"><strong>{{ $data['pending'] }}</strong><span>pending</span></div><p class="exec-insight">{{ $data['overdue'] ? $data['overdue'].' approval(s) are overdue and need attention.' : 'No overdue approvals. The decision queue is healthy.' }}</p><a class="btn btn--inline" href="{{ route('approvals.index') }}">Open approval queue</a></section>
</div>
@elseif($screen === 'reports')
<section class="report-feature">
    <div class="report-feature__icon">📊</div>
    <div><p class="card-label">Recommended executive view</p><h3>Three-Statement Financial Model</h3><p>Review profitability, financial position and cash movement together in the July–June financial-year format.</p></div>
    <div class="report-feature__actions"><a class="btn" href="{{ route('finance.reports.three-statement') }}">Open model</a><a class="btn btn--quiet" href="{{ route('finance.reports.export',['report'=>'three-statement','year'=>now()->year]) }}">Download branded Excel</a></div>
</section>
<section class="report-feature">
    <div class="report-feature__icon">UGX</div>
    <div><p class="card-label">Financial and farm economics</p><h3>Profitability and Unit Costs</h3><p>Review overall farm profit separately from acquisition, feed and health cost per bird or animal group.</p></div>
    <div class="report-feature__actions"><a class="btn btn--quiet" href="{{ route('finance.reports.profitability') }}">Profitability</a><a class="btn" href="{{ route('finance.reports.unit-costs') }}">Unit Costs</a></div>
</section>
<div class="report-section-head"><div><p class="card-label">Financial statements</p><h3>Core management reports</h3></div><span>{{ now()->format('F Y') }}</span></div>
<div class="report-card-grid">
@foreach([
    ['📈','Income Statement','Revenue, costs and net profit for the selected period.','profit-loss'],
    ['⚖️','Balance Sheet','Assets, liabilities and owners’ equity at a point in time.','balance-sheet'],
    ['💵','Cash Flow Statement','Where cash came from and how it was used.','cash-flow'],
    ['📒','Trial Balance','Debit and credit balances used to verify the ledger.','trial-balance'],
] as [$icon,$label,$description,$report])
<article class="report-card"><span class="report-card__icon">{{ $icon }}</span><div class="report-card__body"><h3>{{ $label }}</h3><p>{{ $description }}</p></div><div class="report-card__actions"><a href="{{ route('finance.reports.statement',['report'=>$report]) }}">View report <span>→</span></a><a href="{{ route('finance.reports.export',['report'=>$report]) }}">⬇ Excel</a></div></article>
@endforeach
</div>
<div class="report-section-head"><div><p class="card-label">Commercial performance</p><h3>Sales report</h3></div></div>
<article class="report-card report-card--wide"><span class="report-card__icon">UGX</span><div class="report-card__body"><h3>Sales & Customer Analysis</h3><p>Review sales totals, frequent customers, product performance and recent receipts.</p></div><div class="report-card__actions"><a href="{{ route('sales.index',['screen'=>'reports']) }}">View report <span>&rarr;</span></a></div></article>
<div class="report-section-head"><div><p class="card-label">People and administration</p><h3>Workforce report</h3></div></div>
<article class="report-card report-card--wide"><span class="report-card__icon">👥</span><div class="report-card__body"><h3>HR & Administration Report</h3><p>Attendance, staffing, welfare, security, fleet activity and administration issues requiring management attention.</p></div><div class="report-card__actions"><a href="{{ route('hr.reports.index') }}">View report <span>→</span></a><a href="{{ route('hr.reports.export') }}">⬇ Download</a></div></article>
@elseif($screen === 'messages')
<form method="POST" action="{{ route('executive.messages.store') }}" class="exec-panel">@csrf<h3>Write a message or announcement</h3><div class="form-grid"><div class="field"><label>Type</label><select name="kind"><option value="message">Message</option><option value="announcement">Announcement</option></select></div><div class="field"><label>Audience</label><select name="audience"><option value="management">Management</option><option value="all_staff">All staff</option><option value="finance">Finance</option><option value="hr">HR</option><option value="production">Production</option></select></div><div class="field field--wide"><label>Subject</label><input name="subject" required></div><div class="field field--wide"><label>Message</label><textarea name="body" required></textarea></div></div><div class="form-actions"><button class="btn">Publish</button></div></form>
@foreach($data as $message)<article class="exec-panel"><p class="card-label">{{ ucfirst($message->kind) }} · {{ str_replace('_',' ',ucfirst($message->audience)) }}</p><h3>{{ $message->subject }}</h3><p>{{ $message->body }}</p><small>{{ $message->sender }} · {{ $message->created_at }}</small></article>@endforeach {{ $data->links() }}
@elseif($screen === 'notifications')
<div class="exec-kpi-grid"><article class="exec-kpi exec-kpi--danger"><span class="exec-kpi__icon">⏰</span><div><small>Overdue approvals</small><strong>{{ $data['overdue'] }}</strong></div></article><article class="exec-kpi exec-kpi--warning"><span class="exec-kpi__icon">✅</span><div><small>Pending approvals</small><strong>{{ $data['approvals']->count() }}</strong></div></article><article class="exec-kpi exec-kpi--danger"><span class="exec-kpi__icon">↩️</span><div><small>Recent rejections</small><strong>{{ $data['rejected']->count() }}</strong></div></article></div>
<div class="exec-panel table-wrap"><p class="card-label">Action required</p><h3>Pending approvals</h3><table class="exec-table"><thead><tr><th>Item</th><th>Status</th><th>Due</th><th></th></tr></thead><tbody>@forelse($data['approvals'] as $approval)<tr><td>{{ $approval->label() }}</td><td><span class="status-pill status-pill--inactive">{{ ucfirst($approval->status) }}</span></td><td>{{ $approval->due_at?->format('d M Y, H:i') ?? '—' }}</td><td><a class="btn btn--quiet" href="{{ route('approvals.show',$approval) }}">Review</a></td></tr>@empty<tr><td colspan="4">No pending approval notifications.</td></tr>@endforelse</tbody></table></div>
<div class="exec-panel table-wrap"><p class="card-label">Returned for correction</p><h3>Recent rejected requests</h3><table class="exec-table"><thead><tr><th>Request</th><th>Rejected by</th><th>Date</th><th>Reason</th><th></th></tr></thead><tbody>@forelse($data['rejected'] as $approval)<tr><td><strong>{{ $approval->label() }}</strong></td><td>{{ $approval->decisionMaker?->displayName() ?? 'Approver' }}</td><td>{{ $approval->decided_at?->format('d M Y, H:i') ?? '—' }}</td><td><span class="rejection-reason">{{ $approval->decision_note ?: 'No reason recorded' }}</span></td><td><a class="btn btn--quiet" href="{{ route('approvals.show',$approval) }}">View history</a></td></tr>@empty<tr><td colspan="5">No rejected requests.</td></tr>@endforelse</tbody></table></div>
@elseif($screen === 'security')
@if(session('credentials'))
@php($credentials=session('credentials'))
<section class="credentials" role="status">
    <div><p class="card-label">Temporary sign-in details</p><h3>Password reset successfully</h3><p>Give these details directly to the account owner. The temporary password is shown only once and must be changed at the next sign-in.</p></div>
    <div class="credentials-pair"><span>Username</span><strong class="mono">{{ $credentials['username'] }}</strong></div>
    <div class="credentials-pair"><span>Temporary password</span><strong class="mono">{{ $credentials['password'] }}</strong></div>
</section>
@endif
<div class="exec-kpi-grid exec-kpi-grid--compact"><article class="exec-kpi exec-kpi--positive"><span class="exec-kpi__icon">✅</span><div><small>Active accounts</small><strong>{{ $data->where('is_active',true)->count() }}</strong></div></article><article class="exec-kpi exec-kpi--danger"><span class="exec-kpi__icon">🔒</span><div><small>Inactive accounts</small><strong>{{ $data->where('is_active',false)->count() }}</strong></div></article><article class="exec-kpi exec-kpi--warning"><span class="exec-kpi__icon">🕒</span><div><small>Never signed in</small><strong>{{ $data->whereNull('last_login_at')->count() }}</strong></div></article><article class="exec-kpi"><span class="exec-kpi__icon">🛡️</span><div><small>Roles represented</small><strong>{{ $data->pluck('role.name')->filter()->unique()->count() }}</strong></div></article></div>
<div class="exec-panel"><div class="exec-panel__head"><div><p class="card-label">Access directory</p><h3>User accounts</h3>@if(auth()->user()->roles->pluck('code')->push(auth()->user()->role?->code)->contains('director'))<a class="btn btn--quiet btn--small u-mt-12" href="{{ route('executive.index', ['screen' => 'permissions']) }}">Role permissions ›</a>@endif</div><label class="exec-search">Search <input id="accountSearch" type="search" placeholder="Name, role or department"></label></div><div class="table-wrap"><table class="exec-table" id="accountTable"><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Last login</th><th>Account action</th></tr></thead><tbody>@foreach($data as $account)<tr><td><div class="user-cell"><span class="user-avatar">{{ strtoupper(substr($account->name,0,1)) }}</span><div><strong>{{ $account->name }}</strong><small>{{ '@'.$account->username }}</small></div></div></td><td><span class="role-chip">{{ $account->role?->name ?? 'Unassigned' }}</span></td><td>{{ $account->department?->name ?? 'Not assigned' }}</td><td><span class="status-pill status-pill--{{ $account->is_active?'active':'inactive' }}">{{ $account->is_active?'Active':'Inactive' }}</span></td><td>{{ $account->last_login_at ? \Carbon\Carbon::parse($account->last_login_at)->format('d M Y, H:i') : 'Never' }}</td><td>@if(($user->role?->code === 'system-admin' || $user->roles->contains('code','system-admin')) && $account->id !== $user->id)<form method="POST" action="{{ route('executive.accounts.reset-password',$account) }}">@csrf<button class="btn btn--quiet btn--inline" onclick="return confirm('Reset the password for this account? The current password will stop working immediately.')">Reset password</button></form>@else<span class="muted">Protected</span>@endif</td></tr>@endforeach</tbody></table></div></div><script>document.getElementById('accountSearch')?.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#accountTable tbody tr').forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(q));});</script>

@elseif($screen === 'permissions')
@if($permissionData)
<section class="exec-panel permission-manager">
    <div class="permission-manager__head"><div><p class="card-label">Director control</p><h3>Grant or withdraw permissions</h3><p>Permissions apply to everyone holding the selected role. Department permissions may add access separately.</p></div><form method="GET" action="{{ route('executive.index',['screen'=>'permissions']) }}"><label for="permissionRole">Role to manage</label><select id="permissionRole" name="role" onchange="this.form.submit()">@foreach($permissionData['roles'] as $role)<option value="{{ $role->id }}" @selected($permissionData['selectedRole']?->id===$role->id)>{{ $role->name }}</option>@endforeach</select></form></div>
    @if($permissionData['selectedRole'])
    <form method="POST" action="{{ route('executive.permissions.update',$permissionData['selectedRole']) }}">@csrf @method('PUT')
        <div class="permission-note"><strong>Editing: {{ $permissionData['selectedRole']->name }}</strong><span>Unticking View normally makes the screen unavailable. Delete and Approve are sensitive permissions.</span></div>
        <div class="table-wrap"><table class="permission-table"><thead><tr><th>Module</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th><th>Approve</th></tr></thead><tbody>
        @foreach($permissionData['modules']->groupBy('group') as $group => $modules)
            <tr class="permission-group"><th colspan="6">{{ $group ?: 'Other' }}</th></tr>
            @foreach($modules as $module) @php($grant=$permissionData['grants']->get($module->id))
            <tr><td><strong>{{ $module->name }}</strong><small>{{ $module->key }}</small></td>@foreach(['view'=>'can_view','create'=>'can_create','edit'=>'can_edit','delete'=>'can_delete','approve'=>'can_approve'] as $action=>$column)<td><label class="permission-check"><input type="checkbox" name="permissions[{{ $module->id }}][{{ $action }}]" value="1" @checked((bool)($grant?->{$column} ?? false))><span></span></label></td>@endforeach</tr>
            @endforeach
        @endforeach
        </tbody></table></div>
        <div class="permission-save"><div><strong>Review before saving</strong><span>This change affects all current and future users with this role and is written to the audit trail.</span></div><button class="btn" onclick="return confirm('Apply these permissions to every {{ $permissionData['selectedRole']->name }} account?')">Save role permissions</button></div>
    </form>
    @else<p class="muted">No junior roles are available to manage.</p>@endif
</section>
@endif
@else
<section class="settings-intro"><div><p class="card-label">Administration settings</p><h3>What these controls do</h3><p>These are the company-wide rules used automatically by Finance, HR, security, inventory and reporting. They are not daily records.</p></div><span>{{ $data->count() }} controls configured</span></section>
<div class="settings-groups">
@foreach($data->groupBy(fn($setting) => explode('.', $setting->key)[0]) as $group => $settings)
<section class="exec-panel settings-group"><header><span>{{ match($group){'company'=>'Company','finance'=>'Finance & reporting','hr'=>'Human Resources','inventory'=>'Inventory alerts','notifications'=>'Notifications','poultry'=>'Poultry','reports'=>'Report approvals','security'=>'Account security',default=>Str::headline($group)} }}</span><strong>{{ $settings->count() }} {{ Str::plural('setting',$settings->count()) }}</strong></header><div>
@foreach($settings as $setting)<article><div><strong>{{ $setting->label ?: Str::headline(str_replace('.',' ',$setting->key)) }}</strong><small>{{ $setting->description ?: match($group){'company'=>'Basic company identity used on system documents.','finance'=>'Controls currency and the financial reporting period.','hr'=>'Controls how staff numbers are generated.','inventory'=>'Controls when stock warnings appear.','notifications'=>'Controls which notification channels are enabled.','poultry'=>'Controls poultry reference numbering.','reports'=>'Controls how reports move through approval.','security'=>'Controls password and account protection.',default=>'System-wide configuration.'} }}</small></div><code>{{ $setting->type === 'bool' ? ((bool)$setting->value ? 'Enabled' : 'Disabled') : $setting->value }}</code></article>@endforeach
</div></section>
@endforeach
</div><div class="notice"><strong>Why these are protected:</strong> changing them can affect every account or report. The Director can review them here; controlled editing can be added for selected settings that should be changed through the application.</div>
@endif
</div>
@endsection
