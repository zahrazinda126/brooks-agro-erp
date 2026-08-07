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
        ?? (request()->routeIs('employees.*') ? 'hr' : null);

    /*
     | HR has a focused, task-based menu rather than the generic module
     | catalogue.  It reflects the way the team works: employee records,
     | welfare, development, then reporting.  The checks still come from
     | the permission system, so holding the role alone never bypasses
     | access control.
     */
    $isHrManager = $user->role?->code === 'hr-manager'
        || $user->roles->contains('code', 'hr-manager');
    $isFinanceUser = $user->role?->code === 'finance-manager'
        || $user->roles->contains('code', 'finance-manager')
        || $user->department?->code === 'finance'
        || $user->departments->contains('code', 'finance');
    $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code', 'ceo');

    $ceoSections = [
        'Executive Dashboard' => [
            ['key' => 'analytics', 'label' => 'Executive Summary'],
            ['key' => 'kpi-dashboards', 'label' => 'KPIs & Analytics'],
            ['key' => 'finance', 'label' => 'Financial Dashboard'],
            ['key' => 'production', 'label' => 'Production Dashboard'],
            ['key' => 'poultry', 'label' => 'Livestock Dashboard'],
            ['key' => 'sales', 'label' => 'Sales Dashboard'],
        ],
        'Reports' => [
            ['key' => 'reports', 'label' => 'Executive Reports'], ['key' => 'finance', 'label' => 'Financial Reports'],
            ['key' => 'production', 'label' => 'Production Reports'], ['key' => 'inventory', 'label' => 'Inventory Reports'],
            ['key' => 'hr', 'label' => 'HR Reports'], ['key' => 'sales', 'label' => 'Sales Reports'],
            ['key' => 'scheduled-reports', 'label' => 'Scheduled Reports'],
        ],
        'Operations' => [
            ['key' => 'poultry', 'label' => 'Poultry'], ['key' => 'goats', 'label' => 'Livestock'],
            ['key' => 'inventory', 'label' => 'Inventory & Procurement'], ['key' => 'production', 'label' => 'Production'],
            ['key' => 'quality', 'label' => 'Quality Control'], ['key' => 'veterinary', 'label' => 'Veterinary'],
            ['key' => 'fleet', 'label' => 'Fleet & Maintenance'],
        ],
        'Finance' => [
            ['key' => 'transactions', 'label' => 'Transactions'], ['key' => 'bank-book', 'label' => 'Banking'],
            ['key' => 'general-ledger', 'label' => 'Accounting'], ['key' => 'budget', 'label' => 'Budget'],
            ['key' => 'assets', 'label' => 'Fixed Assets'], ['key' => 'payroll', 'label' => 'Payroll'],
        ],
        'People' => [
            ['key' => 'hr', 'label' => 'Employees'], ['key' => 'attendance', 'label' => 'Attendance'],
            ['key' => 'staff-performance', 'label' => 'Performance'], ['key' => 'welfare', 'label' => 'Welfare'],
            ['key' => 'advances', 'label' => 'Salary Advances'],
        ],
        'Approvals' => [
            ['key' => 'approvals', 'label' => 'Pending Approvals'],
            ['key' => 'approvals', 'label' => 'Purchase Approvals', 'type' => 'purchase-orders'],
            ['key' => 'approvals', 'label' => 'Payment Approvals', 'type' => 'payments'],
            ['key' => 'approvals', 'label' => 'Leave Approvals', 'type' => 'leave'],
            ['key' => 'approvals', 'label' => 'Asset Approvals', 'type' => 'assets'],
        ],
        'Communication' => [
            ['key' => 'messages', 'label' => 'Messages'], ['key' => 'notifications', 'label' => 'Notifications'],
            ['key' => 'messages', 'label' => 'Announcements'],
        ],
        'Administration' => [
            ['key' => 'security', 'label' => 'User Management'], ['key' => 'security', 'label' => 'Roles & Permissions'],
            ['key' => 'settings', 'label' => 'Departments'], ['key' => 'settings', 'label' => 'System Settings'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
        ],
    ];

    $hrSections = [
        'Employees' => [
            ['key' => 'hr', 'label' => 'Employee Register'],
            ['key' => 'attendance', 'label' => 'Attendance'],
            ['key' => 'leave', 'label' => 'Leave'],
            ['key' => 'overtime', 'label' => 'Overtime'],
            ['key' => 'contracts', 'label' => 'Contracts'],
        ],
        'Welfare' => [
            ['key' => 'advances', 'label' => 'Salary Advance'],
            ['key' => 'welfare', 'label' => 'Welfare'],
            ['key' => 'employee-medical', 'label' => 'Medical Records'],
            ['key' => 'discipline', 'label' => 'Disciplinary'],
        ],
        'Payroll' => [
            ['key' => 'payroll', 'label' => 'Payroll'],
        ],
        'Development' => [
            ['key' => 'training', 'label' => 'Training'],
            ['key' => 'staff-performance', 'label' => 'Performance Reviews'],
        ],
        'Reports' => [
            ['key' => 'reports', 'label' => 'Reports'],
            ['key' => 'kpi-dashboards', 'label' => 'KPI Dashboard'],
        ],
    ];

    $financeSections = [
        'Daily Operations' => [
            ['key' => 'transactions', 'label' => 'Transaction Register'],
            ['key' => 'receipts', 'label' => 'Money Received'],
            ['key' => 'payments', 'label' => 'Money Paid'],
            ['key' => 'cash-book', 'label' => 'Cash Book'],
            ['key' => 'bank-book', 'label' => 'Bank Book'],
        ],
        'Accounting' => [
            ['key' => 'general-ledger', 'label' => 'General Ledger'],
            ['key' => 'receivables', 'label' => 'Accounts Receivable'],
            ['key' => 'payables', 'label' => 'Accounts Payable'],
            ['key' => 'budget', 'label' => 'Budget'],
            ['key' => 'assets', 'label' => 'Fixed Assets'],
        ],
        'Farm Operations' => [
            ['key' => 'purchases', 'label' => 'Purchases'],
            ['key' => 'production', 'label' => 'Production'],
            ['key' => 'veterinary', 'label' => 'Veterinary'],
        ],
        'Reports' => [
            ['key' => 'reports', 'label' => 'Financial Reports'],
            ['key' => 'kpi-dashboards', 'label' => 'KPI Dashboard'],
            ['key' => 'profit-loss', 'label' => 'Income Statement'],
            ['key' => 'balance-sheet', 'label' => 'Balance Sheet'],
            ['key' => 'cash-flow', 'label' => 'Cash Flow Statement'],
            ['key' => 'trial-balance', 'label' => 'Trial Balance'],
        ],
        'Administration' => [
            ['key' => 'approvals', 'label' => 'Approval Queue'],
            ['key' => 'audit', 'label' => 'Audit Trail'],
            ['key' => 'settings', 'label' => 'Settings'],
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

    @if ($isCeo)
        <div class="hr-nav" aria-label="CEO navigation">
            <a class="nav-link" href="{{ route('dashboard') }}" @if($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg><span>Dashboard</span>
            </a>
            @foreach($ceoSections as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}">
                    <h2>{{ $section }}</h2>
                    @foreach($items as $item)
                        @if($user->canAccess($item['key']))
                            @php
                                $href = $item['key'] === 'approvals'
                                    ? route('approvals.index', isset($item['type']) ? ['type' => $item['type']] : [])
                                    : \App\Models\Module::where('key', $item['key'])->first()?->url();
                            @endphp
                            @if($href)<a class="nav-link" href="{{ $href }}"><span>{{ $item['label'] }}</span></a>@endif
                        @endif
                    @endforeach
                </section>
            @endforeach
        </div>
    @elseif ($isHrManager || $isFinanceUser)
        <div class="hr-nav" aria-label="{{ $isHrManager ? 'HR navigation' : 'Finance navigation' }}">
            <a class="nav-link" href="{{ route('dashboard') }}"
               @if ($currentKey === 'dashboard') aria-current="page" @endif>
                <svg class="nav-icon" aria-hidden="true"><use href="#i-home"></use></svg>
                <span>Dashboard</span>
            </a>

            @foreach (($isHrManager ? $hrSections : $financeSections) as $section => $items)
                <section class="hr-nav-section" aria-label="{{ $section }}">
                    <h2>{{ $section }}</h2>
                    @foreach ($items as $item)
                        @if ($user->canAccess($item['key']))
                            @php
                                $href = $isHrManager
                                    ? match ($item['key']) {
                                        'hr' => route('employees.index'),
                                        'reports' => route('hr.reports.index'),
                                        'kpi-dashboards' => route('hr.kpis.index'),
                                        'staff-performance' => route('poultry.performance.index'),
                                        'payroll' => route('module.placeholder', ['key' => 'payroll']),
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
                                        'purchases' => route('finance.farm.purchases'),
                                        'production' => route('finance.farm.production'),
                                        'veterinary' => route('finance.farm.veterinary'),
                                        'reports' => route('finance.reports.index'),
                                        'kpi-dashboards' => route('finance.reports.kpis'),
                                        'profit-loss', 'balance-sheet', 'cash-flow', 'trial-balance' => route('finance.reports.statement', ['report' => $item['key']]),
                                        default => route('module.placeholder', ['key' => $item['key']]),
                                    };
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
                $holdsCurrent = $modules->contains(function ($m) use ($currentKey) {
                    return $m->key === $currentKey
                        || $m->children->contains('key', $currentKey);
                });
            @endphp

            <details class="nav-group"
                     data-group="{{ Str::slug($groupName) }}"
                     @if ($holdsCurrent) open @endif>

                <summary>
                    <span>{{ $groupName }}</span>
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
