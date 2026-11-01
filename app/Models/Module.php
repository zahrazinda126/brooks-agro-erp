<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class Module extends Model
{
    /*
     | Menu groups for the module-driven menu (System Administrator and accounts without a
     | role workspace), laid out as in Brooks Farm Hub: the management places first, then each
     | farm department as its own place, then Settings. Only where a screen is listed changes;
     | who may open it is still decided by the permission system.
     */
    private const NAVIGATION = [
        'Finance' => ['finance', 'sales', 'customers', 'invoices', 'receipts', 'returns', 'customer-ledger',
            'payroll', 'expenses', 'payments', 'transactions', 'journal-entries', 'chart-of-accounts',
            'general-ledger', 'cash-book', 'bank-book', 'bank-reconcile', 'receivables', 'payables',
            'assets', 'budget', 'data-entry', 'loans', 'advances'],
        'People' => ['hr', 'attendance', 'leave', 'overtime', 'contracts', 'welfare', 'employee-medical',
            'discipline', 'training', 'staff-performance'],
        'Operations' => ['production', 'veterinary', 'quality', 'maintenance', 'fleet', 'visitors',
            'assign-supervisors', 'assign-tasks', 'approvals', 'messages', 'notifications', 'my-work',
            'my-tasks', 'my-performance'],
        'Reports' => ['reports', 'analytics', 'kpi-dashboards', 'scheduled-reports', 'trial-balance',
            'profit-loss', 'balance-sheet', 'cash-flow'],
        // Departments
        'Poultry' => ['poultry', 'flocks', 'batches', 'breeds', 'egg-entry', 'egg-inventory',
            'bird-consumption', 'feed-consumption', 'health', 'medications', 'mortality-entry',
            'mortality-assess', 'vaccination', 'biosecurity', 'hatchery'],
        'Goats' => ['goats', 'breeding', 'milk-record'],
        'Rabbits' => ['rabbits', 'kindling-record'],
        'Dogs' => ['dogs', 'dog-care'],
        'Garden' => ['garden'],
        'Inventory' => ['inventory', 'stores', 'feedmill', 'feed-types', 'feed-received', 'goods-received',
            'goods-issued', 'stock-transfers', 'stock-adjustments', 'reorder-levels', 'purchases',
            'purchase-orders', 'quotations', 'suppliers'],
        // System set-up, in one place
        'Settings' => ['settings', 'security', 'audit', 'ai', 'finance-periods', 'finance-accounts',
            'payment-methods', 'document-numbering', 'tax-settings', 'budget-settings', 'finance-imports'],
    ];

    /** Groups shown under the "Departments" heading of the menu. */
    public const DEPARTMENT_GROUPS = ['Poultry', 'Goats', 'Rabbits', 'Dogs', 'Garden', 'Inventory'];

    private const NAVIGATION_LABELS = [
        'batches' => 'Birds', 'flocks' => 'Birds', 'egg-entry' => 'Egg Collection',
        'egg-inventory' => 'Egg Collection', 'feed-consumption' => 'Feeding',
        'feedmill' => 'Feed Stock', 'feed-types' => 'Feed Types', 'employee-medical' => 'Medical Records',
        'staff-performance' => 'Performance', 'discipline' => 'Disciplinary',
        'hr' => 'Employees', 'loans' => 'Salary Advances',
    ];
    protected $fillable = ['key', 'name', 'group', 'parent_key', 'is_container',
                           'route', 'icon', 'sort_order', 'is_worker_module', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_worker_module' => 'boolean',
            'is_container'     => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve']);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve']);
    }

    /* ---------------------------------------------------------------
     | Nesting
     | --------------------------------------------------------------- */

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_key', 'key');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_key', 'key')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function isTopLevel(): bool
    {
        return $this->parent_key === null;
    }

    /**
     * A heading rather than a screen — Money In, The Books. It holds
     * things and cannot be opened.
     */
    public function isContainer(): bool
    {
        return $this->is_container || $this->route === null;
    }

    /**
     * Where this module lives. Falls back to a placeholder until the
     * module is built, so the sidebar never produces a dead link.
     */
    public function url(): string
    {
        $builtRoutes = ['payroll'=>'payroll.index', 'sales'=>'sales.index', 'customers'=>'sales.index', 'invoices'=>'sales.index', 'receipts'=>'sales.index', 'returns'=>'sales.index', 'customer-ledger'=>'sales.index', 'feed-received'=>'operations.feed-stock.index', 'reports'=>'finance.reports.index', 'kpi-dashboards'=>'finance.reports.kpis', 'purchases'=>'finance.farm.purchases', 'production'=>'finance.farm.production', 'veterinary'=>'finance.farm.veterinary', 'approvals' => 'approvals.index', 'messages' => 'communications.index', 'transactions' => 'finance.transactions.index', 'general-ledger' => 'finance.general-ledger.index', 'receivables' => 'finance.receivables.index', 'payables' => 'finance.payables.index', 'budget' => 'finance.budgets.index', 'assets' => 'finance.assets.index', 'cash-book' => 'finance.cash-book.index', 'bank-book' => 'finance.bank-book.index'];
        foreach (['inventory','goods-received','goods-issued','stock-transfers','stock-adjustments','reorder-levels'] as $inventoryKey) {
            $builtRoutes[$inventoryKey] = 'inventory.index';
        }
        $builtRoutes['visitors'] = 'visitors.index';
        $builtRoutes['assign-supervisors'] = 'supervision.index';
        if (isset($builtRoutes[$this->key]) && Route::has($builtRoutes[$this->key])) {
            return route($builtRoutes[$this->key]);
        }
        if (in_array($this->key, ['profit-loss','balance-sheet','cash-flow','trial-balance'], true)) return route('finance.reports.statement', ['report'=>$this->key]);
        if (in_array($this->key, ['goats','rabbits','dogs','garden'], true) && Route::has('operations.units.index')) return route('operations.units.index', ['unit'=>$this->key]);
        if (in_array($this->key, ['audit','chart-of-accounts','finance-periods','finance-accounts','payment-methods','document-numbering','tax-settings','budget-settings','finance-imports'], true) && Route::has('finance.admin.index')) {
            return route('finance.admin.index', ['screen' => $this->key]);
        }
        if (in_array($this->key, ['security', 'settings'], true) && Route::has('executive.index')) {
            return route('executive.index', ['screen' => $this->key]);
        }
        if ($this->key === 'staff-performance' && Route::has('poultry.performance.index')) {
            return route('poultry.performance.index');
        }

        // Seeded modules whose work is done on an existing screen.
        if (isset(self::EXISTING_SCREENS[$this->key]) && Route::has(self::EXISTING_SCREENS[$this->key][0])) {
            [$routeName, $parameters] = self::EXISTING_SCREENS[$this->key] + [1 => []];
            return route($routeName, $parameters);
        }

        if ($this->route && Route::has($this->route)) {
            return route($this->route);
        }

        return route('module.placeholder', ['key' => $this->key]);
    }

    /** Module key => [route name, parameters] for modules served by a screen built under another name. */
    private const EXISTING_SCREENS = [
        'dashboard' => ['dashboard'],
        // People: HR record screens
        'attendance' => ['hr.records.index', ['module' => 'attendance']],
        'leave' => ['hr.records.index', ['module' => 'leave']],
        'overtime' => ['hr.records.index', ['module' => 'overtime']],
        'contracts' => ['hr.records.index', ['module' => 'contracts']],
        'discipline' => ['hr.records.index', ['module' => 'discipline']],
        'training' => ['hr.records.index', ['module' => 'training']],
        'welfare' => ['hr.records.index', ['module' => 'welfare']],
        'employee-medical' => ['hr.records.index', ['module' => 'employee-medical']],
        'advances' => ['hr.records.index', ['module' => 'advances']],
        'loans' => ['hr.records.index', ['module' => 'advances']],
        'my-performance' => ['hr.performance.index'],
        // Daily work and livestock
        'my-tasks' => ['daily.index'],
        'pen-cleaning' => ['daily.index'],
        'litter-removal' => ['daily.index'],
        'vaccination-due' => ['vaccination-schedule.index'],
        'vaccination' => ['vaccination-schedule.index'],
        'medications' => ['health.index'],
        'flocks' => ['batches.index'],
        'egg-inventory' => ['collections.index'],
        'milk-record' => ['operations.units.index', ['unit' => 'goats']],
        'breeding' => ['operations.units.index', ['unit' => 'goats']],
        'kindling-record' => ['operations.units.index', ['unit' => 'rabbits']],
        'dog-care' => ['operations.units.index', ['unit' => 'dogs']],
        'assign-tasks' => ['poultry-houses.index'],
        // Operations and supply chain
        'fleet' => ['hr.fleet.index'],
        'feedmill' => ['operations.feed-stock.index'],
        'stores' => ['inventory.index'],
        'suppliers' => ['finance.payables.index'],
        'purchase-orders' => ['finance.farm.purchases'],
        // Finance
        'finance' => ['finance.transactions.index'],
        'data-entry' => ['finance.transactions.create'],
        'payments' => ['finance.transactions.index'],
        'expenses' => ['finance.transactions.index'],
        'money-out' => ['finance.transactions.index'],
        'money-in' => ['finance.receivables.index'],
        'journal-entries' => ['finance.general-ledger.index'],
        'the-books' => ['finance.general-ledger.index'],
        'bank-reconcile' => ['finance.bank-book.index'],
        'statements' => ['finance.reports.index'],
        'analytics' => ['finance.reports.kpis'],
        'notifications' => ['communications.index'],
    ];

    /** False for seeded modules that still only have the "not built yet" placeholder. */
    public function isBuilt(): bool
    {
        return $this->url() !== route('module.placeholder', ['key' => $this->key]);
    }

    /*
     | Which sprite symbol to draw beside this module.
     |
     | Derived from the key rather than read from the `icon` column: that
     | column holds Font Awesome class names inherited from the prototype
     | ("fa-egg"), and we no longer load Font Awesome. Keeping the mapping
     | in code means a new module gets a sensible icon with no extra data
     | entry, and there is one place to change if the set is redrawn.
     */
    private const ICONS = [
        'dashboard' => 'home',
        'my-work' => 'clipboard', 'my-tasks' => 'clipboard',
        'my-performance' => 'chart',

        // Livestock
        'flocks' => 'box', 'batches' => 'box', 'breeds' => 'dna',
        'poultry' => 'egg', 'egg-entry' => 'egg', 'egg-inventory' => 'egg',
        'hatchery' => 'egg',
        'goats' => 'paw', 'rabbits' => 'paw', 'dogs' => 'paw', 'dog-care' => 'paw', 'garden' => 'grain',
        'bird-consumption' => 'paw',
        'milk-record' => 'coins', 'kindling-record' => 'paw',
        'breeding' => 'dna',
        'health' => 'vet', 'veterinary' => 'vet', 'mortality-entry' => 'vet',
        'medications' => 'syringe', 'mortality-assess' => 'check-circle',
        'biosecurity' => 'shield',
        'vaccination' => 'syringe', 'vaccination-due' => 'syringe',

        // Operations
        'production' => 'factory',
        'feedmill' => 'grain', 'feed-received' => 'grain',
        'feed-consumption' => 'grain', 'feed-types' => 'grain',
        'quality' => 'badge',
        'maintenance' => 'wrench', 'pen-cleaning' => 'wrench', 'litter-removal' => 'wrench',
        'fleet' => 'truck',

        // Supply chain
        'stores' => 'box', 'inventory' => 'box',
        'goods-received' => 'box', 'goods-issued' => 'box',
        'stock-transfers' => 'truck', 'stock-adjustments' => 'box',
        'reorder-levels' => 'bell',
        'purchases' => 'cart', 'purchase-orders' => 'cart', 'quotations' => 'cart',
        'suppliers' => 'truck',

        // Money
        'money-in' => 'coins', 'money-out' => 'cash',
        'the-books' => 'ledger', 'statements' => 'chart',
        'sales' => 'receipt', 'invoices' => 'receipt', 'receipts' => 'receipt',
        'returns' => 'receipt', 'customers' => 'users', 'customer-ledger' => 'ledger',
        'payments' => 'cash', 'expenses' => 'cash',
        'finance' => 'coins', 'payroll' => 'cash', 'data-entry' => 'ledger',
        'transactions' => 'ledger', 'general-ledger' => 'ledger',
        'chart-of-accounts' => 'ledger', 'journal-entries' => 'ledger',
        'cash-book' => 'cash', 'bank-book' => 'cash', 'bank-reconcile' => 'check-circle',
        'receivables' => 'coins', 'payables' => 'coins',
        'assets' => 'box', 'budget' => 'chart',
        'trial-balance' => 'ledger', 'profit-loss' => 'chart',
        'balance-sheet' => 'file', 'cash-flow' => 'chart',

        // People
        'hr' => 'users', 'staff' => 'users', 'contracts' => 'file',
        'staff-performance' => 'chart',
        'attendance' => 'calendar', 'leave' => 'calendar', 'overtime' => 'calendar',
        'training' => 'cap', 'discipline' => 'shield', 'welfare' => 'users',
        'loans' => 'cash', 'advances' => 'cash', 'employee-medical' => 'vet',

        // Workflow
        'assign-supervisors' => 'users', 'assign-tasks' => 'clipboard',
        'approvals' => 'check-circle', 'messages' => 'message',
        'notifications' => 'bell',

        // Insight and system
        'reports' => 'file', 'scheduled-reports' => 'calendar',
        'analytics' => 'chart', 'kpi-dashboards' => 'chart',
        'ai' => 'cpu', 'settings' => 'gear',
        'audit' => 'ledger', 'security' => 'lock',
    ];

    public function iconName(): string
    {
        return self::ICONS[$this->key] ?? 'dot';
    }

    public function navigationGroup(): string
    {
        foreach (self::NAVIGATION as $group => $keys) {
            if (in_array($this->key, $keys, true)) {
                return $group;
            }
        }

        return 'Operations';
    }

    public function navigationLabel(): string
    {
        return self::NAVIGATION_LABELS[$this->key] ?? $this->name;
    }

    public static function navigationGroupOrder(string $group): int
    {
        return array_search($group, array_keys(self::NAVIGATION), true) + 1;
    }
}
