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
    // Only the outline SVG icons are shown; emoji left in labels (some are lookup keys) are stripped for display.
    $navText = fn ($label) => trim(preg_replace('/^[\p{So}\p{Sk}\x{FE0F}\x{200D}\s]+/u', '', (string) $label));
    $groups     = $user->sidebarModules();
    $currentKey = request()->route('key');
    if ($currentKey === null) {
        foreach (config('navigation.active_keys') as $routePattern => $navKey) {
            if (! request()->routeIs($routePattern)) continue;
            $currentKey = match (true) {
                str_starts_with($navKey, '@route:') => request()->route(substr($navKey, 7)),
                str_starts_with($navKey, '@input:') => request(substr($navKey, 7), request()->route(substr($navKey, 7))),
                default => $navKey,
            };
            if ($currentKey !== null) break;
        }
    }
    if ($currentKey === 'kpis' && request()->routeIs('executive.*')) $currentKey = 'kpi-dashboards';
    if (request()->routeIs('operations.reports*')) $currentKey = 'reports';
    if (request()->routeIs('operations.units.*')) $currentKey = request()->route('unit');
    if (request()->routeIs('poultry.report.*')) $currentKey = 'reports';
    if (request()->routeIs('daily-reports.*')) $currentKey = 'daily-management-report';
    if (request()->routeIs('vaccination-schedule.*') || request()->routeIs('vaccination-records.*')) $currentKey = 'vaccination';
    // Production and HR reach their department draft through their "budget" tab; Finance has a separate "department-budget" tab.
    if (request()->routeIs('department-budget.*')) $currentKey = auth()->user()->roles->pluck('code')->push(auth()->user()->role?->code)->intersect(['production-manager', 'hr-manager'])->isNotEmpty() ? 'budget' : 'department-budget';
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
        'Director Briefing' => [
            ['key' => 'ceo-daily-report', 'label' => 'My Daily Report'],
            ['key' => 'ceo-daily-reports', 'label' => 'Past Reports'],
        ],
        'Operational Reports' => [
            ['key' => 'reports', 'label' => 'Overall Production Report', 'page' => 'production'],
            // One tab for every unit: the page has a Unit filter and Production / Feeds / Health / Deaths tabs.
            ['key' => 'reports', 'label' => 'Unit Reports', 'page' => 'unit-reports'],
        ],
        '📊 Executive View' => [
            ['key' => 'reports', 'label' => 'Financial & Executive Reports'],
            ['key' => 'sales', 'label' => 'Sales Report', 'screen' => 'reports'],
            ['key' => 'sales', 'label' => 'Sales Analysis', 'screen' => 'analysis'],
            ['key' => 'sales', 'label' => 'Revenue Analysis', 'screen' => 'analysis', 'analysis_view' => 'revenue'],
        ],
        '✅ Decisions' => [
            ['key' => 'assign-supervisors', 'label' => 'Daily Collection Approvals'],
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
            ['key' => 'pa-daily-reports', 'label' => "PA's Daily Reports"],
            ['key' => 'ceo-daily-reports', 'label' => "CEO's Daily Reports"],
            ['key' => 'reports', 'label' => 'Overall Production Report', 'page' => 'production'],
            // One tab for every unit: the page has a Unit filter and Production / Feeds / Health / Deaths tabs.
            ['key' => 'reports', 'label' => 'Unit Reports', 'page' => 'unit-reports'],
        ],
        '📊 Board View' => [
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
            ['key' => 'security', 'label' => 'User Accounts'],
            ['key' => 'role-permissions', 'label' => 'Role Permissions'],
            ['key' => 'settings', 'label' => 'System Settings'],
        ],
    ];

    $assistantSections = [
        // The PA's own daily report to the Director.
        'Director Briefing' => [
            ['key' => 'pa-daily-report', 'label' => "Today's Report"],
            ['key' => 'pa-daily-reports', 'label' => 'Past Reports'],
        ],
        'Director Follow-up' => [
            ['key' => 'directives', 'label' => 'Directives & Feedback'],
            ['key' => 'pa-meetings', 'label' => "Director's Meetings"],
            ['key' => 'pa-money', 'label' => 'Approved Money'],
            ['key' => 'approvals', 'label' => 'Approved Money Tracker', 'status' => 'approved'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Announcements'],
            ['key' => 'notifications', 'label' => 'Notifications'],
        ],
        // Same report sections as the Director (Profitability and Unit Costs open from Financial & Executive Reports).
        'Operational Reports' => [
            ['key' => 'pa-production-reports', 'label' => 'Overall Production Report'],
            ['key' => 'pa-unit-reports', 'label' => 'Unit Reports'],
        ],
        '📊 Board View' => [
            ['key' => 'pa-finance-reports', 'label' => 'Financial & Executive Reports'],
            ['key' => 'pa-sales-reports', 'label' => 'Sales Report'],
            ['key' => 'pa-hr-reports', 'label' => 'HR Reports'],
        ],
        'Front Office' => [
            ['key' => 'visitor-check-in', 'label' => 'Check In a Visitor'],
            ['key' => 'visitors', 'label' => 'Visitor Register'],
        ],
        // Building projects: builders, materials, payments (stay and food are with HR).
        'Construction' => [
            ['key' => 'pa-construction', 'label' => 'Construction Projects'],
        ],
    ];

    $productionSections = [
        'Monitor' => [
            // Each Monitor tab picks the farm unit inside the page (Poultry, Goats, Rabbits, Dogs, Garden).
            ['key'=>'batches', 'label'=>'Production', 'page'=>'production-group'],
            // Feed consumption and delivery verification share one tab (switch inside the page).
            ['key'=>'batches', 'label'=>'Feeds Management', 'page'=>'feed-group'],
            // Treatments, vaccination, deaths and death examination share one tab (switch inside the page).
            ['key'=>'batches', 'label'=>'Health & Treatment', 'page'=>'health-group'],
            ['key'=>'staff-performance', 'label'=>'Attendant Performance'],
        ],
        // Day-to-day unit work (registers, production, feeds, health) lives in Monitor.
        'Farm Units' => [
            ['key'=>'batches', 'label'=>'Houses & Worker Assignment', 'page'=>'houses'],
            ['key'=>'batches', 'label'=>'Farm Tasks', 'page'=>'farm-tasks'],
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
            ['key' => 'vehicles', 'label' => 'Company Vehicle Register'],
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

    // One tab per store the person may view (replaces the store picker on the Inventory page).
    $inventoryStoreTabs = $user->canAccess('inventory')
        ? \App\Http\Controllers\InventoryController::storesFor($user)->map(fn ($store) => ['key' => 'inventory', 'label' => $store->name, 'store' => $store->id])->values()->all()
        : [];
    // Finance menu, as in the Finance dashboard design: the day's work first, then other modules, then settings.
    // Each item is a section; its pages appear as tabs in the bar at the top of the page.
    $financeSections = [
        'Finance Posting' => [
            ['key' => 'transactions', 'label' => 'Data Entry & Register'],
            ['key' => 'transactions', 'label' => 'Account Balances', 'view' => 'balances'],
            ['key' => 'transactions', 'label' => 'Farm Consumption', 'view' => 'consumption'],
        ],
        'Customers' => [
            ['key' => 'receivables', 'label' => 'Overview'],
            ['key' => 'customers', 'label' => 'Customer Register', 'view' => 'add'],
            ['key' => 'customers', 'label' => 'Customer Accounts', 'view' => 'accounts'],
            ['key' => 'receivables', 'label' => 'Receivables & Balances', 'tab' => 'balances'],
            ['key' => 'receivables', 'label' => 'Prepayments', 'tab' => 'prepayments'],
            // Record Payment is a button on Prepayments now (the separate tab was removed).
            ['key' => 'customers', 'label' => 'Customer Grading', 'view' => 'analysis', 'analysis' => 'grading'],
            ['key' => 'customers', 'label' => 'Customer Loyalty', 'view' => 'analysis', 'analysis' => 'loyalty'],
            ['key' => 'customers', 'label' => 'Customers to Follow Up', 'view' => 'analysis', 'analysis' => 'follow-up'],
        ],
        'Payables' => [
            ['key' => 'payables', 'label' => 'Supplier Bills'],
            ['key' => 'payables', 'label' => 'Batch Orders', 'tab' => 'orders'],
        ],
        'Expenses' => [
            ['key' => 'expenses', 'label' => 'Money Paid Out'],
            ['key' => 'expenditure-analysis-report', 'label' => 'Expenditure Analysis'],
        ],
        'Budget & Funding' => [
            ['key' => 'budget', 'label' => 'Combined Weekly Budget'],
            ['key' => 'department-budget', 'label' => 'Finance Department Draft'],
        ],
        'Cash & Banking' => [
            ['key' => 'cash-book', 'label' => 'Cash Book'],
            ['key' => 'bank-book', 'label' => 'Bank Book'],
        ],
        'Reports' => [
            ['key' => 'reports', 'label' => 'Financial Reports'],
            ['key' => 'profitability-report', 'label' => 'Profitability'],
            ['key' => 'unit-costs-report', 'label' => 'Unit Costs'],
            ['key' => 'profit-analysis-report', 'label' => 'Profit Analysis'],
            ['key' => 'performance-analysis-report', 'label' => 'P&L Performance Analysis'],
        ],
        // ---- Other modules
        'Sales' => [
            ['key' => 'sales', 'label' => 'Sales Report', 'screen' => 'reports'],
            ['key' => 'sales', 'label' => 'Sales Analysis', 'screen' => 'analysis'],
            ['key' => 'sales', 'label' => 'Selling Prices', 'screen' => 'setup'],
        ],
        'Purchases' => [
            ['key' => 'purchases', 'label' => 'Purchases'],
        ],
        'Inventory' => $inventoryStoreTabs ?: [
            ['key' => 'inventory', 'label' => 'Inventory & Stores'],
        ],
        'Payroll' => [
            ['key' => 'payroll', 'label' => 'Payroll'],
            ['key' => 'accounts', 'label' => 'Staff Salary Accounts', 'view' => 'staff'],
        ],
        'Fixed Assets' => [
            ['key' => 'assets', 'label' => 'Asset Register'],
        ],
        'Accounting' => [
            ['key' => 'general-ledger', 'label' => 'General Ledger'],
            ['key' => 'approvals', 'label' => 'Approval Tracking'],
        ],
        // ---- Settings
        'Settings' => [
            ['key' => 'chart-of-accounts', 'label' => 'Finance Setup'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
            ['key' => 'messages', 'label' => 'Messages & Updates'],
        ],
    ];

    $salesSections = [
        '🧾 Daily Sales' => [
            ['key'=>'sales','label'=>'Daily Sales','screen'=>'daily'],
            ['key'=>'sales','label'=>'Record Daily Sale','screen'=>'sale'],
        ],
        'Inventory' => [
            ['key'=>'sales','label'=>'Inventory','screen'=>'inventory'],
        ],
        '📈 Reports' => [
        ],
        'Communication' => [
            ['key'=>'messages','label'=>'Messages & Updates'],
        ],
    ];

    /* A module can expose several destinations (for example Sales entry and
       Sales reports). Match the complete destination so only the link for the
       page being viewed is highlighted and expanded. */
    $navItemIsActive = function (array $item) use ($currentKey, $inventoryStoreTabs): bool {
        // Daily Collection Approvals (under Decisions) owns the supervision pages, so the bar stays on Decisions.
        if (($item['key'] ?? null) === 'assign-supervisors') return request()->routeIs('supervision.*');
        // Pending Approvals is current only on the pending list, not on Sent to Director or Rejected.
        if (($item['key'] ?? null) === 'approvals' && ! isset($item['status']) && ! isset($item['type'])
            && request()->routeIs('approvals.index') && ! in_array(request('status'), [null, '', 'pending'], true)) return false;
        if (($item['key'] ?? null) === 'assets') return request()->routeIs('finance.assets.*');
        if (($item['key'] ?? null) === 'ceo-daily-report') return request()->routeIs('ceo.daily-report');
        if (($item['key'] ?? null) === 'ceo-daily-reports') return request()->routeIs('ceo.reports.*');
        if (($item['key'] ?? null) === 'role-permissions') return request()->routeIs('executive.index') && request()->route('screen') === 'permissions';
        if (($item['key'] ?? null) === 'security') return request()->routeIs('executive.index') && request()->route('screen') === 'security';
        if (($item['key'] ?? null) === 'pa-construction') return request()->routeIs('construction.*');
        if (($item['key'] ?? null) === 'pa-daily-report') return request()->routeIs('pa.daily-report*');
        if (($item['key'] ?? null) === 'pa-unit-reports') return request()->routeIs('poultry.report.*') || request()->routeIs('operations.units.report');
        if (($item['key'] ?? null) === 'pa-finance-reports') return request()->routeIs('finance.reports.*');
        if (($item['key'] ?? null) === 'pa-sales-reports') return request()->routeIs('sales.*') && request('screen') === 'reports';
        if (($item['key'] ?? null) === 'pa-production-reports') return request()->routeIs('operations.reports*');
        if (($item['key'] ?? null) === 'pa-hr-reports') return request()->routeIs('hr.reports.*');
        if (($item['key'] ?? null) === 'pa-daily-reports') return request()->routeIs('pa.reports.*');
        if (($item['key'] ?? null) === 'visitor-check-in') return request()->routeIs('visitors.create');
        if (($item['key'] ?? null) === 'visitors' && request()->routeIs('visitors.create')) return false;
        if (($item['key'] ?? null) === 'pa-meetings') return request()->routeIs('pa.meetings');
        if (($item['key'] ?? null) === 'pa-money') return request()->routeIs('pa.approved-money');
        if (($item['key'] ?? null) === 'on-farm') return request()->routeIs('hr.on-farm');
        if (($item['key'] ?? null) === 'staff-consumables') return request()->routeIs('hr.consumables.index');
        if (($item['key'] ?? null) === 'temporary-workers') return request()->routeIs('hr.temporary.*');
        if (($item['key'] ?? null) === 'profitability-report') return request()->routeIs('finance.reports.profitability');
        if (($item['key'] ?? null) === 'unit-costs-report') return request()->routeIs('finance.reports.unit-costs');
        if (($item['key'] ?? null) === 'expenditure-analysis-report') return request()->routeIs('finance.reports.expenditure-analysis');
        if (($item['key'] ?? null) === 'profit-analysis-report') return request()->routeIs('finance.reports.profit-analysis');
        if (($item['key'] ?? null) === 'performance-analysis-report') return request()->routeIs('finance.reports.performance-analysis');
        // Pages opened from a Monitor tab belong to that tab only (not to the unit's own tab).
        if ((request()->routeIs('monitor.group') || request()->routeIs('monitor.health*') || request()->routeIs('sick-bay.*') || request()->routeIs('operations.livestock.examine') || request()->filled('monitor'))
            && ! in_array($item['page'] ?? null, ['production-group', 'feed-group', 'health-group'], true)) return false;
        if (isset($item['page'])) {
            $page = $item['page'];
            if ($page === 'production') return request()->routeIs('operations.reports*');
            if ($page === 'poultry') return request()->routeIs('poultry.report.*');
            if ($page === 'unit-reports') return request()->routeIs('poultry.report.*') || request()->routeIs('operations.units.report');
            if (in_array($page, ['goats', 'rabbits', 'dogs', 'garden'], true)) {
                return request()->routeIs('operations.units.report') && request()->route('unit') === $page;
            }
            if ($page === 'poultry-report') {
                $shown = in_array(request('view'), ['feed', 'mortality', 'health', 'sales'], true) ? request('view') : 'eggs';
                return request()->routeIs('poultry.report.index') && $shown === ($item['view'] ?? 'eggs');
            }
            if ($page === 'houses') return request()->routeIs('poultry-houses.*');
            if ($page === 'farm-tasks') return request()->routeIs('farm-tasks.*');
            if ($page === 'production-group') {
                return (request()->routeIs('monitor.group') && request()->route('group') === 'production') || request('monitor') === 'production'
                    || request()->routeIs('batches.*') || request()->routeIs('operations.units.index')
                    || (request()->routeIs('operations.livestock.section') && in_array(request()->route('section'), ['register', 'breeding', 'production'], true));
            }
            if ($page === 'feed-group') {
                return (request()->routeIs('monitor.group') && request()->route('group') === 'feeds') || request('monitor') === 'feeds'
                    || (request()->routeIs('operations.livestock.section') && request()->route('section') === 'feed' && ! request()->filled('monitor'))
                    || (request()->routeIs('poultry.report.index') && request('view') === 'feed') || request()->routeIs('operations.feed-stock.*') || request()->routeIs('operations.feed-mixing.*');
            }
            if ($page === 'health-group') {
                return (request()->routeIs('monitor.group') && request()->route('group') === 'health') || request('monitor') === 'health' || request()->routeIs('monitor.health*') || request()->routeIs('operations.livestock.examine')
                    || (request()->routeIs('operations.livestock.section') && in_array(request()->route('section'), ['vaccination', 'deworming', 'spraying', 'treatment', 'mortality'], true) && ! request()->filled('monitor'))
                    || (request()->routeIs('poultry.report.index') && in_array(request('view'), ['health', 'mortality'], true))
                    || request()->routeIs('vaccination-schedule.*') || request()->routeIs('mortality.assess.*') || request()->routeIs('sick-bay.*');
            }
            if ($page === 'schedule') return request()->routeIs('vaccination-schedule.*');
            if ($page === 'records') return request()->routeIs('vaccination-schedule.*') && request('view') === 'records';
            if ($page === 'export') {
                return request()->routeIs('operations.units.report') && request()->route('unit') === ($item['key'] ?? null);
            }
        }

        if (($item['key'] ?? null) !== $currentKey) return false;
        /* A store tab is current for its store; with no store chosen the page shows the first one. */
        if (($item['key'] ?? null) === 'inventory' && isset($item['store'])) {
            $firstStore = $inventoryStoreTabs[0]['store'] ?? null;
            return (int) request('store', $firstStore) === (int) $item['store'];
        }
        /* Batch Orders also covers the old ?tab=order link. */
        if (($item['key'] ?? null) === 'payables' && ($item['tab'] ?? null) === 'orders') {
            return in_array(request('tab'), ['orders', 'order'], true);
        }
        /* Customer analysis opens on grading when no analysis is chosen. */
        if (($item['key'] ?? null) === 'customers' && ($item['analysis'] ?? null) === 'grading' && request('view') === 'analysis') {
            return ! in_array(request('analysis'), ['loyalty', 'follow-up'], true);
        }

        /* Sales analysis and revenue analysis share screen=analysis: revenue pages carry analysis_view=revenue. */
        if (($item['key'] ?? null) === 'sales' && ($item['screen'] ?? null) === 'analysis'
            && (request('analysis_view') === 'revenue') !== (($item['analysis_view'] ?? null) === 'revenue')) {
            return false;
        }

        foreach (['screen', 'status', 'view', 'type', 'analysis', 'tab'] as $parameter) {
            if (array_key_exists($parameter, $item)
                && (string) request($parameter, request()->route($parameter)) !== (string) $item[$parameter]) {
                return false;
            }
        }

        /* These screens use a default when the query parameter is omitted. */
        if (($item['key'] ?? null) === 'payables' && !isset($item['tab'])) {
            return ! in_array(request('tab'), ['orders', 'order'], true);
        }
        if (($item['key'] ?? null) === 'receivables' && !isset($item['tab'])) {
            return request()->routeIs('finance.receivables.index') && ! request()->filled('tab');
        }
        if (($item['key'] ?? null) === 'transactions' && !isset($item['view'])) {
            return ! in_array(request('view'), ['consumption', 'balances'], true);
        }
        if (($item['key'] ?? null) === 'sales' && !isset($item['screen'])) {
            return request('screen', 'sale') === 'sale';
        }

        return true;
    };


    $hrSections = [
        'HR Data Entry' => [
            ['key' => 'hr-entry', 'label' => 'HR Data Entry'],
            ['key' => 'staff-consumables', 'label' => 'Consumables Issue Register'],
            ['key' => 'accommodation', 'label' => 'Staff Accommodation'],
            ['key' => 'temporary-workers', 'label' => 'Temporary Workers'],
            ['key' => 'on-farm', 'label' => 'On the Farm Now'],
        ],
        'People & Payroll' => array_merge($hrSections['👥 Employees'], $hrSections['💰 Payroll']),
        'Welfare & Development' => array_merge($hrSections['❤️ Welfare'], $hrSections['📚 Development']),
        'Administration & Fleet' => $hrSections['🚚 Administration & Fleet'],
        'Fixed Assets' => [
            ['key' => 'assets', 'label' => 'Asset Register'],
        ],
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
    <a class="drawer-person" href="{{ route('account.edit') }}"><strong>{{ $user->displayName() }}</strong><small>{{ $user->roleSummary() }}</small></a>

    @if(!$isStoreManager && ($isAppointedSupervisor || $isSalesManager || $isProductionManager))
        <a class="nav-link nav-link--supplementary" href="{{ route('supervision.index') }}" @if(request()->routeIs('supervision.*')) aria-current="page" @endif><span>Daily Collection Approvals</span></a>
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
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>Dashboard</span>
            </a>
            @foreach(($isCeo ? $ceoSections : $directorSections) as $section => $items)
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif>
                    <summary>{{ $navText($section) }}</summary>
                    @foreach($items as $item)
                        @continue(in_array($item['key'], ['settings', 'notifications'], true))
                        @if($user->canAccess($item['key']) || in_array($item['key'], ['customers', 'security', 'settings', 'visitors', 'directives'], true) || ($isCeo && $item['key'] === 'sales') || ($isDirector && in_array($item['key'], ['pa-daily-reports', 'role-permissions', 'ceo-daily-reports'], true)) || ($isCeo && in_array($item['key'], ['ceo-daily-report', 'ceo-daily-reports'], true)))
                            @php
                                $href = match ($item['key']) {
                                    'approvals' => route('approvals.index', isset($item['type']) ? ['type'=>$item['type']] : (isset($item['status']) ? ['status'=>$item['status']] : [])),
                                    'assign-supervisors' => route('supervision.index'),
                                    'kpi-dashboards' => route('executive.index', ['screen'=>'kpis']),
                                    'reports' => match($item['page'] ?? 'executive') {
                                        'production' => route('operations.reports'),
                                        'poultry', 'unit-reports' => route('poultry.report.index'),
                                        'goats','rabbits','dogs','garden' => route('operations.units.report',['unit'=>$item['page']]),
                                        default => route('executive.index', ['screen'=>'reports']),
                                    },
                                    'messages' => route('communications.index'),
                                    'notifications', 'security', 'settings' => route('executive.index', ['screen'=>$item['key']]),
                                    'visitors' => route('visitors.index'),
                                    'pa-daily-reports' => route('pa.reports.index'),
                                    'role-permissions' => route('executive.index', ['screen'=>'permissions']),
                                    'ceo-daily-report' => route('ceo.daily-report'),
                                    'ceo-daily-reports' => route('ceo.reports.index'),
                                    'hr' => route('employees.index'),
                                    'customers' => route('owner.customers'),
                                    'directives' => route('directives.index'),
                                    'sales' => route('sales.index', array_filter(['screen' => $item['screen'] ?? 'setup', 'analysis_view' => $item['analysis_view'] ?? null])),
                                    'budget' => route('finance.budgets.index'),
                                    'purchases' => route('finance.farm.purchases'),
                                    'assets' => route('finance.assets.index'),
                                    'audit' => route('finance.admin.index', ['screen' => 'audit']),
                                    'attendance','welfare','advances' => route('hr.records.index',['module'=>$item['key']]),
                                    'staff-performance' => route('poultry.performance.index'),
                                    default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                                };
                            @endphp
                            @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $navText($item['label']) }}</span></a>@endif
                        @endif
                    @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isPersonalAssistant)
        <div class="hr-nav" aria-label="Personal Assistant navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif><span>Dashboard</span></a>
            @foreach($assistantSections as $section => $items)
                {{-- A section with nothing this person may open is not shown. --}}
                @continue(collect($items)->filter(fn ($item) => ! in_array($item['key'], ['settings', 'notifications'], true) && ($user->canAccess($item['key']) || $item['key'] === 'directives' || str_starts_with($item['key'], 'pa-') || ($item['key'] === 'visitor-check-in' && $user->canDo('visitors', 'create'))))->isEmpty())
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif><summary>{{ $navText($section) }}</summary>
                @foreach($items as $item)
                    @continue(in_array($item['key'], ['settings', 'notifications'], true))
                    @if($user->canAccess($item['key']) || $item['key'] === 'directives' || str_starts_with($item['key'], 'pa-') || ($item['key'] === 'visitor-check-in' && $user->canDo('visitors', 'create')))
                        @php $href = match($item['key']) {
                            'messages' => route('communications.index'),
                            'dashboard' => route('dashboard'), 'directives' => route('directives.index'),
                            'reports', 'notifications' => route('executive.index',['screen'=>$item['key']]),
                            'pa-finance-reports' => route('finance.reports.index'),
                            'pa-profitability-reports' => route('finance.reports.profitability'),
                            'pa-unit-costs-reports' => route('finance.reports.unit-costs'),
                            'pa-sales-reports' => route('sales.index',['screen'=>'reports']),
                            'pa-hr-reports' => route('hr.reports.index'),
                            'pa-production-reports' => route('operations.reports'),
                            'pa-poultry-reports', 'pa-unit-reports' => route('poultry.report.index'),
                            'pa-goat-reports' => route('operations.units.report',['unit'=>'goats']),
                            'pa-rabbit-reports' => route('operations.units.report',['unit'=>'rabbits']),
                            'pa-dog-reports' => route('operations.units.report',['unit'=>'dogs']),
                            'pa-garden-reports' => route('operations.units.report',['unit'=>'garden']),
                            'pa-construction' => route('construction.index'),
                            'pa-daily-report' => route('pa.daily-report'),
                            'pa-daily-reports' => route('pa.reports.index'),
                            'pa-meetings' => route('pa.meetings'),
                            'pa-money' => route('pa.approved-money'),
                            'approvals' => route('approvals.index',['status'=>$item['status'] ?? 'pending']),
                            'visitors' => route('visitors.index'),
                            'visitor-check-in' => route('visitors.create'),
                            default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                        }; @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $navText($item['label']) }}</span></a>@endif
                    @endif
                @endforeach
                </details>
            @endforeach
        </div>
    @elseif ($isProductionManager)
        <div class="hr-nav" aria-label="Production Manager navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif><svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>Dashboard</span></a>
            @foreach($productionSections as $section => $items)
                @continue(empty($items))
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif><summary>{{ $navText($section) }}</summary>
                @foreach($items as $item)
                    @continue(in_array($item['key'], ['settings', 'notifications'], true))
                    @if($user->canAccess($item['key']))
                        @php
                            $href = match($item['key']) {
                                'egg-entry','feed-consumption','mortality-entry' => route('poultry.report.index'),
                                'batches' => match($item['page'] ?? null) {
                                    'poultry-report' => route('poultry.report.index', array_filter(['view' => $item['view'] ?? null])),
                                    'health-group' => route('monitor.group', 'health'),
                                    'production-group' => route('monitor.group', 'production'),
                                    'feed-group' => route('monitor.group', 'feeds'),
                                    'houses' => route('poultry-houses.index'),
                                    'farm-tasks' => route('farm-tasks.index'),
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
                                'budget' => route('department-budget.index'),
                                'veterinary' => route('finance.farm.veterinary'),
                                'attendance' => route('hr.records.index',['module'=>'attendance']),
                                'staff-performance','kpi-dashboards' => route('poultry.performance.index'),
                                'reports' => route('operations.reports'),
                                'feed-received' => route('operations.feed-stock.index'),
                                'vaccination' => route('vaccination-schedule.index', ['view' => ($item['page']??null)==='records' ? 'records' : 'schedule']),
                                'approvals' => route('approvals.index'),
                                'messages' => route('communications.index'),
                                default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                            };
                        @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}" @if($navItemIsActive($item)) aria-current="page" @endif><span>{{ $navText($item['label']) }}</span></a>@endif
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
                @if(! $isHrManager && ! $isSalesManager && $section === 'Sales')<p class="nav-divider">Other modules</p>@endif
                @if(! $isHrManager && ! $isSalesManager && $section === 'Settings')<hr class="nav-rule">@endif
                <details class="hr-nav-section" @if(collect($items)->contains(fn($item) => $navItemIsActive($item))) open @endif>
                    <summary>{{ $navText($section) }}</summary>
                    @foreach ($items as $item)
                        @continue(in_array($item['key'], ['settings', 'notifications'], true))
                        @if ($user->canAccess($item['key']) || ($isHrManager && in_array($item['key'], ['hr-entry', 'staff-consumables', 'accommodation', 'vehicles', 'on-farm', 'temporary-workers'], true)) || ($isSalesManager && $item['key'] === 'sales') || in_array($item['key'], ['department-budget','customers'], true) || ($isFinanceUser && in_array($item['key'], ['sales','reports','accounts','expenses','profitability-report','unit-costs-report','expenditure-analysis-report','profit-analysis-report','performance-analysis-report'], true)))
                            @php
                                $href = $isSalesManager
                                    ? match ($item['key']) {
                                        'sales' => route('sales.index', ['screen' => $item['screen'] ?? 'sale']),
                                        'customers' => route('owner.customers', array_filter(['view'=>$item['view'] ?? 'accounts', 'analysis'=>$item['analysis'] ?? null])),
                                        'department-budget' => route('department-budget.index'),
                                        'messages' => route('communications.index'),
                                        default => route('module.placeholder', ['key' => $item['key']]),
                                    }
                                    : ($isHrManager
                                    ? match ($item['key']) {
                                        'hr' => route('employees.index'),
                                        'hr-entry' => route('hr.data-entry.index'),
                                        'staff-consumables' => route('hr.consumables.index'),
                                        'accommodation' => route('hr.accommodation.index'),
                                        'on-farm' => route('hr.on-farm'),
                                        'temporary-workers' => route('hr.temporary.index'),
                                        'vehicles' => route('hr.vehicles.index'),
                                        'reports' => route('hr.reports.index'),
                                        'kpi-dashboards' => route('hr.kpis.index'),
                                        'staff-performance' => route('poultry.performance.index'),
                                        'payroll' => route('payroll.index'),
                                        'budget' => route('department-budget.index'),
                                        'fleet' => route('hr.fleet.index'),
                                        'assets' => route('finance.assets.index'),
                                        'messages' => route('communications.index'),
                                        default => route('hr.records.index', ['module' => $item['key']]),
                                    }
                                    : match ($item['key']) {
                                        'transactions' => route('finance.transactions.index', array_filter(['view' => $item['view'] ?? null])),
                                        'expenses' => route('finance.transactions.index', ['view' => 'register', 'type' => 'money_paid']),
                                        'accounts' => route('finance.accounts.index', array_filter(['view' => $item['view'] ?? null])),
                                        'inventory' => route('inventory.index', isset($item['store']) ? ['store' => $item['store'], 'screen' => 'stock'] : []),
                                        'customers' => route('owner.customers', array_filter(['view'=>$item['view'] ?? 'accounts', 'analysis'=>$item['analysis'] ?? null])),
                                        'general-ledger' => route('finance.general-ledger.index'),
                                        'receivables' => route('finance.receivables.index', array_filter(['tab' => $item['tab'] ?? null])),
                                        'payables' => route('finance.payables.index', array_filter(['tab' => $item['tab'] ?? null])),
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
                                        'profitability-report' => route('finance.reports.profitability'),
                                        'unit-costs-report' => route('finance.reports.unit-costs'),
                                        'expenditure-analysis-report' => route('finance.reports.expenditure-analysis'),
                                        'profit-analysis-report' => route('finance.reports.profit-analysis'),
                                        'performance-analysis-report' => route('finance.reports.performance-analysis'),
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
                                <span>{{ $navText($item['label']) }}</span>
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
            <details class="hr-nav-section" @if(request()->routeIs('operations.feed-stock.*') || request()->routeIs('operations.feed-mixing.*') || (request()->routeIs('inventory.*') && request('action', 'received')==='received')) open @endif>
                <summary>Receive</summary>
                <a class="nav-link" href="{{ route('operations.feed-stock.index') }}" @if(request()->routeIs('operations.feed-stock.*')) aria-current="page" @endif><span>Feeds</span></a>
                <a class="nav-link" href="{{ route('operations.feed-mixing.index') }}" @if(request()->routeIs('operations.feed-mixing.*')) aria-current="page" @endif><span>Mixed Feed</span></a>
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
                <h2>My Assigned Work</h2>
                @forelse($casualUnits as $code=>$item)
                    <a class="nav-link" href="{{ route('daily.index') }}#{{ $item['anchor'] }}"><span>{{ $navText($item['label']) }}</span></a>
                @empty
                    <p class="nav-empty">No farm unit is assigned. Ask HR to assign Poultry, Goats, Rabbits, Dogs, or Garden.</p>
                @endforelse
            </section>
            @if($user->canAccess('messages'))
            <section class="hr-nav-section" aria-label="Worker updates"><h2>Updates</h2>
                @if($user->canAccess('messages'))<a class="nav-link" href="{{ route('communications.index') }}"><span>Messages</span></a>@endif
            </section>
            @endif
        </div>
    @else
        @if ($user->canAccess('dashboard'))
            <a class="nav-link" href="{{ route('dashboard') }}"
               @if ($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg>
                <span>Dashboard</span>
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
                $departmentsShown = $departmentsShown ?? false;
                $holdsCurrent = $modules->contains(function ($m) use ($currentKey) {
                    return $m->key === $currentKey
                        || $m->children->contains('key', $currentKey);
                });
                $shownModules = $modules->reject(fn ($m) => in_array($m->key, ['settings', 'notifications'], true));
                $isDepartment = in_array($groupName, \App\Models\Module::DEPARTMENT_GROUPS, true);
                $headingBefore = null;
                if ($isDepartment && ! $departmentsShown) { $headingBefore = 'Departments'; $departmentsShown = true; }
                elseif ($groupName === 'Settings') { $headingBefore = 'System'; }
                $only = $shownModules->count() === 1 && $shownModules->first()->children->isEmpty() ? $shownModules->first() : null;
            @endphp

            {{-- Farm Hub layout: the farm departments are listed under their own heading. --}}
            @if ($headingBefore)
                <p class="nav-heading">{{ $headingBefore }}</p>
            @endif

            {{-- A place with a single screen is a plain link, as in Farm Hub. --}}
            @if ($only)
                <a class="nav-link nav-link--place"
                   href="{{ $only->url() }}"
                   data-name="{{ Str::lower($groupName.' '.$only->navigationLabel()) }}"
                   @if ($only->key === $currentKey) aria-current="page" @endif>
                    <svg class="nav-icon" aria-hidden="true"><use href="#i-{{ $only->iconName() }}"></use></svg>
                    <span>{{ $groupName }}</span>
                </a>
                @continue
            @endif

            <details class="nav-group"
                     data-group="{{ Str::slug($groupName) }}"
                     @if ($holdsCurrent) open @endif>

                <summary>
                    <span>{{ $groupName }}</span>
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
                                <span>{{ $navText($module->name) }}</span>
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
            <span>Logout</span>
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
