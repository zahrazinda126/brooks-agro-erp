{{--
    Built from the database, not written by hand. A person sees a link only
    if their role or department grants it, so the menu is itself a statement
    of their permissions.

    Two levels now. A group heading, then either a link or a heading that
    opens into more links:

        FINANCE
          Money In        ▸  Invoices, Receipts, Customers…
          The Books       ▸  Journal Entries, Cash Book…
          Payroll

    The second level exists because the Finance Manager's remit is about
    twenty-five screens. Flat, that is a wall of text where Cash Book and
    Customer Ledger look equally important and neither is findable.
--}}
@php
    $groups     = $user->sidebarModules();
    $currentKey = request()->route('key')
        ?? (request()->routeIs('hr.records.*') ? request()->route('module') : null)
        ?? (request()->routeIs('executive.*') ? request()->route('screen') : null)
        ?? (request()->routeIs('communications.*') ? 'messages' : null)
        ?? (request()->routeIs('dashboard') ? 'dashboard' : null)
        ?? (request()->routeIs('approvals.*') ? 'approvals' : null)
        ?? (request()->routeIs('employees.*') ? 'hr' : null)
        ?? (request()->routeIs('daily.*') ? 'egg-entry' : null)
        ?? (request()->routeIs('batches.*') ? 'batches' : null)
        ?? (request()->routeIs('collections.*') ? 'egg-inventory' : null)
        ?? (request()->routeIs('consumption.*') ? 'bird-consumption' : null)
        ?? (request()->routeIs('health.*') ? 'health' : null)
        ?? (request()->routeIs('mortality.assess.*') ? 'mortality-assess' : null)
        ?? (request()->routeIs('inventory.*') ? 'inventory' : null)
        ?? (request()->routeIs('feed-types.*') ? 'feed-types' : null)
        ?? (request()->routeIs('sales.*') ? 'sales' : null)
        ?? (request()->routeIs('finance.transactions.*') ? 'transactions' : null)
        ?? (request()->routeIs('finance.accounts.*') ? 'accounts' : null)
        ?? (request()->routeIs('finance.cash-book.*') ? 'cash-book' : null)
        ?? (request()->routeIs('finance.bank-book.*') ? 'bank-book' : null)
        ?? (request()->routeIs('finance.general-ledger.*') ? 'general-ledger' : null)
        ?? (request()->routeIs('finance.receivables.*') ? 'receivables' : null)
        ?? (request()->routeIs('finance.payables.*') ? 'payables' : null)
        ?? (request()->routeIs('finance.budgets.*') ? 'budget' : null)
        ?? (request()->routeIs('finance.assets.*') ? 'assets' : null)
        ?? (request()->routeIs('finance.farm.purchases*') ? 'purchases' : null)
        ?? (request()->routeIs('finance.farm.production*') ? 'production' : null)
        ?? (request()->routeIs('finance.farm.veterinary*') ? 'veterinary' : null)
        ?? (request()->routeIs('finance.reports.*') ? 'reports' : null)
        ?? (request()->routeIs('finance.admin.*') ? request()->route('screen') : null)
        ?? (request()->routeIs('payroll.*') ? 'payroll' : null)
        ?? (request()->routeIs('owner.customers') ? 'customers' : null)
        ?? (request()->routeIs('visitors.*') ? 'visitors' : null)
        ?? (request()->routeIs('poultry.performance.*') ? 'staff-performance' : null);
    if ($currentKey === 'kpis' && request()->routeIs('executive.*')) $currentKey = 'kpi-dashboards';
    if (request()->routeIs('operations.reports*')) $currentKey = 'reports';
    if (request()->routeIs('operations.units.*')) $currentKey = request()->route('unit');
    if (request()->routeIs('poultry.report.*')) $currentKey = 'reports';
    if (request()->routeIs('daily-reports.*')) $currentKey = 'daily-management-report';
    if (request()->routeIs('vaccination-schedule.*') || request()->routeIs('vaccination-records.*')) $currentKey = 'vaccination';
    if (request()->routeIs('department-budget.*')) $currentKey = 'department-budget';
    if (request()->routeIs('payroll.my-balance')) $currentKey = 'my-salary-balance';

    /*
     | HR has a focused, task-based menu rather than the generic module
     | catalogue.  It reflects the way the team works: employee records,
     | welfare, development, then reporting.  The checks still come from
     | the permission system, so holding the role alone never bypasses
     | access control.
     */
    $isHrManager = $user->role?->code === 'hr-manager'
        || $user->roles->contains('code', 'hr-manager');
    $isFinanceUser = ! $user->isDataEntryOnly() && ($user->role?->code === 'finance-manager'
        || $user->roles->contains('code', 'finance-manager')
        || $user->department?->code === 'finance'
        || $user->departments->contains('code', 'finance'));
    $isSalesManager = ! $user->isDataEntryOnly() && (
        $user->role?->code === 'sales-manager'
        || $user->roles->contains('code', 'sales-manager')
        || $user->department?->code === 'sales'
        || $user->departments->contains('code', 'sales')
        || $user->department?->code === 'div-sales'
        || $user->departments->contains('code', 'div-sales')
    );
    $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code', 'ceo');
    $isDirector = $user->role?->code === 'director' || $user->roles->contains('code', 'director');
    $isSystemAdmin = $user->role?->code === 'system-admin' || $user->roles->contains('code', 'system-admin');
    $isPersonalAssistant = $user->role?->code === 'personal-assistant'
        || $user->roles->contains('code', 'personal-assistant')
        || $user->role?->code === 'pa' || $user->roles->contains('code', 'pa');
    $isProductionManager = $user->role?->code === 'production-manager'
        || $user->roles->contains('code', 'production-manager')
        || (($user->role?->code === 'department-manager' || $user->roles->contains('code', 'department-manager'))
            && ($user->department?->code === 'production' || $user->departments->contains('code', 'production')));
    $isStoreManager = $user->role?->code === 'store-keeper'
        || $user->roles->contains('code', 'store-keeper');
    $feedStoreId = $isStoreManager ? \App\Models\InventoryStore::where('code', 'feed')->value('id') : null;
    $isCasualWorker = $user->isDataEntryOnly();
    $isAppointedSupervisor = \App\Models\SupervisorAssignment::where('supervisor_id',$user->id)
        ->where('is_active',true)->whereDate('starts_on','<=',today())
        ->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',today()))->exists();
    $isDailyReportManager = $user->roles->pluck('code')->push($user->role?->code)->filter()
        ->intersect(['production-manager','finance-manager','hr-manager','sales-manager'])->isNotEmpty();
    $casualUnits = collect([
        'poultry'=>['label'=>'🐔 Poultry Daily Entry','anchor'=>'poultry'],
        'goats'=>['label'=>'🐐 Goat Daily Entry','anchor'=>'goats'],
        'rabbits'=>['label'=>'🐇 Rabbit Daily Entry','anchor'=>'rabbits'],
        'dogs'=>['label'=>'🐕 Dog Daily Entry','anchor'=>'dogs'],
        'garden'=>['label'=>'🌱 Garden Daily Entry','anchor'=>'garden'],
    ])->only($user->assignedFarmUnits());
    $groupEmojis = [
        'Finance' => '💰', 'Reports' => '📈', 'Operations' => '🏢',
        'People' => '👥', 'Administration' => '⚙️', 'Stock' => '📦',
        'Poultry' => '🐔', 'Livestock' => '🐐',
    ];

    $ceoSections = [
        'Operational Reports' => [
            ['key' => 'reports', 'label' => 'Overall Production Report', 'page' => 'production'],
            ['key' => 'reports', 'label' => 'Poultry Unit Report', 'page' => 'poultry'],
            ['key' => 'reports', 'label' => 'Goat Unit Report', 'page' => 'goats'],
            ['key' => 'reports', 'label' => 'Rabbit Unit Report', 'page' => 'rabbits'],
            ['key' => 'reports', 'label' => 'Dog Unit Report', 'page' => 'dogs'],
            ['key' => 'reports', 'label' => 'Garden Unit Report', 'page' => 'garden'],
        ],
        '📊 Executive View' => [
            ['key' => 'kpi-dashboards', 'label' => 'KPIs & Analytics'],
            ['key' => 'reports', 'label' => 'Financial & Executive Reports'],
            ['key' => 'sales', 'label' => 'Sales Analysis', 'screen' => 'reports'],
        ],
        '✅ Decisions' => [
            ['key' => 'assign-supervisors', 'label' => 'Daily Supervisors'],
            ['key' => 'approvals', 'label' => 'Pending Approvals'],
            ['key' => 'approvals', 'label' => 'Sent to Director', 'status' => 'sent'],
            ['key' => 'approvals', 'label' => 'Rejected Requests', 'status' => 'rejected'],
        ],
        '🏢 Oversight' => [
            ['key' => 'budget', 'label' => 'Weekly Budgets'],
            ['key' => 'sales', 'label' => 'Selling Prices', 'screen' => 'setup'],
            ['key' => 'purchases', 'label' => 'Purchases'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
            ['key' => 'hr', 'label' => 'Employees'],
            ['key' => 'customers', 'label' => 'Customer Details'],
        ],
        '💬 Communication' => [
            ['key' => 'messages', 'label' => 'Messages'], ['key' => 'notifications', 'label' => 'Notifications'],
        ],
        '⚙️ Administration' => [
            ['key' => 'visitors', 'label' => 'Visitor Register'],
            ['key' => 'security', 'label' => 'Users & Permissions'],
            ['key' => 'settings', 'label' => 'System Settings'],
        ],
    ];

    $directorSections = [
        'Operational Reports' => [
            ['key' => 'reports', 'label' => 'Overall Production Report', 'page' => 'production'],
            ['key' => 'reports', 'label' => 'Poultry Unit Report', 'page' => 'poultry'],
            ['key' => 'reports', 'label' => 'Goat Unit Report', 'page' => 'goats'],
            ['key' => 'reports', 'label' => 'Rabbit Unit Report', 'page' => 'rabbits'],
            ['key' => 'reports', 'label' => 'Dog Unit Report', 'page' => 'dogs'],
            ['key' => 'reports', 'label' => 'Garden Unit Report', 'page' => 'garden'],
        ],
        '📊 Board View' => [
            ['key' => 'kpi-dashboards', 'label' => 'KPIs & Analytics'],
            ['key' => 'reports', 'label' => 'Financial & Executive Reports'],
        ],
        '✅ Final Decisions' => [
            ['key' => 'directives', 'label' => 'Directives & Tracking'],
            ['key' => 'approvals', 'label' => 'Pending Final Approvals'],
        ],
        '🏛️ Governance' => [
            ['key' => 'budget', 'label' => 'Approved Budgets'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
        ],
        'People & Customers' => [
            ['key' => 'hr', 'label' => 'Workers & Staff Details'],
            ['key' => 'customers', 'label' => 'Customer Details'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Announcements'],
            ['key' => 'notifications', 'label' => 'Notifications'],
        ],
        '⚙️ Administration' => [
            ['key' => 'visitors', 'label' => 'Visitor Register'],
            ['key' => 'security', 'label' => 'Users & Permissions'],
            ['key' => 'settings', 'label' => 'System Settings'],
        ],
    ];

    $assistantSections = [
        'Director Follow-up' => [
            ['key' => 'directives', 'label' => 'Directives & Feedback'],
            ['key' => 'approvals', 'label' => 'Approved Money Tracker', 'status' => 'approved'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Announcements'],
            ['key' => 'notifications', 'label' => 'Notifications'],
        ],
        'Reports & Briefing' => [
            ['key' => 'reports', 'label' => 'Executive Reports'],
            ['key' => 'pa-finance-reports', 'label' => 'Financial Reports'],
            ['key' => 'pa-profitability-reports', 'label' => 'Unit Costs & Profitability'],
            ['key' => 'pa-sales-reports', 'label' => 'Sales Report'],
            ['key' => 'pa-hr-reports', 'label' => 'HR Reports'],
            ['key' => 'pa-production-reports', 'label' => 'Overall Production Report'],
            ['key' => 'pa-poultry-reports', 'label' => 'Poultry Unit Report'],
            ['key' => 'pa-goat-reports', 'label' => 'Goat Unit Report'],
            ['key' => 'pa-rabbit-reports', 'label' => 'Rabbit Unit Report'],
            ['key' => 'pa-dog-reports', 'label' => 'Dog Unit Report'],
            ['key' => 'pa-garden-reports', 'label' => 'Garden Unit Report'],
        ],
        'Front Office' => [
            ['key' => 'visitors', 'label' => 'Visitor Register'],
        ],
    ];

    $productionSections = [
        'Monitor' => [
            ['key'=>'batches', 'label'=>'Poultry Daily Records', 'page'=>'poultry-report'],
            ['key'=>'vaccination', 'label'=>'Poultry Vaccination Schedule', 'page'=>'schedule'],
            ['key'=>'mortality-assess', 'label'=>'Mortality Examination'],
            ['key'=>'staff-performance', 'label'=>'Attendant Performance'],
            ['key'=>'feed-received', 'label'=>'Feeds Verification'],
        ],
        'Farm Units' => [
            ['key'=>'inventory', 'label'=>'Inventory & Stores'],
            ['key'=>'batches', 'label'=>'Poultry'],
            ['key'=>'batches', 'label'=>'Houses & Worker Assignment', 'page'=>'houses'],
            ['key'=>'goats', 'label'=>'Goats'],
            ['key'=>'rabbits', 'label'=>'Rabbits'],
            ['key'=>'dogs', 'label'=>'Dogs'],
            ['key'=>'garden', 'label'=>'Garden'],
        ],
        'Plan & Resources' => [
            ['key'=>'budget', 'label'=>'Weekly Unit Budget'],
        ],
        'Reports' => [
            ['key'=>'reports', 'label'=>'Production Reports'],
            ['key'=>'batches', 'label'=>'Poultry Report', 'page'=>'poultry-report'],
            ['key'=>'vaccination', 'label'=>'Poultry Vaccination Report', 'page'=>'records'],
            ['key'=>'goats', 'label'=>'Goat Report', 'page'=>'export'],
            ['key'=>'rabbits', 'label'=>'Rabbit Report', 'page'=>'export'],
            ['key'=>'dogs', 'label'=>'Dog Report', 'page'=>'export'],
            ['key'=>'garden', 'label'=>'Garden Report', 'page'=>'export'],
        ],
        'Communication' => [
            ['key'=>'messages', 'label'=>'Messages'],
        ],
    ];
    $hrSections = [
        '👥 Employees' => [
            ['key' => 'hr', 'label' => 'Employee Register'],
            ['key' => 'attendance', 'label' => 'Attendance'],
            ['key' => 'leave', 'label' => 'Leave'],
            ['key' => 'overtime', 'label' => 'Overtime'],
            ['key' => 'contracts', 'label' => 'Contracts'],
        ],
        '❤️ Welfare' => [
            ['key' => 'budget', 'label' => 'Weekly Welfare Budget'],
            ['key' => 'advances', 'label' => 'Salary Advance'],
            ['key' => 'welfare', 'label' => 'Welfare'],
            ['key' => 'employee-medical', 'label' => 'Medical Records'],
            ['key' => 'discipline', 'label' => 'Disciplinary'],
        ],
        '💰 Payroll' => [
            ['key' => 'payroll', 'label' => 'Payroll'],
        ],
        '🚚 Administration & Fleet' => [
            ['key' => 'fleet', 'label' => 'Daily Fleet Management'],
        ],
        '📚 Development' => [
            ['key' => 'training', 'label' => 'Training'],
            ['key' => 'staff-performance', 'label' => 'Performance Reviews'],
        ],
        '📈 Reports' => [
            ['key' => 'reports', 'label' => 'Reports'],
            ['key' => 'kpi-dashboards', 'label' => 'KPI Dashboard'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Updates'],
        ],
    ];

    $financeSections = [
        'Accounts' => [
            ['key' => 'accounts', 'label' => 'Accounts'],
        ],
        '💵 Daily Operations' => [
            ['key' => 'transactions', 'label' => 'Data Entry & Register'],
        ],
        'Cash & Banking' => [
            ['key' => 'cash-book', 'label' => 'Cash Book'],
            ['key' => 'bank-book', 'label' => 'Bank Book'],
        ],
        'Customers' => [
            ['key' => 'customers', 'label' => 'Customer Accounts', 'view' => 'accounts'],
            ['key' => 'customers', 'label' => 'Add Customer', 'view' => 'add'],
            ['key' => 'customers', 'label' => 'Customer Analysis', 'view' => 'analysis'],
            ['key' => 'receivables', 'label' => 'Balances & Prepayments'],
        ],
        'Suppliers' => [
            ['key' => 'payables', 'label' => 'Supplier Bills & Balances'],
        ],
        'Farm Costs' => [
            ['key' => 'purchases', 'label' => 'Farm Purchases'],
            ['key' => 'production', 'label' => 'Production Costs'],
            ['key' => 'veterinary', 'label' => 'Veterinary Costs'],
        ],
        'Inventory' => [
            ['key' => 'inventory', 'label' => 'Inventory & Stores'],
        ],
        '📒 Accounting' => [
            ['key' => 'general-ledger', 'label' => 'General Ledger'],
            ['key' => 'payroll', 'label' => 'Payroll'],
            ['key' => 'assets', 'label' => 'Assets'],
            ['key' => 'chart-of-accounts', 'label' => 'Finance Setup'],
            ['key' => 'department-budget', 'label' => 'Finance Department Draft'],
            ['key' => 'budget', 'label' => 'Combined Weekly Budget'],
            ['key' => 'sales', 'label' => 'Selling Prices', 'screen' => 'setup'],
        ],
        '📈 Reports' => [
            ['key' => 'reports', 'label' => 'Financial Reports'],
        ],
        '⚙️ Administration' => [
            ['key' => 'approvals', 'label' => 'Approval Tracking'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Updates'],
        ],
    ];

    $salesSections = [
        '🧾 Daily Sales' => [
            ['key'=>'sales','label'=>'Record Daily Sale','screen'=>'sale'],
        ],
        'Inventory' => [
            ['key'=>'sales','label'=>'Inventory','screen'=>'inventory'],
        ],
        '📈 Reports' => [
            ['key'=>'sales','label'=>'Sales Report','screen'=>'reports'],
            ['key'=>'sales','label'=>'Sales Analysis','screen'=>'analysis'],
        ],
        'Communication' => [
            ['key'=>'messages','label'=>'Messages & Updates'],
        ],
    ];

    /* A module can expose several destinations (for example Sales entry and
       Sales reports). Match the complete destination so only the link for the
       page being viewed is highlighted and expanded. */
    $navItemIsActive = function (array $item) use ($currentKey): bool {
        if (isset($item['page'])) {
            $page = $item['page'];
            if ($page === 'production') return request()->routeIs('operations.reports*');
            if ($page === 'poultry') return request()->routeIs('poultry.report.*');
            if (in_array($page, ['goats', 'rabbits', 'dogs', 'garden'], true)) {
                return request()->routeIs('operations.units.report') && request()->route('unit') === $page;
            }
            if ($page === 'poultry-report') return request()->routeIs('poultry.report.*');
            if ($page === 'houses') return request()->routeIs('poultry-houses.*');
            if ($page === 'export') {
                return request()->routeIs('operations.units.report') && request()->route('unit') === ($item['key'] ?? null);
            }
        }

        if (($item['key'] ?? null) !== $currentKey) return false;

        foreach (['screen', 'status', 'view', 'type'] as $parameter) {
            if (array_key_exists($parameter, $item)
                && (string) request($parameter, request()->route($parameter)) !== (string) $item[$parameter]) {
                return false;
            }
        }

        /* These screens use a default when the query parameter is omitted. */
        if (($item['key'] ?? null) === 'sales' && !isset($item['screen'])) {
            return request('screen', 'sale') === 'sale';
        }

        return true;
    };

    /* Keep the rail deliberately small. Related screens remain available in
       the contextual navigation instead of becoming separate rail entries. */
    $productionSections['Farm Units'] = array_merge($productionSections['Farm Units'], $productionSections['Plan & Resources']);
    unset($productionSections['Plan & Resources']);

    $hrSections = [
        'People & Payroll' => array_merge($hrSections['👥 Employees'], $hrSections['💰 Payroll']),
        'Welfare & Development' => array_merge($hrSections['❤️ Welfare'], $hrSections['📚 Development']),
        'Administration & Fleet' => $hrSections['🚚 Administration & Fleet'],
        'Reports' => $hrSections['📈 Reports'],
        'Communication' => $hrSections['Communication'],
    ];

    $ceoSections['Oversight & Administration'] = array_merge($ceoSections['🏢 Oversight'], $ceoSections['⚙️ Administration']);
    unset($ceoSections['🏢 Oversight'], $ceoSections['⚙️ Administration']);
    $directorSections['Governance & Administration'] = array_merge($directorSections['🏛️ Governance'], $directorSections['⚙️ Administration']);
    unset($directorSections['🏛️ Governance'], $directorSections['⚙️ Administration']);
@endphp

<nav class="drawer" id="drawer" data-open="false" aria-label="Main menu">

    <div class="brand">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark">
        @endif
        <span class="brand-name">Brooks Agro Farm</span>
    </div>

    @if(!$isStoreManager && ($isAppointedSupervisor || $isSalesManager || $isProductionManager))
        <a class="nav-link nav-link--supplementary" href="{{ route('supervision.index') }}" @if(request()->routeIs('supervision.*')) aria-current="page" @endif><span>Daily Supervisor</span></a>
    @endif

    @if(!$isStoreManager && ($user->canAccess('vaccination') || $isProductionManager || $isCeo || $isDirector))
        <a class="nav-link nav-link--supplementary" href="{{ route('vaccination-schedule.index') }}" @if(request()->routeIs('vaccination-schedule.*')) aria-current="page" @endif><span>Vaccination Schedule</span></a>
    @endif

    @if(!$isStoreManager && ($isCeo || $isDirector || $isPersonalAssistant || $isDailyReportManager))
        <a class="nav-link nav-link--supplementary" href="{{ route('daily-reports.index') }}" @if(request()->routeIs('daily-reports.*')) aria-current="page" @endif><span>Daily Management Report</span></a>
    @endif

    @if ($isCeo || $isDirector)
        <div class="hr-nav" aria-label="{{ $isCeo ? 'CEO' : 'Director' }} navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>🏠 Dashboard</span>
            </a>
            @foreach(($isCeo ? $ceoSections : $directorSections) as $section => $items)
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif>
                    <summary>{{ $section }}</summary>
                    @foreach($items as $item)
                        @continue(in_array($item['key'], ['settings', 'notifications'], true))
                        @if($user->canAccess($item['key']) || in_array($item['key'], ['customers', 'security', 'settings', 'visitors', 'directives'], true) || ($isCeo && $item['key'] === 'sales'))
                            @php
                                $href = match ($item['key']) {
                                    'approvals' => route('approvals.index', isset($item['type']) ? ['type'=>$item['type']] : (isset($item['status']) ? ['status'=>$item['status']] : [])),
                                    'assign-supervisors' => route('supervision.index'),
                                    'kpi-dashboards' => route('executive.index', ['screen'=>'kpis']),
                                    'reports' => match($item['page'] ?? 'executive') {
                                        'production' => route('operations.reports'),
                                        'poultry' => route('poultry.report.index'),
                                        'goats','rabbits','dogs','garden' => route('operations.units.report',['unit'=>$item['page']]),
                                        default => route('executive.index', ['screen'=>'reports']),
                                    },
                                    'messages' => route('communications.index'),
                                    'notifications', 'security', 'settings' => route('executive.index', ['screen'=>$item['key']]),
                                    'visitors' => route('visitors.index'),
                                    'hr' => route('employees.index'),
                                    'customers' => route('owner.customers'),
                                    'directives' => route('directives.index'),
                                    'sales' => route('sales.index', ['screen' => $item['screen'] ?? 'setup']),
                                    'budget' => route('finance.budgets.index'),
                                    'purchases' => route('finance.farm.purchases'),
                                    'assets' => route('finance.assets.index'),
                                    'audit' => route('finance.admin.index', ['screen' => 'audit']),
                                    'attendance','welfare','advances' => route('hr.records.index',['module'=>$item['key']]),
                                    'staff-performance' => route('poultry.performance.index'),
                                    default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                                };
                            @endphp
                            @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $item['label'] }}</span></a>@endif
                        @endif
                    @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isPersonalAssistant)
        <div class="hr-nav" aria-label="Personal Assistant navigation">
            <a class="nav-link" href="{{ route('dashboard') }}"><span>Dashboard</span></a>
            @foreach($assistantSections as $section => $items)
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif><summary>{{ $section }}</summary>
                @foreach($items as $item)
                    @continue(in_array($item['key'], ['settings', 'notifications'], true))
                    @if($user->canAccess($item['key']) || $item['key'] === 'directives' || str_starts_with($item['key'], 'pa-'))
                        @php $href = match($item['key']) {
                            'messages' => route('communications.index'),
                            'dashboard' => route('dashboard'), 'directives' => route('directives.index'),
                            'reports', 'notifications' => route('executive.index',['screen'=>$item['key']]),
                            'pa-finance-reports' => route('finance.reports.index'),
                            'pa-profitability-reports' => route('finance.reports.profitability'),
                            'pa-sales-reports' => route('sales.index',['screen'=>'reports']),
                            'pa-hr-reports' => route('hr.reports.index'),
                            'pa-production-reports' => route('operations.reports'),
                            'pa-poultry-reports' => route('poultry.report.index'),
                            'pa-goat-reports' => route('operations.units.report',['unit'=>'goats']),
                            'pa-rabbit-reports' => route('operations.units.report',['unit'=>'rabbits']),
                            'pa-dog-reports' => route('operations.units.report',['unit'=>'dogs']),
                            'pa-garden-reports' => route('operations.units.report',['unit'=>'garden']),
                            'approvals' => route('approvals.index',['status'=>$item['status'] ?? 'pending']),
                            'visitors' => route('visitors.index'),
                            default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                        }; @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $item['label'] }}</span></a>@endif
                    @endif
                @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isProductionManager)
        <div class="hr-nav" aria-label="Production Manager navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif><svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>🏠 Dashboard</span></a>
            @foreach($productionSections as $section => $items)
                @continue(empty($items))
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif><summary>{{ $section }}</summary>
                @foreach($items as $item)
                    @continue(in_array($item['key'], ['settings', 'notifications'], true))
                    @if($user->canAccess($item['key']))
                        @php
                            $href = match($item['key']) {
                                'egg-entry','feed-consumption','mortality-entry' => route('poultry.report.index'),
                                'batches' => match($item['page'] ?? null) {
                                    'poultry-report' => route('poultry.report.index'),
                                    'houses' => route('poultry-houses.index'),
                                    default => route('batches.index'),
                                },
                                'egg-inventory' => route('collections.index'),
                                'bird-consumption' => route('consumption.index'),
                                'health' => route('health.index'),
                                'mortality-assess' => route('mortality.assess.index'),
                                'feed-types' => route('feed-types.index'),
                                'poultry' => route('batches.index'),
                                'goats','rabbits','dogs','garden' => ($item['page'] ?? null)==='export'
                                    ? route('operations.units.report',['unit'=>$item['key']])
                                    : (isset($item['section']) ? route('operations.livestock.section',['unit'=>$item['key'],'section'=>$item['section']]) : route('operations.units.index',array_filter(['unit'=>$item['key'],'type'=>$item['type']??null,'view'=>$item['view']??null]))),
                                'production' => route('finance.farm.production'),
                                'budget' => route('production.finance-draft.index'),
                                'veterinary' => route('finance.farm.veterinary'),
                                'attendance' => route('hr.records.index',['module'=>'attendance']),
                                'staff-performance','kpi-dashboards' => route('poultry.performance.index'),
                                'reports' => route('operations.reports'),
                                'feed-received' => route('operations.feed-stock.index'),
                                'vaccination' => route('vaccination-schedule.index').(($item['page']??null)==='records'?'#vaccination-records':'#vaccination-schedule'),
                                'approvals' => route('approvals.index'),
                                'messages' => route('communications.index'),
                                default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                            };
                        @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $item['label'] }}</span></a>@endif
                    @endif
                @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isHrManager || $isFinanceUser || $isSalesManager)
        <div class="hr-nav" aria-label="{{ $isHrManager ? 'HR navigation' : ($isSalesManager ? 'Sales navigation' : 'Finance navigation') }}">
            <a class="nav-link" href="{{ route('dashboard') }}"
               @if ($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg>
                <span>Dashboard</span>
            </a>

            @foreach (($isHrManager ? $hrSections : ($isSalesManager ? $salesSections : $financeSections)) as $section => $items)
                @continue(empty($items))
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif>
                    <summary>{{ $section }}</summary>
                    @foreach ($items as $item)
                        @continue(in_array($item['key'], ['settings', 'notifications'], true))
                        @if ($user->canAccess($item['key']) || ($isSalesManager && $item['key'] === 'sales') || in_array($item['key'], ['department-budget','customers'], true) || ($isFinanceUser && in_array($item['key'], ['sales','reports','accounts'], true)))
                            @php
                                $href = $isSalesManager
                                    ? match ($item['key']) {
                                        'sales' => route('sales.index', ['screen' => $item['screen'] ?? 'sale']),
                                        'customers' => route('owner.customers', ['view'=>$item['view'] ?? 'accounts']).(($item['view'] ?? null)==='analysis' ? '#customer-analysis' : ''),
                                        'department-budget' => route('department-budget.index'),
                                        'messages' => route('communications.index'),
                                        default => route('module.placeholder', ['key' => $item['key']]),
                                    }
                                    : ($isHrManager
                                    ? match ($item['key']) {
                                        'hr' => route('employees.index'),
                                        'reports' => route('hr.reports.index'),
                                        'kpi-dashboards' => route('hr.kpis.index'),
                                        'staff-performance' => route('poultry.performance.index'),
                                        'payroll' => route('payroll.index'),
                                        'budget' => route('hr.welfare-budget.index'),
                                        'fleet' => route('hr.fleet.index'),
                                        'messages' => route('communications.index'),
                                        default => route('hr.records.index', ['module' => $item['key']]),
                                    }
                                    : match ($item['key']) {
                                        'transactions' => route('finance.transactions.index'),
                                        'accounts' => route('finance.accounts.index'),
                                        'inventory' => route('inventory.index'),
                                        'customers' => route('owner.customers', ['view'=>$item['view'] ?? 'accounts']).(($item['view'] ?? null)==='analysis' ? '#customer-analysis' : ''),
                                        'general-ledger' => route('finance.general-ledger.index'),
                                        'receivables' => route('finance.receivables.index'),
                                        'payables' => route('finance.payables.index'),
                                        'budget' => route('finance.budgets.index'),
                                        'department-budget' => route('department-budget.index'),
                                        'assets' => route('finance.assets.index'),
                                        'cash-book' => route('finance.cash-book.index'),
                                        'bank-book' => route('finance.bank-book.index'),
                                        'payroll' => route('payroll.index'),
                                        'purchases' => route('finance.farm.purchases'),
                                        'production' => route('finance.farm.production'),
                                        'veterinary' => route('finance.farm.veterinary'),
                                        'reports' => route('finance.reports.index'),
                                        'sales' => route('sales.index', ['screen' => $item['screen'] ?? 'setup']),
                                        'kpi-dashboards' => route('finance.reports.kpis'),
                                        'profit-loss', 'balance-sheet', 'cash-flow', 'trial-balance' => route('finance.reports.statement', ['report' => $item['key']]),
                                        'approvals' => route('approvals.index'),
                                        'messages' => route('communications.index'),
                                        'audit', 'chart-of-accounts', 'finance-periods', 'finance-accounts', 'payment-methods', 'document-numbering', 'tax-settings', 'budget-settings', 'finance-imports' => route('finance.admin.index', ['screen' => $item['key']]),
                                        default => route('module.placeholder', ['key' => $item['key']]),
                                    });
                            @endphp
                            <a class="nav-link"
                               href="{{ $href }}"
                               @if ($navItemIsActive($item)) aria-current="page" @endif>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isStoreManager)
        <div class="hr-nav" aria-label="Farm Store Manager navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>Dashboard</span>
            </a>
            <details class="hr-nav-section" @if(request()->routeIs('operations.feed-stock.*') || (request()->routeIs('inventory.*') && request('action', 'received')==='received')) open @endif>
                <summary>Receive</summary>
                <a class="nav-link" href="{{ route('operations.feed-stock.index') }}" @if(request()->routeIs('operations.feed-stock.*')) aria-current="page" @endif><span>Feeds</span></a>
                <a class="nav-link" href="{{ route('inventory.index', ['screen'=>'movement', 'action'=>'received']) }}" @if(request()->routeIs('inventory.*') && request('action', 'received')==='received') aria-current="page" @endif><span>Other Stock</span></a>
            </details>
            <details class="hr-nav-section" @if(request()->routeIs('inventory.*') && in_array(request('action'),['issued','farm_use'],true)) open @endif>
                <summary>Issue</summary>
                <a class="nav-link" href="{{ route('inventory.index', array_filter(['store'=>$feedStoreId, 'screen'=>'movement', 'action'=>'issued'])) }}" @if(request()->routeIs('inventory.*') && request('action')==='issued' && request('store')==$feedStoreId) aria-current="page" @endif><span>Feeds for Mixing</span></a>
                <a class="nav-link" href="{{ route('inventory.index', ['screen'=>'movement', 'action'=>'issued']) }}" @if(request()->routeIs('inventory.*') && request('action')==='issued' && request('store')!=$feedStoreId) aria-current="page" @endif><span>Other Stock</span></a>
                <a class="nav-link" href="{{ route('inventory.index', ['screen'=>'movement', 'action'=>'farm_use']) }}" @if(request()->routeIs('inventory.*') && request('action')==='farm_use') aria-current="page" @endif><span>Farm Consumption</span></a>
            </details>
        </div>
    @elseif ($isCasualWorker)
        <div class="hr-nav" aria-label="Casual worker navigation">
            <section class="hr-nav-section" aria-label="Assigned daily work">
                <h2>🗓️ My Assigned Work</h2>
                @forelse($casualUnits as $code=>$item)
                    <a class="nav-link" href="{{ route('daily.index') }}#{{ $item['anchor'] }}"><span>{{ $item['label'] }}</span></a>
                @empty
                    <p class="nav-empty">No farm unit is assigned. Ask HR to assign Poultry, Goats, Rabbits, Dogs, or Garden.</p>
                @endforelse
            </section>
            @if($user->canAccess('messages'))
            <section class="hr-nav-section" aria-label="Worker updates"><h2>💬 Updates</h2>
                @if($user->canAccess('messages'))<a class="nav-link" href="{{ route('communications.index') }}"><span>Messages</span></a>@endif
            </section>
            @endif
        </div>
    @else
        @if ($user->canAccess('dashboard'))
            <a class="nav-link" href="{{ route('dashboard') }}"
               @if ($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg>
                <span>🏠 Dashboard</span>
            </a>
        @endif

        <div class="nav-filter">
            <input type="search"
                   id="navFilter"
                   placeholder="Find a screen"
                   aria-label="Find a screen"
                   autocomplete="off">
        </div>

        <div id="navGroups">
        @forelse ($groups as $groupName => $modules)
            @php
                $holdsCurrent = $modules->contains(function ($m) use ($currentKey) {
                    return $m->key === $currentKey
                        || $m->children->contains('key', $currentKey);
                });
            @endphp

            <details class="nav-group"
                     data-group="{{ Str::slug($groupName) }}"
                     @if ($holdsCurrent) open @endif>

                <summary>
                    <span>{{ $groupEmojis[$groupName] ?? '📌' }} {{ $groupName }}</span>
                    <em>{{ $modules->count() }}</em>
                </summary>

                @foreach ($modules as $module)
                    @continue(in_array($module->key, ['settings', 'notifications'], true))

                    @if ($module->children->isNotEmpty())
                        @php
                            $childCurrent = $module->children->contains('key', $currentKey);
                        @endphp

                        {{-- A heading that opens into more links. --}}
                        <details class="nav-sub"
                                 data-group="sub-{{ $module->key }}"
                                 @if ($childCurrent) open @endif>

                            <summary>
                                <svg class="nav-icon" aria-hidden="true">
                                    <use href="#i-{{ $module->iconName() }}"></use>
                                </svg>
                                <span>{{ $module->name }}</span>
                                <em>{{ $module->children->count() }}</em>
                            </summary>

                            {{-- A parent that is also a screen keeps its own link. --}}
                            @if (! $module->isContainer())
                                <a class="nav-link nav-link--child"
                                   href="{{ $module->url() }}"
                                   data-name="{{ Str::lower($module->navigationLabel()) }}"
                                   @if ($module->key === $currentKey) aria-current="page" @endif>
                                    <span>{{ $module->navigationLabel() }} overview</span>
                                </a>
                            @endif

                            @foreach ($module->children as $child)
                                @continue(in_array($child->key, ['settings', 'notifications'], true))
                                <a class="nav-link nav-link--child"
                                   href="{{ $child->url() }}"
                                   data-name="{{ Str::lower($child->navigationLabel()) }}"
                                   @if ($child->key === $currentKey) aria-current="page" @endif>
                                    <span>{{ $child->navigationLabel() }}</span>
                                </a>
                            @endforeach
                        </details>

                    @else
                        <a class="nav-link"
                           href="{{ $module->url() }}"
                           data-name="{{ Str::lower($module->navigationLabel()) }}"
                           @if ($module->key === $currentKey) aria-current="page" @endif>
                            <svg class="nav-icon" aria-hidden="true">
                                <use href="#i-{{ $module->iconName() }}"></use>
                            </svg>
                            <span>{{ $module->navigationLabel() }}</span>
                        </a>
                    @endif

                @endforeach
            </details>
        @empty
            <p class="nav-empty">
                No screens have been assigned to your account yet. Ask HR to check your role.
            </p>
        @endforelse
        </div>

        <p class="nav-none" id="navNone" hidden>Nothing matches that.</p>
    @endif

    @php
        $sidebarUnread = \App\Models\Communication::where('recipient_id', $user->id)->whereNull('read_at')->count();
        $settingsHref = ($isCeo || $isDirector || $isSystemAdmin)
            ? route('executive.index', ['screen' => 'settings'])
            : route('account.edit');
    @endphp
    @if($user->employee_id)
        <a class="nav-link" href="{{ route('payroll.my-balance') }}" @if($currentKey==='my-salary-balance') aria-current="page" @endif>
            <span>My salary balance</span>
        </a>
    @endif
    <div class="sidebar-utilities" aria-label="Notifications and settings">
        <a href="{{ route('communications.index') }}" class="sidebar-utility" title="Notifications" aria-label="Notifications{{ $sidebarUnread ? ': '.$sidebarUnread.' unread' : '' }}" @if(request()->routeIs('communications.*')) aria-current="page" @endif>
            <svg aria-hidden="true"><use href="#i-bell"></use></svg>
            @if($sidebarUnread)<b>{{ $sidebarUnread > 99 ? '99+' : $sidebarUnread }}</b>@endif
        </a>
        <a href="{{ $settingsHref }}" class="sidebar-utility" title="Settings" aria-label="Settings" @if(request()->routeIs('account.*') || request()->routeIs('executive.*') && request()->route('screen') === 'settings') aria-current="page" @endif>
            <svg aria-hidden="true"><use href="#i-gear"></use></svg>
        </a>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="nav-signout">
        @csrf
        <button type="submit" class="nav-link nav-link--button">
            <svg class="nav-icon" aria-hidden="true"><use href="#i-lock"></use></svg>
            <span>🚪 Logout</span>
        </button>
    </form>
</nav>

<script>
(function () {
    var filter = document.getElementById('navFilter');
    var groups = document.getElementById('navGroups');
    var none   = document.getElementById('navNone');
    if (!filter || !groups) return;

    var STORE = 'brooks.nav.open';

    /* Remember which groups and sub-groups the user leaves open. */
    function remember() {
        var open = [];
        groups.querySelectorAll('details[open]').forEach(function (d) {
            open.push(d.dataset.group);
        });
        try { localStorage.setItem(STORE, JSON.stringify(open)); } catch (e) {}
    }

    try {
        var saved = JSON.parse(localStorage.getItem(STORE) || '[]');
        groups.querySelectorAll('details').forEach(function (d) {
            if (saved.indexOf(d.dataset.group) !== -1) d.open = true;
        });
    } catch (e) {}

    groups.querySelectorAll('details').forEach(function (d) {
        d.addEventListener('toggle', remember);
    });

    /* Typing opens everything and hides non-matching links. Clearing the
       box puts the previous state back. */
    var before = null;

    filter.addEventListener('input', function () {
        var term = filter.value.trim().toLowerCase();

        if (term && before === null) {
            before = [];
            groups.querySelectorAll('details').forEach(function (d) {
                before.push(d.open);
                d.open = true;
            });
        }

        var hits = 0;

        // Links first, then hide any container left with nothing showing.
        groups.querySelectorAll('.nav-link').forEach(function (a) {
            if (!a.dataset.name) return;
            var match = !term || a.dataset.name.indexOf(term) !== -1;
            a.hidden = !match;
            if (match) hits++;
        });

        groups.querySelectorAll('.nav-sub').forEach(function (sub) {
            var shown = sub.querySelectorAll('.nav-link:not([hidden])').length;
            sub.hidden = term && shown === 0;
        });

        groups.querySelectorAll('.nav-group').forEach(function (g) {
            var shownLinks = g.querySelectorAll('.nav-link:not([hidden])').length;
            g.hidden = term && shownLinks === 0;
        });

        none.hidden = hits > 0;

        if (!term && before !== null) {
            groups.querySelectorAll('details').forEach(function (d, i) {
                d.open = before[i];
            });
            before = null;
        }
    });
})();
</script>
