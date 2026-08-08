<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class Module extends Model
{
    private const NAVIGATION = [
        'Poultry' => ['poultry', 'flocks', 'batches', 'breeds', 'egg-entry', 'egg-inventory',
            'bird-consumption', 'feed-consumption', 'health', 'medications', 'mortality-entry',
            'mortality-assess', 'vaccination', 'biosecurity', 'hatchery'],
        'Livestock' => ['goats', 'rabbits', 'dogs', 'dog-care', 'breeding', 'milk-record', 'kindling-record'],
        'Stock' => ['inventory', 'stores', 'feedmill', 'feed-types', 'feed-received', 'goods-received',
            'goods-issued', 'stock-transfers', 'stock-adjustments', 'reorder-levels', 'purchases',
            'purchase-orders', 'quotations', 'suppliers'],
        'Finance' => ['finance', 'sales', 'customers', 'invoices', 'receipts', 'returns', 'customer-ledger',
            'payroll', 'expenses', 'payments', 'transactions', 'journal-entries', 'chart-of-accounts',
            'general-ledger', 'cash-book', 'bank-book', 'bank-reconcile', 'receivables', 'payables',
            'assets', 'budget', 'data-entry', 'loans', 'advances'],
        'People' => ['hr', 'attendance', 'leave', 'overtime', 'contracts', 'welfare', 'employee-medical',
            'discipline', 'training', 'staff-performance'],
        'Reports' => ['reports', 'analytics', 'kpi-dashboards', 'scheduled-reports', 'trial-balance',
            'profit-loss', 'balance-sheet', 'cash-flow'],
        'Administration' => ['settings', 'security', 'audit', 'ai'],
        'Operations' => ['production', 'quality', 'maintenance', 'fleet', 'assign-supervisors', 'assign-tasks',
            'approvals', 'messages', 'notifications', 'my-work', 'my-tasks', 'my-performance'],
    ];

    private const NAVIGATION_LABELS = [
        'batches' => 'Birds', 'flocks' => 'Birds', 'egg-entry' => 'Egg Collection',
        'egg-inventory' => 'Egg Collection', 'feed-consumption' => 'Feeding',
        'feedmill' => 'Feed', 'feed-types' => 'Feed', 'employee-medical' => 'Medical Records',
        'staff-performance' => 'Performance', 'discipline' => 'Disciplinary',
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
        $builtRoutes = ['payroll'=>'payroll.index', 'sales'=>'sales.index', 'customers'=>'sales.index', 'invoices'=>'sales.index', 'receipts'=>'sales.index', 'returns'=>'sales.index', 'customer-ledger'=>'sales.index', 'feed-received'=>'operations.feed-stock.index', 'reports'=>'finance.reports.index', 'kpi-dashboards'=>'finance.reports.kpis', 'purchases'=>'finance.farm.purchases', 'production'=>'finance.farm.production', 'veterinary'=>'finance.farm.veterinary', 'approvals' => 'approvals.index', 'transactions' => 'finance.transactions.index', 'general-ledger' => 'finance.general-ledger.index', 'receivables' => 'finance.receivables.index', 'payables' => 'finance.payables.index', 'budget' => 'finance.budgets.index', 'assets' => 'finance.assets.index', 'cash-book' => 'finance.cash-book.index', 'bank-book' => 'finance.bank-book.index'];
        $builtRoutes['visitors'] = 'visitors.index';
        if (isset($builtRoutes[$this->key]) && Route::has($builtRoutes[$this->key])) {
            return route($builtRoutes[$this->key]);
        }
        if (in_array($this->key, ['profit-loss','balance-sheet','cash-flow','trial-balance'], true)) return route('finance.reports.statement', ['report'=>$this->key]);
        if (in_array($this->key, ['goats','rabbits','dogs','garden'], true) && Route::has('operations.units.index')) return route('operations.units.index', ['unit'=>$this->key]);
        if (in_array($this->key, ['audit','chart-of-accounts','finance-periods','finance-accounts','payment-methods','document-numbering','tax-settings','budget-settings','finance-imports'], true) && Route::has('finance.admin.index')) {
            return route('finance.admin.index', ['screen' => $this->key]);
        }
        if ($this->key === 'staff-performance' && Route::has('poultry.performance.index')) {
            return route('poultry.performance.index');
        }

        if ($this->route && Route::has($this->route)) {
            return route($this->route);
        }

        return route('module.placeholder', ['key' => $this->key]);
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
        'goats' => 'paw', 'rabbits' => 'paw', 'dogs' => 'paw', 'dog-care' => 'paw',
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
