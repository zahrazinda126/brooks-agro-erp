<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Third pass, driven by the functional spec.
 |
 |  1. Five roles the spec names that neither the prototype nor the org
 |     chart had — most importantly Cashier and Read Only.
 |  2. The 22 transactional modules the spec implies.
 |  3. Default system settings.
 |
 | Run AFTER FoundationSeeder and OrgChartSeeder. Safe to re-run.
 */
class SpecSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedModules();
        $this->seedGrants();
        $this->seedSettings();
    }

    /* ------------------------------------------------------------
     | 1. Missing roles
     | ------------------------------------------------------------ */
    private function seedRoles(): void
    {
        $rows = [
            // Separate from Director: this is the technical account that
            // manages users and security, not a business decision-maker.
            ['code' => 'system-admin', 'name' => 'System Administrator',
             'is_superuser' => true, 'rank' => 5,
             'description' => 'Manages accounts, roles and security. Not a business role.'],

            ['code' => 'veterinary-officer', 'name' => 'Veterinary Officer',
             'is_superuser' => false, 'rank' => 30,
             'description' => 'Animal health, treatment and biosecurity.'],

            ['code' => 'store-keeper', 'name' => 'Store Keeper',
             'is_superuser' => false, 'rank' => 40,
             'description' => 'Physical stock: receiving, issuing, counts.'],

            // Critical for segregation of duties: the person who handles
            // cash must NOT be the person who edits the ledger.
            ['code' => 'cashier', 'name' => 'Cashier',
             'is_superuser' => false, 'rank' => 40,
             'description' => 'Receives payments and issues receipts. Cannot post journals.'],

            ['code' => 'read-only', 'name' => 'Read Only',
             'is_superuser' => false, 'rank' => 95,
             'description' => 'Auditors, bank, visiting directors. Sees everything, changes nothing.'],
        ];

        foreach ($rows as $r) {
            DB::table('roles')->updateOrInsert(
                ['code' => $r['code']],
                $r + ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /* ------------------------------------------------------------
     | 2. Transactional modules from the spec
     | ------------------------------------------------------------ */
    private function seedModules(): void
    {
        $rows = [
            // Livestock
            ['key' => 'batches',           'name' => 'Batch Management',   'group' => 'Livestock',    'sort_order' => 105],
            ['key' => 'egg-inventory',     'name' => 'Egg Inventory',      'group' => 'Livestock',    'sort_order' => 115],

            // Supply chain
            ['key' => 'goods-received',    'name' => 'Goods Received',     'group' => 'Supply Chain', 'sort_order' => 300],
            ['key' => 'goods-issued',      'name' => 'Goods Issued',       'group' => 'Supply Chain', 'sort_order' => 310],
            ['key' => 'stock-transfers',   'name' => 'Stock Transfers',    'group' => 'Supply Chain', 'sort_order' => 320],
            ['key' => 'stock-adjustments', 'name' => 'Stock Adjustments',  'group' => 'Supply Chain', 'sort_order' => 330],
            ['key' => 'reorder-levels',    'name' => 'Reorder Levels',     'group' => 'Supply Chain', 'sort_order' => 340],
            ['key' => 'quotations',        'name' => 'Quotations',         'group' => 'Supply Chain', 'sort_order' => 350],
            ['key' => 'purchase-orders',   'name' => 'Purchase Orders',    'group' => 'Supply Chain', 'sort_order' => 360],

            // Commercial
            ['key' => 'invoices',          'name' => 'Invoices',           'group' => 'Commercial',   'sort_order' => 400],
            ['key' => 'receipts',          'name' => 'Receipts',           'group' => 'Commercial',   'sort_order' => 410],
            ['key' => 'returns',           'name' => 'Sales Returns',      'group' => 'Commercial',   'sort_order' => 420],
            ['key' => 'customer-ledger',   'name' => 'Customer Ledger',    'group' => 'Commercial',   'sort_order' => 430],

            // Finance
            ['key' => 'chart-of-accounts', 'name' => 'Chart of Accounts',  'group' => 'Finance',      'sort_order' => 495],
            ['key' => 'journal-entries',   'name' => 'Journal Entries',    'group' => 'Finance',      'sort_order' => 505],

            // People
            ['key' => 'contracts',         'name' => 'Contracts',          'group' => 'People',       'sort_order' => 605],
            ['key' => 'overtime',          'name' => 'Overtime',           'group' => 'People',       'sort_order' => 615],
            ['key' => 'loans',             'name' => 'Staff Loans',        'group' => 'People',       'sort_order' => 645],
            ['key' => 'advances',          'name' => 'Salary Advances',    'group' => 'People',       'sort_order' => 650],
            ['key' => 'employee-medical',  'name' => 'Medical Records',    'group' => 'People',       'sort_order' => 655],
            ['key' => 'staff-performance', 'name' => 'Staff Performance',  'group' => 'People',       'sort_order' => 660],

            // System
            ['key' => 'security',          'name' => 'Security',           'group' => 'System',       'sort_order' => 705],
        ];

        foreach ($rows as $r) {
            DB::table('modules')->updateOrInsert(
                ['key' => $r['key']],
                $r + [
                    'icon'             => null,
                    'is_worker_module' => false,
                    'is_active'        => true,
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ]
            );
        }
    }

    /* ------------------------------------------------------------
     | 3. Grants
     | ------------------------------------------------------------ */
    private function seedGrants(): void
    {
        $grants = [
            'system-admin' => ['security', 'audit', 'settings', 'hr'],

            'veterinary-officer' => [
                'dashboard', 'veterinary', 'biosecurity', 'vaccination',
                'mortality-entry', 'poultry', 'goats', 'rabbits', 'dogs',
                'batches', 'reports', 'messages', 'notifications',
            ],

            'store-keeper' => [
                'dashboard', 'inventory', 'goods-received', 'goods-issued',
                'stock-transfers', 'stock-adjustments', 'reorder-levels',
                'suppliers', 'purchase-orders', 'feedmill', 'feed-received',
                'reports', 'messages', 'notifications',
            ],

            // Cashier: takes money, issues receipts. Deliberately NOT given
            // journal-entries or general-ledger.
            'cashier' => [
                'dashboard', 'receipts', 'invoices', 'customers',
                'customer-ledger', 'cash-book', 'sales',
                'reports', 'messages', 'notifications',
            ],

            'read-only' => [
                'dashboard', 'reports', 'analytics', 'kpi-dashboards',
                'finance', 'general-ledger', 'production', 'inventory',
            ],

            'finance-manager' => [
                'chart-of-accounts', 'journal-entries', 'invoices', 'receipts',
                'returns', 'customer-ledger', 'purchase-orders', 'quotations',
                'loans', 'advances',
            ],

            'hr-manager' => [
                'contracts', 'overtime', 'loans', 'advances',
                'employee-medical', 'staff-performance',
            ],

            'production-manager' => [
                'batches', 'egg-inventory', 'goods-issued', 'staff-performance',
            ],

            'daily-supervisor' => ['batches', 'egg-inventory', 'staff-performance'],
            'inventory-officer' => [
                'goods-received', 'goods-issued', 'stock-transfers',
                'stock-adjustments', 'reorder-levels', 'purchase-orders', 'quotations',
            ],
            'sales-manager' => ['invoices', 'receipts', 'returns', 'customer-ledger'],
        ];

        // Read Only must never get a write bit anywhere.
        $approvers = ['finance-manager', 'hr-manager', 'production-manager', 'daily-supervisor'];

        foreach ($grants as $roleCode => $keys) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }

            $readOnly = $roleCode === 'read-only';

            foreach ($keys as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');
                if (! $moduleId) {
                    continue;
                }

                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $roleId, 'module_id' => $moduleId],
                    [
                        'can_view'    => true,
                        'can_create'  => ! $readOnly,
                        'can_edit'    => ! $readOnly,
                        'can_delete'  => false,
                        'can_approve' => ! $readOnly && in_array($roleCode, $approvers, true),
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }
    }

    /* ------------------------------------------------------------
     | 4. Default settings
     | ------------------------------------------------------------ */
    private function seedSettings(): void
    {
        $rows = [
            ['key' => 'company.name',      'group' => 'company', 'value' => 'Brooks Agro',
             'type' => 'string', 'label' => 'Company name'],
            ['key' => 'company.country',   'group' => 'company', 'value' => 'UG',
             'type' => 'string', 'label' => 'Country'],

            // Locked: changing currency after transactions exist would
            // silently reinterpret every historic amount.
            ['key' => 'finance.currency',  'group' => 'finance', 'value' => 'UGX',
             'type' => 'string', 'label' => 'Currency', 'is_locked' => true],
            ['key' => 'finance.decimals',  'group' => 'finance', 'value' => '0',
             'type' => 'int', 'label' => 'Decimal places shown',
             'description' => 'UGX is normally shown whole. Storage keeps 2dp regardless.'],
            ['key' => 'finance.fiscal_year_start', 'group' => 'finance', 'value' => '07-01',
             'type' => 'string', 'label' => 'Fiscal year starts (MM-DD)'],

            ['key' => 'security.password_min_length', 'group' => 'security', 'value' => '8',
             'type' => 'int', 'label' => 'Minimum password length'],
            ['key' => 'security.password_expiry_days', 'group' => 'security', 'value' => '90',
             'type' => 'int', 'label' => 'Force password change after (days)'],
            ['key' => 'security.max_login_attempts', 'group' => 'security', 'value' => '5',
             'type' => 'int', 'label' => 'Lock account after failed attempts'],
            ['key' => 'security.lockout_minutes', 'group' => 'security', 'value' => '15',
             'type' => 'int', 'label' => 'Lockout duration (minutes)'],
            ['key' => 'security.password_history', 'group' => 'security', 'value' => '3',
             'type' => 'int', 'label' => 'Block reuse of last N passwords'],
            ['key' => 'security.require_2fa_for_roles', 'group' => 'security',
             'value' => '["director","ceo","system-admin","finance-manager"]',
             'type' => 'json', 'label' => 'Roles that must use 2FA'],

            ['key' => 'inventory.low_stock_days', 'group' => 'inventory', 'value' => '14',
             'type' => 'int', 'label' => 'Warn when stock falls below (days of cover)'],

            ['key' => 'notifications.email_enabled', 'group' => 'notifications', 'value' => '1',
             'type' => 'bool', 'label' => 'Send email notifications'],
            ['key' => 'notifications.sms_enabled', 'group' => 'notifications', 'value' => '0',
             'type' => 'bool', 'label' => 'Send SMS notifications'],
        ];

        foreach ($rows as $r) {
            DB::table('settings')->updateOrInsert(
                ['key' => $r['key']],
                $r + [
                    'description' => $r['description'] ?? null,
                    'is_locked'   => $r['is_locked'] ?? false,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }
    }
}
