@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="page-head">
    <h2>{{ $greeting }}, {{ $user->firstName() }}</h2>
    <p>{{ now()->format('l, j F Y') }} &middot; {{ $user->roleSummary() }}</p>
</div>

@foreach ($panels as $panel)

    @if ($panel === 'sales-dashboard')
        @php
            $s = $data['salesDashboard'];
            $salesMax = max(1, $s['monthly']->max('amount'));
        @endphp
        <section class="sales-dashboard">
            <div class="sales-hero"><div><span class="finance-panel-kicker">Sales workspace</span><h3>Today at the shop</h3><p>Record sales, issue receipts and see which customers and products are driving revenue.</p></div><div><a class="btn" href="{{ route('sales.index',['screen'=>'sale']) }}">Record a sale</a><a class="btn btn--quiet" href="{{ route('sales.index',['screen'=>'reports']) }}">View sales report</a></div></div>
            <div class="sales-dashboard__kpis">
                <article><span>&#8599;</span><div><small>Sales today</small><strong>UGX {{ number_format($s['today']) }}</strong><em>{{ $s['todayCount'] }} receipt{{ $s['todayCount']===1?'':'s' }}</em></div></article>
                <article><span>&#9670;</span><div><small>{{ now()->format('F') }} sales</small><strong>UGX {{ number_format($s['month']) }}</strong><em>{{ $s['monthCount'] }} completed sales</em></div></article>
                <article><span>&#9733;</span><div><small>Average sale</small><strong>UGX {{ number_format($s['average']) }}</strong><em>This month</em></div></article>
                <article><span>&#128101;</span><div><small>Known customers</small><strong>{{ number_format($s['customers']) }}</strong><em>Saved for repeat analysis</em></div></article>
            </div>
            <div class="sales-dashboard__grid">
                <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Six-month trend</p><h3>Sales performance</h3></div><a href="{{ route('sales.index',['screen'=>'reports']) }}">Full report &rarr;</a></div><div class="sales-chart">@foreach($s['monthly'] as $month)<div><span><i style="height:{{ $month['amount'] > 0 ? max(5,$month['amount']/$salesMax*100) : 2 }}%"></i></span><small>{{ $month['label'] }}</small><b>UGX {{ number_format($month['amount']) }}</b></div>@endforeach</div></section>
                <section class="exec-panel"><div class="section-heading"><div><p class="card-label">This month</p><h3>Top products</h3></div></div><div class="sales-ranking">@forelse($s['topProducts'] as $index=>$row)<div><b>{{ $index+1 }}</b><span><strong>{{ $row->name }}</strong><small>{{ number_format($row->quantity,2) }} units sold</small></span><em>UGX {{ number_format($row->amount) }}</em></div>@empty<p class="muted">Products will rank here after sales are recorded.</p>@endforelse</div></section>
            </div>
            <section class="exec-panel table-wrap"><div class="section-heading"><div><p class="card-label">Latest activity</p><h3>Recent receipts</h3></div><a href="{{ route('sales.index',['screen'=>'sale']) }}">New sale &rarr;</a></div><table><thead><tr><th>Date</th><th>Receipt</th><th>Customer</th><th>Items</th><th>Total</th></tr></thead><tbody>@forelse($s['recent'] as $sale)<tr><td>{{ $sale->sale_date->format('d M Y') }}</td><td><strong>{{ $sale->invoice_number }}</strong></td><td>{{ $sale->customer_name }}</td><td>{{ $sale->lines->count() }}</td><td><strong>UGX {{ number_format($sale->total) }}</strong></td></tr>@empty<tr><td colspan="5">No sales recorded yet. Use “Record a sale” to create the first receipt.</td></tr>@endforelse</tbody></table></section>
        </section>
    @elseif ($panel === 'hr-dashboard')
        @php
            $hr = $data['hrDashboard'];
        @endphp
        <section class="hr-dashboard">
            <div class="hr-hero"><div><span class="finance-panel-kicker">People & welfare</span><h3>HR workspace</h3><p>Keep staff records complete, prepare payroll, follow contracts, welfare and employee matters from one place.</p></div><a class="btn" href="{{ route('employees.create') }}">Add worker</a></div>
            <div class="hr-kpis">
                <article><span>&#128101;</span><div><small>Active workers</small><strong>{{ $hr['active'] }}</strong><em>{{ $hr['permanent'] }} permanent &middot; {{ $hr['casual'] }} casual</em></div></article>
                <article><span>UGX</span><div><small>Net payroll this month</small><strong>{{ number_format($hr['monthlyPayroll']) }}</strong><em>{{ $hr['payrollPending'] }} awaiting approval</em></div></article>
                <article><span>&#9203;</span><div><small>Contracts ending</small><strong>{{ $hr['endingSoon'] }}</strong><em>Within the next 30 days</em></div></article>
                <article><span>!</span><div><small>Needs attention</small><strong>{{ $hr['withoutLogin'] + $hr['pendingDeductions'] }}</strong><em>{{ $hr['withoutLogin'] }} without login &middot; {{ $hr['pendingDeductions'] }} deductions</em></div></article>
            </div>
            <div class="hr-work-grid">
                <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Staff register</p><h3>Recently added workers</h3></div><a href="{{ route('employees.index') }}">View all &rarr;</a></div><div class="hr-staff-list">@forelse($hr['recentStaff'] as $employee)<a href="{{ route('employees.show',$employee) }}"><span>{{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}</span><div><strong>{{ $employee->fullName() }}</strong><small>{{ $employee->job_title ?: ($employee->role?->name ?? 'Role not assigned') }} &middot; {{ $employee->department?->name ?? 'No department' }}</small></div><em>{{ Str::headline($employee->employment_type) }}</em></a>@empty<p class="muted">No workers have been registered.</p>@endforelse</div></section>
                <aside class="hr-actions"><section class="exec-panel"><p class="card-label">Payroll cycle</p><h3>{{ $hr['latestPayroll']?->payroll_number ?? 'No payroll yet' }}</h3>@if($hr['latestPayroll'])<p class="muted">{{ $hr['latestPayroll']->period_start->format('d M') }} &ndash; {{ $hr['latestPayroll']->period_end->format('d M Y') }} &middot; {{ Str::headline($hr['latestPayroll']->status) }}</p><strong class="hr-pay-total">UGX {{ number_format($hr['latestPayroll']->net_total) }}</strong>@else<p class="muted">Generate the first payroll when staff pay details are ready.</p>@endif<a class="btn btn--inline" href="{{ route('payroll.index') }}">Open payroll</a></section><section class="exec-panel"><p class="card-label">Common HR work</p><div class="hr-quick-links"><a href="{{ route('hr.kpis.index') }}">HR dashboard & KPIs</a><a href="{{ route('hr.performance.index') }}">Performance records</a><a href="{{ route('hr.welfare-budget.index') }}">Staff welfare budget</a><a href="{{ route('communications.index') }}">Messages</a></div></section></aside>
            </div>
        </section>
    @elseif ($panel === 'pa-dashboard')
        @php
            $pa = $data['paDashboard'];
        @endphp
        <section class="pa-dashboard">
            <script>document.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('.pa-dashboard');const title=root?.querySelector('.pa-main-grid > .exec-panel .section-heading h3');if(title)title.textContent="Director's directives";});</script>
            <script>document.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('.pa-dashboard');if(!root)return;const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);while(walker.nextNode()){const node=walker.currentNode;if(!/[\u00c2\u00c3]/.test(node.data))continue;let value=node.data;for(let i=0;i<3&&/[\u00c2\u00c3]/.test(value);i++){try{value=decodeURIComponent(escape(value));}catch(e){break;}}node.data=value;}const icons=root.querySelectorAll('.pa-kpis article>span');if(icons[0])icons[0].textContent='\u2713';if(icons[1])icons[1].textContent='\u25c6';const empty=root.querySelector('.pa-directive-list .finance-empty');if(empty){empty.querySelector('span').textContent='\u2713';empty.querySelector('p').textContent="Add the Director's next instruction here and follow it through to completion.";}});</script>
            <div class="pa-hero"><div><span class="finance-panel-kicker">Director's office</span><h3>Implementation & executive coordination</h3><p>Track directives from decision to completion, coordinate the DirectorÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢s schedule, and verify what approved money achieved.</p></div><a class="btn btn--inline" href="{{ route('communications.index') }}">Open messages</a></div>
            <div class="pa-kpis"><article><span>ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“</span><div><small>Open directives</small><strong>{{ $pa['openDirectives'] }}</strong><em>{{ $pa['overdueDirectives'] }} overdue</em></div></article><article><span>ÃƒÂ¢Ã¢â‚¬â€Ã‚Â·</span><div><small>Meetings today</small><strong>{{ $pa['todayMeetings'] }}</strong><em>{{ $pa['upcomingMeetings']->count() }} upcoming</em></div></article><article><span>UGX</span><div><small>Approved funds tracked</small><strong>{{ number_format($pa['approvedAmount']) }}</strong><em>{{ $pa['fundsPending'] }} awaiting verification</em></div></article><article><span>%</span><div><small>Funds accounted for</small><strong>{{ $pa['approvedAmount']>0?round($pa['accountedAmount']/$pa['approvedAmount']*100):0 }}%</strong><em>UGX {{ number_format($pa['accountedAmount']) }}</em></div></article></div>
            <div class="pa-main-grid">
                <section class="exec-panel"><div class="section-heading"><div><p class="card-label">Implementation tracker</p><h3>DirectorÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢s directives</h3></div><details class="disclosure pa-add"><summary>Add directive</summary><div class="disclosure__body"><form method="POST" action="{{ route('executive-follow-up.directives.store') }}">@csrf<div class="field"><label>Directive title</label><input name="title" required></div><div class="field"><label>Responsible team</label><input name="responsible_team" placeholder="Finance, Production, HR..." required></div><div class="form-grid"><div class="field"><label>Issued on</label><input type="date" name="issued_on" value="{{ now()->toDateString() }}" required></div><div class="field"><label>Due on</label><input type="date" name="due_on"></div></div><div class="field"><label>Director's instruction</label><textarea name="directive" required></textarea></div><button class="btn">Start tracking</button></form></div></details></div><div class="pa-directive-list">@forelse($pa['directives']->take(8) as $directive)<article><div class="pa-directive-head"><div><strong>{{ $directive->title }}</strong><small>{{ $directive->responsible_team }} Ãƒâ€šÃ‚Â· Due {{ $directive->due_on?->format('d M Y')??'not set' }}</small></div><span class="status-pill">{{ str_replace('_',' ',$directive->status) }}</span></div><div class="pa-progress"><i style="width:{{ $directive->progress }}%"></i></div><form method="POST" action="{{ route('executive-follow-up.directives.update',$directive) }}">@csrf @method('PUT')<div class="form-grid"><div class="field"><label>Status</label><select name="status">@foreach(['not_started','in_progress','blocked','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($directive->status===$status)>{{ Str::headline($status) }}</option>@endforeach</select></div><div class="field"><label>Progress %</label><input type="number" min="0" max="100" name="progress" value="{{ $directive->progress }}" required></div><div class="field field--wide"><label>Feedback to Director</label><textarea name="latest_feedback" required>{{ $directive->latest_feedback }}</textarea></div></div><button class="btn btn--small">Update & send feedback</button></form></article>@empty<div class="finance-empty"><span>ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“</span><strong>No directives being tracked</strong><p>Add the DirectorÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢s next instruction here and follow it through to completion.</p></div>@endforelse</div></section>
                <aside class="pa-side"><section class="exec-panel"><div class="section-heading"><div><p class="card-label">Director's calendar</p><h3>Upcoming meetings</h3></div></div>@forelse($pa['upcomingMeetings'] as $meeting)<div class="pa-meeting"><time><strong>{{ $meeting->starts_at->format('d') }}</strong>{{ $meeting->starts_at->format('M') }}</time><div><strong>{{ $meeting->title }}</strong><small>{{ $meeting->starts_at->format('H:i') }} Ãƒâ€šÃ‚Â· {{ $meeting->venue?:'Venue pending' }}</small></div></div>@empty<p class="muted">No upcoming meetings.</p>@endforelse<details class="disclosure"><summary>Schedule meeting</summary><div class="disclosure__body"><form method="POST" action="{{ route('executive-follow-up.meetings.store') }}">@csrf<div class="field"><label>Meeting</label><input name="title" required></div><div class="field"><label>Starts</label><input type="datetime-local" name="starts_at" required></div><div class="field"><label>Ends</label><input type="datetime-local" name="ends_at"></div><div class="field"><label>Venue</label><input name="venue"></div><div class="field"><label>Participants</label><textarea name="participants"></textarea></div><div class="field"><label>Agenda</label><textarea name="agenda"></textarea></div><button class="btn">Schedule</button></form></div></details></section>
                <section class="exec-panel pa-funds"><p class="card-label">Post-approval accountability</p><h3>Approved money follow-up</h3><p class="muted">Confirm that approved funds achieved their intended purpose and have supporting evidence.</p><div class="pa-fund-summary"><span>Accounted</span><strong>UGX {{ number_format($pa['accountedAmount']) }}</strong><i><b style="width:{{ $pa['approvedAmount']?min(100,$pa['accountedAmount']/$pa['approvedAmount']*100):0 }}%"></b></i></div>@foreach($pa['funds']->take(4) as $followUp)<details class="disclosure"><summary><span><strong>{{ $followUp->approval->label() }}</strong><small>UGX {{ number_format($followUp->approved_amount) }} Ãƒâ€šÃ‚Â· {{ Str::headline($followUp->status) }}</small></span></summary><div class="disclosure__body"><p>{{ $followUp->intended_purpose }}</p><form method="POST" action="{{ route('executive-follow-up.funds.update',$followUp) }}">@csrf @method('PUT')<div class="field"><label>Status</label><select name="status">@foreach(['awaiting_implementation','in_use','partly_accounted','verified','concern'] as $status)<option value="{{ $status }}" @selected($followUp->status===$status)>{{ Str::headline($status) }}</option>@endforeach</select></div><div class="field"><label>Amount accounted for</label><input type="number" min="0" max="{{ $followUp->approved_amount }}" name="amount_accounted_for" value="{{ $followUp->amount_accounted_for }}" required></div><div class="field"><label>Implementation result</label><textarea name="implementation_result" required>{{ $followUp->implementation_result }}</textarea></div><div class="field"><label>Evidence reference</label><input name="evidence_reference" value="{{ $followUp->evidence_reference }}" placeholder="Voucher, report, photo or document reference"></div><button class="btn btn--small">Save verification</button></form></div></details>@endforeach @if($pa['approvedForTracking']->isNotEmpty())<details class="disclosure"><summary>Add newly approved funds</summary><div class="disclosure__body"><form method="POST" action="{{ route('executive-follow-up.funds.store') }}">@csrf<div class="field"><label>Approved request</label><select name="approval_id" required>@foreach($pa['approvedForTracking'] as $approval)<option value="{{ $approval->id }}">{{ $approval->label() }} Ãƒâ€šÃ‚Â· UGX {{ $approval->amount() }}</option>@endforeach</select></div><div class="field"><label>Intended purpose</label><textarea name="intended_purpose" required></textarea></div><div class="field"><label>Follow-up due</label><input type="date" name="follow_up_due_on"></div><button class="btn">Begin follow-up</button></form></div></details>@elseif($pa['funds']->isEmpty())<p class="muted">No approved budget or purchase is waiting for follow-up.</p>@endif</section></aside>
            </div>
        </section>
    @elseif ($panel === 'finance-dashboard')
        @php
            $f = $data['financeDashboard'];
            $max = max(1, $f['monthly']->max(fn ($month) => max($month['income'], $month['expenses'])));
            $expenseMax = max(1, $f['expenseGroups']->max('amount') ?? 1);
            $hasCashMovement = $f['monthly']->contains(fn ($month) => $month['income'] > 0 || $month['expenses'] > 0);
        @endphp
        <section class="finance-dashboard">
            <div class="finance-kpis finance-kpis--legacy" aria-hidden="true">
                <article class="finance-kpi finance-kpi--income"><span>ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â€</span><div><small>Money received Ãƒâ€šÃ‚Â· {{ now()->format('F') }}</small><strong>UGX {{ number_format($f['income']) }}</strong><em>Posted income</em></div></article>
                <article class="finance-kpi finance-kpi--expense"><span>ÃƒÂ¢Ã¢â‚¬Â Ã¢â€žÂ¢</span><div><small>Money paid Ãƒâ€šÃ‚Â· {{ now()->format('F') }}</small><strong>UGX {{ number_format($f['expenses']) }}</strong><em>Posted expenses</em></div></article>
                <article class="finance-kpi finance-kpi--net"><span>ÃƒÂ¢Ã¢â‚¬â€Ã¢â‚¬Â </span><div><small>Net cash movement</small><strong>UGX {{ number_format($f['net']) }}</strong><em>{{ $f['net']>=0?'Positive position':'Spending exceeds receipts' }}</em></div></article>
                <article class="finance-kpi finance-kpi--pending"><span>ÃƒÂ¢Ã…â€œÃ¢â‚¬Å“</span><div><small>Pending approvals</small><strong>{{ $f['pending'] }}</strong><em>Awaiting decisions</em></div></article>
            </div>
            <div class="finance-kpis">
                <article class="finance-kpi finance-kpi--income"><span aria-hidden="true">&#8593;</span><div><small>Money received &middot; {{ now()->format('F') }}</small><strong>UGX {{ number_format($f['income']) }}</strong><em>Posted income</em></div></article>
                <article class="finance-kpi finance-kpi--expense"><span aria-hidden="true">&#8595;</span><div><small>Money paid &middot; {{ now()->format('F') }}</small><strong>UGX {{ number_format($f['expenses']) }}</strong><em>Posted expenses</em></div></article>
                <article class="finance-kpi finance-kpi--net"><span aria-hidden="true">&#9670;</span><div><small>Net cash movement</small><strong>UGX {{ number_format($f['net']) }}</strong><em>{{ $f['net']>=0?'Positive position':'Spending exceeds receipts' }}</em></div></article>
                <article class="finance-kpi finance-kpi--pending"><span aria-hidden="true">&#10003;</span><div><small>Pending approvals</small><strong>{{ $f['pending'] }}</strong><em>Awaiting decisions</em></div></article>
            </div>
            <div class="finance-main-grid">
                <section class="finance-chart-card finance-chart-card--cash"><div class="finance-panel-head"><div><span class="finance-panel-kicker">Six-month trend</span><h3>Cash movement</h3><p>Money received compared with money paid</p></div><span>Last 6 months</span></div>@if($hasCashMovement)<div class="finance-bars">@foreach($f['monthly'] as $m)<div class="finance-bar-group"><div class="finance-bar-pair"><i class="income" style="height:{{ max(3,$m['income']/$max*100) }}%" title="Income UGX {{ number_format($m['income']) }}"></i><i class="expense" style="height:{{ max(3,$m['expenses']/$max*100) }}%" title="Expenses UGX {{ number_format($m['expenses']) }}"></i></div><span>{{ $m['label'] }}</span></div>@endforeach</div><div class="finance-legend"><span><i class="income"></i>Money received</span><span><i class="expense"></i>Money paid</span></div>@else<div class="finance-empty"><span>ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â€</span><strong>Your cash trend will appear here</strong><p>Record income or expenses to begin building the six-month comparison.</p><a href="{{ route('finance.transactions.create') }}">Record first transaction</a></div>@endif</section>
                <section class="finance-chart-card finance-chart-card--spend"><div class="finance-panel-head"><div><span class="finance-panel-kicker">Expense insight</span><h3>Where money was spent</h3><p>Largest expense counterparties this month</p></div></div><div class="finance-distribution">@forelse($f['expenseGroups'] as $row)<div><span>{{ Str::limit($row['name'],28) }}</span><b><i style="width:{{ max(3,$row['amount']/$expenseMax*100) }}%"></i></b><strong>UGX {{ number_format($row['amount']) }}</strong></div>@empty<div class="finance-empty finance-empty--orange"><span>ÃƒÂ¢Ã¢â‚¬â€Ã…Â½</span><strong>No spending recorded yet</strong><p>Expense categories and suppliers will appear here after entries are posted.</p><a href="{{ route('finance.transactions.create') }}">Add an expense</a></div>@endforelse</div></section>
            </div>
            <div class="finance-position-grid">
                <article><span>Customer balances</span><strong>UGX {{ number_format($f['receivables']) }}</strong><a href="{{ route('finance.receivables.index') }}">Open receivables ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢</a></article>
                <article><span>Supplier balances</span><strong>UGX {{ number_format($f['payables']) }}</strong><a href="{{ route('finance.payables.index') }}">Open payables ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢</a></article>
                <article><span>Budget planned this month</span><strong>UGX {{ number_format($f['budgeted']) }}</strong><a href="{{ route('finance.budgets.index') }}">Open weekly budgets ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢</a></article>
            </div>
            <section class="finance-chart-card finance-recent"><div class="finance-panel-head"><div><h3>Recent transactions</h3><p>Latest entries in the finance register</p></div><a class="btn btn--quiet btn--inline" href="{{ route('finance.transactions.create') }}">Record transaction</a></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Description</th><th>Account</th><th>Type</th><th>Amount</th></tr></thead><tbody>@forelse($f['recent'] as $row)<tr><td>{{ $row->transaction_date->format('d M') }}</td><td>{{ $row->description }}</td><td>{{ $row->account }}</td><td>{{ Str::headline($row->type) }}</td><td><strong>UGX {{ number_format($row->amount) }}</strong></td></tr>@empty<tr><td colspan="5">No transactions recorded yet.</td></tr>@endforelse</tbody></table></div></section>
        </section>
    @elseif ($panel === 'production-overview')
        @php
            $po = $data['productionOverview'];
        @endphp
        <section class="production-overview">
            <div class="production-hero"><div><span class="finance-panel-kicker">Farm operations</span><h3>Production control centre</h3><p>Today's feed position and poultry-house records needing management attention.</p></div><a class="btn" href="{{ route('operations.reports') }}">Open production reports</a></div>
            <section class="feed-reminder {{ $po['feedShortageKg'] > 0 ? 'feed-reminder--attention' : 'feed-reminder--complete' }}"><div class="feed-reminder__icon" aria-hidden="true">&#9670;</div><div><span class="finance-panel-kicker">Daily feed reminder &middot; {{ now()->format('D, d M') }}</span><h3>{{ number_format($po['dailyFeedKg'], 2) }} kg required today</h3><p>Calculated for {{ number_format($po['totalBirds']) }} remaining birds at 125g per bird per day.</p></div><div class="feed-reminder__status"><small>Recorded today</small><strong>{{ number_format($po['recordedFeedKg'], 2) }} kg</strong><em>{{ $po['feedShortageKg'] > 0 ? number_format($po['feedShortageKg'], 2).' kg still to supply' : 'Daily requirement covered' }}</em></div></section>
            <section class="exec-panel production-feed-plan"><div class="section-heading"><div><p class="card-label">House-by-house monitoring</p><h3>Bird balance and estimated feed</h3></div><a href="{{ route('poultry.report.index') }}">Review workers' entries &rarr;</a></div><div class="feed-house-list">@forelse($po['feedPlan'] as $house)<a href="{{ route('batches.show',$house['batch']) }}" class="feed-house-row"><div><strong>{{ $house['house'] }}</strong><small>{{ $house['batch']->code }} &middot; {{ number_format($house['birds']) }} birds remaining</small></div><div class="feed-house-meter"><i style="width:{{ $house['percent'] }}%"></i></div><div><small>Estimated</small><strong>{{ number_format($house['requiredKg'],2) }} kg</strong></div><div class="{{ $house['shortageKg'] > 0 ? 'feed-short' : 'feed-covered' }}"><small>{{ $house['shortageKg'] > 0 ? 'Still needed' : 'Recorded' }}</small><strong>{{ number_format($house['shortageKg'] > 0 ? $house['shortageKg'] : $house['recordedKg'],2) }} kg</strong></div></a>@empty<div class="finance-empty"><span aria-hidden="true">&#9670;</span><strong>No active poultry houses</strong><p>Open a poultry batch to begin daily feed estimates.</p></div>@endforelse</div></section>
        </section>

    {{-- ============================================================
         Flocks. Trouble first, then the totals ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â a supervisor opening
         this at 6am wants to know what needs them, not the headline.
         ============================================================ --}}
    @elseif ($panel === 'flocks')
        @php
            $f = $data['flocks'];
        @endphp

        @if ($f['count'] === 0)
            <div class="card">
                <p class="card-label">Flocks</p>
                <p class="muted" style="margin:0 0 14px;">
                    No batches are open. Start one when your next delivery of birds
                    arrives ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â everything else in Poultry hangs off it.
                </p>
                @if ($user->canDo('batches', 'create'))
                    <a class="btn btn--inline" href="{{ route('batches.create') }}">New batch</a>
                @endif
            </div>
        @else
            @if ($f['attention']->isNotEmpty())
                <div class="card card--alarm">
                    <p class="card-label">Needs attention</p>

                    @foreach ($f['attention'] as $b)
                        <a class="attention-row" href="{{ route('batches.show', $b) }}">
                            <span>
                                <strong class="mono">{{ $b->code }}</strong>
                                @if ($b->name) <em>{{ $b->name }}</em> @endif
                            </span>
                            <span class="attention-why">
                                @if ($b->layingIsOverdue())
                                    Laying overdue by {{ abs($b->weeksToLay()) }} weeks
                                @else
                                    Mortality {{ $b->mortalityRate() }}% at {{ $b->ageInWeeks() }} weeks
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($f['comingSoon']->isNotEmpty())
                <div class="card">
                    <p class="card-label">Coming into lay</p>
                    @foreach ($f['comingSoon'] as $b)
                        <a class="attention-row" href="{{ route('batches.show', $b) }}">
                            <span>
                                <strong class="mono">{{ $b->code }}</strong>
                                @if ($b->name) <em>{{ $b->name }}</em> @endif
                            </span>
                            <span class="attention-why attention-why--soon">
                                First eggs in {{ $b->weeksToLay() }} {{ Str::plural('week', $b->weeksToLay()) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="grid">
                <div class="card">
                    <p class="card-label">Birds</p>
                    <span class="figure">{{ number_format($f['birds']) }}</span>
                    <span class="figure-unit">Alive now</span>
                </div>

                <div class="card">
                    <p class="card-label">Flocks</p>
                    <span class="figure">{{ $f['count'] }}</span>
                    <span class="figure-unit">Open batches</span>
                </div>

                <div class="card">
                    <p class="card-label">In lay</p>
                    <span class="figure">{{ $f['laying'] }}</span>
                    <span class="figure-unit">of {{ $f['count'] }} flocks</span>
                </div>

            </div>
        @endif

    {{-- ============================================================
         Approvals
         ============================================================ --}}
    @elseif ($panel === 'executive')
        @php
            $e = $data['executive'];
            $cashMax = max(1, $e['monthly']->max(fn ($month) => max($month['income'], $month['spending'])));
            $spendMax = max(1, $e['spendingByArea']->max('amount') ?? 1);
            $hasCashData = $e['monthly']->contains(fn ($month) => $month['income'] > 0 || $month['spending'] > 0);
        @endphp
        <section class="owner-dashboard">
            <header class="owner-hero">
                <div><span class="finance-panel-kicker">Owner's briefing &middot; {{ now()->format('F Y') }}</span><h3>The farm at a glance</h3><p>Financial position, production, livestock and decisions that need attention.</p></div>
                <div class="owner-hero__actions"><a class="btn btn--quiet" href="{{ route('account.edit') }}">Edit my account</a><a class="btn" href="{{ route('executive.index', ['screen' => 'kpis']) }}">Full analysis</a></div>
            </header>

            <div class="owner-kpis">
                <article class="owner-kpi owner-kpi--income"><span>&uarr;</span><div><small>Money received</small><strong>UGX {{ number_format($e['income']) }}</strong><em>This month</em></div></article>
                <article class="owner-kpi owner-kpi--expense"><span>&darr;</span><div><small>Money spent</small><strong>UGX {{ number_format($e['spending']) }}</strong><em>This month</em></div></article>
                <article class="owner-kpi owner-kpi--net"><span>&bull;</span><div><small>Net cash movement</small><strong>UGX {{ number_format($e['net']) }}</strong><em>{{ $e['net'] >= 0 ? 'Positive position' : 'Spending exceeds receipts' }}</em></div></article>
                <article class="owner-kpi owner-kpi--attention"><span>!</span><div><small>Pending decisions</small><strong>{{ $e['pending'] }}</strong><em>{{ $e['overdue'] ? $e['overdue'].' overdue' : 'Nothing overdue' }}</em></div></article>
            </div>

            <div class="owner-analysis-grid">
                <section class="owner-panel owner-panel--cash">
                    <div class="owner-panel__head"><div><span class="finance-panel-kicker">Six-month trend</span><h3>Income versus spending</h3></div><span>UGX</span></div>
                    @if($hasCashData)
                        <div class="owner-bars">@foreach($e['monthly'] as $month)<div class="owner-bar-group"><div><i class="income" style="height:{{ max(3, $month['income'] / $cashMax * 100) }}%" title="Income UGX {{ number_format($month['income']) }}"></i><i class="expense" style="height:{{ max(3, $month['spending'] / $cashMax * 100) }}%" title="Spending UGX {{ number_format($month['spending']) }}"></i></div><span>{{ $month['label'] }}</span></div>@endforeach</div>
                        <div class="owner-legend"><span><i class="income"></i>Income</span><span><i class="expense"></i>Spending</span></div>
                    @else<div class="owner-empty"><strong>No cash movement recorded</strong><p>The trend will build automatically as Finance posts transactions.</p></div>@endif
                </section>
                <section class="owner-panel">
                    <div class="owner-panel__head"><div><span class="finance-panel-kicker">Cost analysis</span><h3>Where money was spent</h3></div></div>
                    <div class="owner-spend-list">@forelse($e['spendingByArea'] as $row)<div><header><span>{{ Str::limit($row['name'], 30) }}</span><strong>UGX {{ number_format($row['amount']) }}</strong></header><i><b style="width:{{ max(3, $row['amount'] / $spendMax * 100) }}%"></b></i></div>@empty<div class="owner-empty"><strong>No spending posted this month</strong><p>Cost areas appear here when Finance records expenses.</p></div>@endforelse</div>
                </section>
            </div>

            <div class="owner-operation-grid">
                <article><span class="owner-operation-icon">&#129370;</span><div><small>Eggs collected today</small><strong>{{ number_format($e['eggsToday']) }}</strong><em>{{ number_format($e['eggsMonth'] / 30, 1) }} trays this month</em></div></article>
                <article><span class="owner-operation-icon">&#128020;</span><div><small>Birds on farm</small><strong>{{ number_format($e['birds']) }}</strong><em>Across active poultry batches</em></div></article>
                <article><span class="owner-operation-icon">&#127806;</span><div><small>Feed in stock</small><strong>{{ number_format($e['feedBalance'], 1) }} kg</strong><em>Recorded stock balance</em></div></article>
                <article><span class="owner-operation-icon">&#128062;</span><div><small>Other livestock</small><strong>{{ number_format($e['livestock']->sum()) }}</strong><em>{{ $e['livestock']->map(fn($count,$unit) => $count.' '.Str::plural($unit, $count))->implode(' / ') ?: 'No active animals recorded' }}</em></div></article>
            </div>

            <div class="owner-position-grid">
                <article><small>Customers owe the farm</small><strong>UGX {{ number_format($e['receivables']) }}</strong><a href="{{ route('finance.receivables.index') }}">Open receivables &rarr;</a></article>
                <article><small>Farm owes suppliers</small><strong>UGX {{ number_format($e['payables']) }}</strong><a href="{{ route('finance.payables.index') }}">Open payables &rarr;</a></article>
                <article><small>Purchases awaiting approval</small><strong>UGX {{ number_format($e['purchaseExposure']) }}</strong><a href="{{ route('approvals.index') }}">Review decisions &rarr;</a></article>
                <article class="{{ $e['withdrawals'] ? 'owner-position--alert' : '' }}"><small>Active egg withdrawals</small><strong>{{ $e['withdrawals'] }}</strong><a href="{{ route('vaccination-schedule.index') }}">Open health schedule &rarr;</a></article>
            </div>
        </section>
    @elseif ($panel === 'executive-legacy')
        @php($e=$data['executive'])
        <section class="executive-brief">
            <div class="executive-brief__head">
                <div><p class="card-label">Executive briefing Ãƒâ€šÃ‚Â· {{ now()->format('F Y') }}</p><h3>What needs your attention</h3></div>
                <a href="{{ route('executive.index',['screen'=>'kpis']) }}">View full analytics ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢</a>
            </div>
            <div class="exec-kpi-grid executive-brief__grid">
                <article class="exec-kpi exec-kpi--positive"><span class="exec-kpi__icon">ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Âµ</span><div><small>Money received</small><strong>UGX {{ number_format($e['income']) }}</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--negative"><span class="exec-kpi__icon">ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¸</span><div><small>Money paid</small><strong>UGX {{ number_format($e['spending']) }}</strong><em>This month</em></div></article>
                <article class="exec-kpi exec-kpi--{{ $e['pending'] ? 'warning' : 'positive' }}"><span class="exec-kpi__icon">ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦</span><div><small>Waiting for your review</small><strong>{{ $e['pending'] }}</strong><em>{{ $e['overdue'] ? $e['overdue'].' overdue' : 'Nothing overdue' }}</em></div></article>
                <article class="exec-kpi"><span class="exec-kpi__icon">ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â¤</span><div><small>Sent to Director</small><strong>{{ $e['sent'] }}</strong><em>Awaiting final decision</em></div></article>
                <article class="exec-kpi exec-kpi--warning"><span class="exec-kpi__icon">ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¹</span><div><small>Approval exposure</small><strong>UGX {{ number_format($e['purchaseExposure']) }}</strong><em>Submitted purchases</em></div></article>
                <article class="exec-kpi exec-kpi--{{ $e['withdrawals'] ? 'danger' : 'positive' }}"><span class="exec-kpi__icon">ÃƒÂ°Ã…Â¸Ã‚Â¥Ã…Â¡</span><div><small>Egg withdrawals</small><strong>{{ $e['withdrawals'] }}</strong><em>{{ $e['withdrawals'] ? 'Active restrictions' : 'No active restrictions' }}</em></div></article>
            </div>
            <div class="executive-brief__actions">
                <div><strong>{{ $e['pending'] ? 'Decisions are waiting.' : 'Your approval queue is clear.' }}</strong><span>{{ $e['pending'] ? 'Review supporting details, comment, approve or reject.' : 'There are no items requiring CEO action right now.' }}</span></div>
                <div class="form-actions"><a class="btn" href="{{ route('approvals.index') }}">Review approvals</a><a class="btn btn--quiet" href="{{ route('approvals.index',['status'=>'sent']) }}">Track Director queue</a></div>
            </div>
        </section>
    @elseif ($panel === 'approvals')
        <div class="card">
            <p class="card-label">Waiting for you</p>
            <span class="figure {{ ($data['approvals']['pending'] ?? 0) ? 'figure--alarm' : '' }}">{{ $data['approvals']['pending'] ?? 0 }}</span>
            <span class="figure-unit">pending approvals</span>
            @if(($data['approvals']['overdue'] ?? 0) > 0)<p>{{ $data['approvals']['overdue'] }} overdue</p>@endif
            <p><a class="btn" href="{{ route('approvals.index') }}">Open approval queue</a></p>
        </div>

    {{-- ============================================================
         Staff
         ============================================================ --}}
    @elseif ($panel === 'staff')
        <div class="grid">
            <div class="card">
                <p class="card-label">Staff</p>
                <span class="figure">{{ $data['staff']['active'] ?? 0 }}</span>
                <span class="figure-unit">On the register</span>
            </div>

            <div class="card">
                <p class="card-label">With a login</p>
                <span class="figure">{{ $data['staff']['withLogin'] ?? 0 }}</span>
                <span class="figure-unit">of {{ $data['staff']['active'] ?? 0 }} staff</span>
            </div>

            <div class="card">
                <p class="card-label">Never signed in</p>
                <span class="figure {{ ($data['staff']['neverSignedIn'] ?? 0) > 0 ? 'figure--alarm' : '' }}">{{ $data['staff']['neverSignedIn'] ?? 0 }}</span>
                <span class="figure-unit">
                    @if (($data['staff']['neverSignedIn'] ?? 0) > 0)
                        May need their password again
                    @else
                        Everyone has signed in
                    @endif
                </span>
            </div>

        </div>

    {{-- ============================================================
         HR KPI entry
         ============================================================ --}}
    @elseif ($panel === 'hr-kpis')
        <div class="card">
            <p class="card-label">HR KPI Dashboard</p>
            <p class="muted" style="margin:0 0 14px;">Attendance, performance, contracts, welfare and deductions in one place.</p>
            <a class="btn btn--inline" href="{{ route('hr.kpis.index') }}">Open KPI Dashboard</a>
        </div>

    {{-- ============================================================
         Stock
         ============================================================ --}}
    @elseif ($panel === 'stock')
        <div class="card"><div class="page-head page-head--split" style="margin:0"><div><p class="card-label">Inventory & Stock</p><h3>{{ $data['stock']['items'] }} registered items</h3><p class="muted">{{ $data['stock']['low'] }} at or below reorder level Ãƒâ€šÃ‚Â· {{ $data['stock']['feedLines'] }} feed stock lines</p></div><a class="btn" href="{{ route('inventory.index') }}">Open inventory</a></div></div>

    {{-- ============================================================
         Nobody should land on an empty screen.
         ============================================================ --}}
    @elseif ($panel === 'welcome')
        <div class="card">
            <p class="card-label">Nothing assigned yet</p>
            <p class="muted" style="margin:0;">
                Your account has no screens attached to it. Ask HR to check your
                role and which section you are posted to.
            </p>
        </div>

    {{-- ============================================================
         Shortcuts ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â the screens this person actually opens.
         ============================================================ --}}
    @elseif ($panel === 'shortcuts' && $data['shortcuts']->isNotEmpty())
        <h3 class="section-heading">Go to</h3>

        <div class="shortcut-grid">
            @foreach ($data['shortcuts'] as $m)
                <a class="shortcut" href="{{ $m->link ?? $m->url() }}">
                    <svg class="shortcut-icon" aria-hidden="true">
                        <use href="#i-{{ $m->icon ?? $m->iconName() }}"></use>
                    </svg>
                    <span>{{ $m->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

@endforeach

@endsection




