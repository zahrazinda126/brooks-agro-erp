<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class Module extends Model
{
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
}
