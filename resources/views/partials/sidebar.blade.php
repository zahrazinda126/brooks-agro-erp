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
        ?? (request()->routeIs('dashboard') ? 'dashboard' : null)
        ?? (request()->routeIs('approvals.*') ? 'approvals' : null)
        ?? (request()->routeIs('employees.*') ? 'hr' : null)
        ?? (request()->routeIs('daily.*') ? 'egg-entry' : null)
        ?? (request()->routeIs('batches.*') ? 'batches' : null)
        ?? (request()->routeIs('collections.*') ? 'egg-inventory' : null)
        ?? (request()->routeIs('consumption.*') ? 'bird-consumption' : null)
        ?? (request()->routeIs('health.*') ? 'health' : null)
        ?? (request()->routeIs('mortality.assess.*') ? 'mortality-assess' : null)
        ?? (request()->routeIs('feed-types.*') ? 'feed-types' : null)
        ?? (request()->routeIs('sales.*') ? 'sales' : null)
        ?? (request()->routeIs('visitors.*') ? 'visitors' : null)
        ?? (request()->routeIs('poultry.performance.*') ? 'staff-performance' : null);

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
    $isPersonalAssistant = $user->role?->code === 'personal-assistant'
        || $user->roles->contains('code', 'personal-assistant');
    $isProductionManager = $user->role?->code === 'production-manager'
        || $user->roles->contains('code', 'production-manager')
        || (($user->role?->code === 'department-manager' || $user->roles->contains('code', 'department-manager'))
            && ($user->department?->code === 'production' || $user->departments->contains('code', 'production')));
    $isCasualWorker = $user->isDataEntryOnly();
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
        '📊 Executive View' => [
            ['key' => 'kpi-dashboards', 'label' => 'KPIs & Analytics'],
            ['key' => 'reports', 'label' => 'Executive Reports'],
        ],
        '✅ Decisions' => [
            ['key' => 'approvals', 'label' => 'Pending Approvals'],
            ['key' => 'approvals', 'label' => 'Sent to Director', 'status' => 'sent'],
            ['key' => 'approvals', 'label' => 'Rejected Requests', 'status' => 'rejected'],
        ],
        '📈 Financial Reports' => [
            ['key' => 'profit-loss', 'label' => 'Income Statement'],
            ['key' => 'balance-sheet', 'label' => 'Balance Sheet'],
            ['key' => 'cash-flow', 'label' => 'Cash Flow Statement'],
            ['key' => 'trial-balance', 'label' => 'Trial Balance'],
        ],
        '🏢 Oversight' => [
            ['key' => 'budget', 'label' => 'Weekly Budgets'],
            ['key' => 'purchases', 'label' => 'Purchases'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
            ['key' => 'hr', 'label' => 'Employees'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
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
        '📊 Board View' => [
            ['key' => 'kpi-dashboards', 'label' => 'KPIs & Analytics'],
            ['key' => 'reports', 'label' => 'Executive Reports'],
        ],
        '✅ Final Decisions' => [
            ['key' => 'approvals', 'label' => 'Pending Final Approvals'],
        ],
        '📈 Financial Statements' => [
            ['key' => 'profit-loss', 'label' => 'Income Statement'],
            ['key' => 'balance-sheet', 'label' => 'Balance Sheet'],
            ['key' => 'cash-flow', 'label' => 'Cash Flow Statement'],
            ['key' => 'trial-balance', 'label' => 'Trial Balance'],
        ],
        '🏛️ Governance' => [
            ['key' => 'budget', 'label' => 'Approved Budgets'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
        ],
        '⚙️ Administration' => [
            ['key' => 'visitors', 'label' => 'Visitor Register'],
            ['key' => 'security', 'label' => 'Users & Permissions'],
            ['key' => 'settings', 'label' => 'System Settings'],
        ],
    ];

    $assistantSections = [
        'Executive Support' => [
            ['key' => 'reports', 'label' => 'Executive Reports'],
            ['key' => 'approvals', 'label' => 'Approval Tracker', 'status' => 'approved'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages & Announcements'],
            ['key' => 'notifications', 'label' => 'Notifications'],
        ],
        'Front Office' => [
            ['key' => 'visitors', 'label' => 'Visitor Register'],
        ],
    ];

    $productionSections = [
        '🗓️ Today’s Work' => [
            ['key'=>'egg-entry', 'label'=>'Daily Production Entry', 'page'=>'daily'],
        ],
        '💰 Budgets' => [
            ['key'=>'budget', 'label'=>'Weekly Unit Budgets'],
        ],
        '🐔 Poultry Unit' => [
            ['key'=>'batches', 'label'=>'Poultry Overview'],
        ],
        '🐐 Goat Unit' => [
            ['key'=>'goats', 'label'=>'Goat Overview'],
            ['key'=>'goats', 'label'=>'Goat Unit Report', 'page'=>'export'],
        ],
        '🐇 Rabbit Unit' => [
            ['key'=>'rabbits', 'label'=>'Rabbit Overview'],
            ['key'=>'rabbits', 'label'=>'Rabbit Unit Report', 'page'=>'export'],
        ],
        '🐕 Dog Unit' => [
            ['key'=>'dogs', 'label'=>'Dog Overview'],
            ['key'=>'dogs', 'label'=>'Dog Unit Report', 'page'=>'export'],
        ],
        '🌱 Garden Unit' => [
            ['key'=>'garden', 'label'=>'Garden Overview'],
            ['key'=>'garden', 'label'=>'Garden Unit Report', 'page'=>'export'],
        ],
        '🏢 Farm Oversight' => [
            ['key'=>'reports', 'label'=>'Overall Production Report'],
            ['key'=>'feed-received', 'label'=>'Receive & View Feed'],
            ['key'=>'inventory', 'label'=>'Inventory & Farm Inputs'],
        ],
        '👥 Team' => [
            ['key'=>'attendance', 'label'=>'Attendance'],
            ['key'=>'assign-tasks', 'label'=>'Task Assignment'],
        ],
        '✅ Workflow' => [
            ['key'=>'approvals', 'label'=>'Approvals'],
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
    ];

    $financeSections = [
        '💵 Daily Operations' => [
            ['key' => 'transactions', 'label' => 'Transaction Register'],
            ['key' => 'receipts', 'label' => 'Money Received'],
            ['key' => 'payments', 'label' => 'Money Paid'],
            ['key' => 'cash-book', 'label' => 'Cash Book'],
            ['key' => 'bank-book', 'label' => 'Bank Book'],
        ],
        '📒 Accounting' => [
            ['key' => 'payroll', 'label' => 'Payroll'],
            ['key' => 'general-ledger', 'label' => 'General Ledger'],
            ['key' => 'receivables', 'label' => 'Accounts Receivable'],
            ['key' => 'payables', 'label' => 'Accounts Payable'],
            ['key' => 'budget', 'label' => 'Budget'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
        ],
        '🚜 Farm Operations' => [
            ['key' => 'purchases', 'label' => 'Purchases'],
            ['key' => 'production', 'label' => 'Production'],
            ['key' => 'veterinary', 'label' => 'Veterinary'],
        ],
        '📈 Reports' => [
            ['key' => 'reports', 'label' => 'Financial Reports'],
            ['key' => 'kpi-dashboards', 'label' => 'KPI Dashboard'],
            ['key' => 'profit-loss', 'label' => 'Income Statement'],
            ['key' => 'balance-sheet', 'label' => 'Balance Sheet'],
            ['key' => 'cash-flow', 'label' => 'Cash Flow Statement'],
            ['key' => 'trial-balance', 'label' => 'Trial Balance'],
        ],
        '⚙️ Administration' => [
            ['key' => 'approvals', 'label' => 'Approval Tracking'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
            ['key' => 'chart-of-accounts', 'label' => 'Chart of Accounts'],
            ['key' => 'finance-periods', 'label' => 'Financial Periods'],
            ['key' => 'finance-accounts', 'label' => 'Bank & Cash Accounts'],
            ['key' => 'payment-methods', 'label' => 'Payment Methods'],
            ['key' => 'document-numbering', 'label' => 'Document Numbering'],
            ['key' => 'tax-settings', 'label' => 'Tax & Payroll Settings'],
            ['key' => 'budget-settings', 'label' => 'Budget Settings'],
            ['key' => 'finance-imports', 'label' => 'Import & Reconciliation'],
        ],
    ];

    $salesSections = [
        '🧾 Daily Sales' => [
            ['key'=>'sales','label'=>'Record New Sale','anchor'=>'new-sale'],
            ['key'=>'invoices','label'=>'Invoices & Sales Register','anchor'=>'sales-register'],
            ['key'=>'receipts','label'=>'Receipts & Payments','anchor'=>'sales-register'],
        ],
        '🏷️ Products & Pricing' => [
            ['key'=>'inventory','label'=>'Products & Current Stock','anchor'=>'products'],
            ['key'=>'sales','label'=>'Selling Prices','anchor'=>'products'],
            ['key'=>'returns','label'=>'Sales Returns','anchor'=>'sales-register'],
        ],
        '👥 Customers' => [
            ['key'=>'customers','label'=>'Customer Register','anchor'=>'customers'],
            ['key'=>'customer-ledger','label'=>'Customer Balances','anchor'=>'sales-register'],
        ],
        '📈 Reports' => [
            ['key'=>'reports','label'=>'Sales Summary','anchor'=>'sales-summary'],
            ['key'=>'analytics','label'=>'Sales Performance','anchor'=>'sales-summary'],
        ],
    ];
@endphp

<nav class="drawer" id="drawer" data-open="false" aria-label="Main menu">

    <div class="brand">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark">
        @endif
        <span class="brand-name">Brooks Agro</span>
    </div>

    <div class="drawer-head">
        <strong>{{ $user->displayName() }}</strong>
        <span>{{ $user->departmentSummary() }}</span>
    </div>

    @if ($isCeo || $isDirector)
        <div class="hr-nav" aria-label="{{ $isCeo ? 'CEO' : 'Director' }} navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>🏠 Dashboard</span>
            </a>
            @foreach(($isCeo ? $ceoSections : $directorSections) as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}">
                    <h2>{{ $section }}</h2>
                    @foreach($items as $item)
                        @if($user->canAccess($item['key']))
                            @php
                                $href = match ($item['key']) {
                                    'approvals' => route('approvals.index', isset($item['type']) ? ['type'=>$item['type']] : (isset($item['status']) ? ['status'=>$item['status']] : [])),
                                    'kpi-dashboards' => route('executive.index', ['screen'=>'kpis']),
                                    'reports' => route('executive.index', ['screen'=>'reports']),
                                    'messages', 'notifications', 'security', 'settings' => route('executive.index', ['screen'=>$item['key']]),
                                    'visitors' => route('visitors.index'),
                                    'hr' => route('employees.index'),
                                    'attendance','welfare','advances' => route('hr.records.index',['module'=>$item['key']]),
                                    'staff-performance' => route('poultry.performance.index'),
                                    default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                                };
                            @endphp
                            @if($href)<a class="nav-link" href="{{ $href }}"><span>{{ $item['label'] }}</span></a>@endif
                        @endif
                    @endforeach
                </section>
            @endforeach
        </div>
    @elseif ($isPersonalAssistant)
        <div class="hr-nav" aria-label="Personal Assistant navigation">
            <a class="nav-link" href="{{ route('dashboard') }}"><span>Dashboard</span></a>
            @foreach($assistantSections as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}"><h2>{{ $section }}</h2>
                @foreach($items as $item)
                    @if($user->canAccess($item['key']))
                        @php $href = match($item['key']) {
                            'reports', 'messages', 'notifications' => route('executive.index',['screen'=>$item['key']]),
                            'approvals' => route('approvals.index',['status'=>$item['status'] ?? 'pending']),
                            'visitors' => route('visitors.index'),
                            default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                        }; @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}"><span>{{ $item['label'] }}</span></a>@endif
                    @endif
                @endforeach
                </section>
            @endforeach
        </div>
    @elseif ($isProductionManager)
        <div class="hr-nav" aria-label="Production Manager navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif><svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>🏠 Dashboard</span></a>
            @foreach($productionSections as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}"><h2>{{ $section }}</h2>
                @foreach($items as $item)
                    @if($user->canAccess($item['key']))
                        @php
                            $href = match($item['key']) {
                                'egg-entry','feed-consumption','mortality-entry' => route('daily.index'),
                                'batches' => route('batches.index'),
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
                                'approvals' => route('approvals.index'),
                                default => \App\Models\Module::where('key',$item['key'])->first()?->url(),
                            };
                        @endphp
                        @if($href)<a class="nav-link" href="{{ $href }}" @if($currentKey===$item['key']) aria-current="page" @endif><span>{{ $item['label'] }}</span></a>@endif
                    @endif
                @endforeach
                </section>
            @endforeach
        </div>
    @elseif ($isHrManager || $isFinanceUser || $isSalesManager)
        <div class="hr-nav" aria-label="{{ $isHrManager ? 'HR navigation' : ($isSalesManager ? 'Sales navigation' : 'Finance navigation') }}">
            <a class="nav-link" href="{{ route('dashboard') }}"
               @if ($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg>
                <span>🏠 Dashboard</span>
            </a>

            @foreach (($isHrManager ? $hrSections : ($isSalesManager ? $salesSections : $financeSections)) as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}">
                    <h2>{{ $section }}</h2>
                    @foreach ($items as $item)
                        @if ($user->canAccess($item['key']))
                            @php
                                $href = $isSalesManager
                                    ? route('sales.index').'#'.($item['anchor'] ?? 'sales-summary')
                                    : ($isHrManager
                                    ? match ($item['key']) {
                                        'hr' => route('employees.index'),
                                        'reports' => route('hr.reports.index'),
                                        'kpi-dashboards' => route('hr.kpis.index'),
                                        'staff-performance' => route('poultry.performance.index'),
                                        'payroll' => route('payroll.index'),
                                        'fleet' => route('hr.fleet.index'),
                                        default => route('hr.records.index', ['module' => $item['key']]),
                                    }
                                    : match ($item['key']) {
                                        'transactions' => route('finance.transactions.index'),
                                        'general-ledger' => route('finance.general-ledger.index'),
                                        'receivables' => route('finance.receivables.index'),
                                        'payables' => route('finance.payables.index'),
                                        'budget' => route('finance.budgets.index'),
                                        'assets' => route('finance.assets.index'),
                                        'cash-book' => route('finance.cash-book.index'),
                                        'bank-book' => route('finance.bank-book.index'),
                                        'payroll' => route('payroll.index'),
                                        'purchases' => route('finance.farm.purchases'),
                                        'production' => route('finance.farm.production'),
                                        'veterinary' => route('finance.farm.veterinary'),
                                        'reports' => route('finance.reports.index'),
                                        'kpi-dashboards' => route('finance.reports.kpis'),
                                        'profit-loss', 'balance-sheet', 'cash-flow', 'trial-balance' => route('finance.reports.statement', ['report' => $item['key']]),
                                        'approvals' => route('approvals.index'),
                                        'audit', 'chart-of-accounts', 'finance-periods', 'finance-accounts', 'payment-methods', 'document-numbering', 'tax-settings', 'budget-settings', 'finance-imports' => route('finance.admin.index', ['screen' => $item['key']]),
                                        default => route('module.placeholder', ['key' => $item['key']]),
                                    });
                            @endphp
                            <a class="nav-link"
                               href="{{ $href }}"
                               @if ($currentKey === $item['key']) aria-current="page" @endif>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </section>
            @endforeach
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
            @if($user->canAccess('my-tasks') || $user->canAccess('messages'))
            <section class="hr-nav-section" aria-label="Worker updates"><h2>💬 Updates</h2>
                @if($user->canAccess('my-tasks'))<a class="nav-link" href="{{ route('module.placeholder',['key'=>'my-tasks']) }}"><span>My Tasks</span></a>@endif
                @if($user->canAccess('messages'))<a class="nav-link" href="{{ route('module.placeholder',['key'=>'messages']) }}"><span>Messages</span></a>@endif
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
