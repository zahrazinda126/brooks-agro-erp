<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | The Finance Manager's remit, and a sidebar shaped for it.
 |
 | Two jobs.
 |
 | 1. STRUCTURE. Finance is grouped the way a finance person thinks —
 |    money coming in, money going out, the books, and the statements that
 |    come off them. Not alphabetically, and not by which screen happened
 |    to be built first.
 |
 | 2. ACCESS. The Finance Manager records every transaction the farm
 |    makes. They get create and edit on all of it, and approve on none of
 |    it, because the Director approves money. The person who writes the
 |    cheque is never the person who signs it.
 |
 | Run AFTER every other seeder.
 */
class FinanceStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->addMissingModules();
        $this->buildContainers();
        $this->nestEverything();
        $this->grantFinanceManager();
    }

    /* ------------------------------------------------------------
     | 1. Screens the farm needs that were never created
     | ------------------------------------------------------------ */
    private function addMissingModules(): void
    {
        $rows = [
            // The statements. These come off the ledger rather than being
            // entered, which is why they sit apart from the day book.
            ['key' => 'trial-balance',  'name' => 'Trial Balance',        'group' => 'Finance', 'sort_order' => 580],
            ['key' => 'profit-loss',    'name' => 'Profit & Loss',        'group' => 'Finance', 'sort_order' => 582],
            ['key' => 'balance-sheet',  'name' => 'Balance Sheet',        'group' => 'Finance', 'sort_order' => 584],
            ['key' => 'cash-flow',      'name' => 'Cash Flow',            'group' => 'Finance', 'sort_order' => 586],

            // Day-to-day money movement, which is most of the job.
            ['key' => 'payments',       'name' => 'Payments Out',         'group' => 'Finance', 'sort_order' => 470],
            ['key' => 'expenses',       'name' => 'Expenses',             'group' => 'Finance', 'sort_order' => 472],
            ['key' => 'bank-reconcile', 'name' => 'Bank Reconciliation',  'group' => 'Finance', 'sort_order' => 545],
        ];

        foreach ($rows as $r) {
            if (! DB::table('modules')->where('key', $r['key'])->exists()) {
                DB::table('modules')->insert(array_merge($r, [
                    'is_worker_module' => false,
                    'is_container'     => false,
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]));
            }
        }
    }

    /* ------------------------------------------------------------
     | 2. Headings that hold things but are not screens themselves
     | ------------------------------------------------------------ */
    private function buildContainers(): void
    {
        $rows = [
            ['key' => 'money-in',   'name' => 'Money In',    'group' => 'Finance',      'sort_order' => 400],
            ['key' => 'money-out',  'name' => 'Money Out',   'group' => 'Finance',      'sort_order' => 460],
            ['key' => 'the-books',  'name' => 'The Books',   'group' => 'Finance',      'sort_order' => 500],
            ['key' => 'statements', 'name' => 'Statements',  'group' => 'Finance',      'sort_order' => 578],
            ['key' => 'stores',     'name' => 'Stores',      'group' => 'Supply Chain', 'sort_order' => 290],
            ['key' => 'staff',      'name' => 'Staff',       'group' => 'People',       'sort_order' => 598],
            ['key' => 'flocks',     'name' => 'Flocks',      'group' => 'Livestock',    'sort_order' => 100],
        ];

        foreach ($rows as $r) {
            DB::table('modules')->updateOrInsert(
                ['key' => $r['key']],
                array_merge($r, [
                    'route'            => null,      // a heading, not a screen
                    'parent_key'       => null,
                    'is_container'     => true,
                    'is_worker_module' => false,
                    'is_active'        => true,
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ])
            );
        }
    }

    /* ------------------------------------------------------------
     | 3. What sits inside what
     |
     | Ordered by how often a finance manager touches it, not
     | alphabetically. Invoices and receipts are daily; the balance sheet
     | is monthly.
     | ------------------------------------------------------------ */
    private function nestEverything(): void
    {
        $tree = [
            /* --- Finance ------------------------------------------- */
            'money-in' => [
                'invoices', 'receipts', 'customers',
                'customer-ledger', 'returns', 'receivables',
            ],

            'money-out' => [
                'payments', 'expenses', 'purchase-orders',
                'quotations', 'suppliers', 'payables',
            ],

            'the-books' => [
                'transactions', 'journal-entries', 'chart-of-accounts',
                'general-ledger', 'cash-book', 'bank-book', 'bank-reconcile',
            ],

            'statements' => [
                'trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow',
            ],

            /* --- Elsewhere, same idea ------------------------------- */
            'stores' => [
                'goods-received', 'goods-issued', 'stock-transfers',
                'stock-adjustments', 'reorder-levels',
            ],

            'staff' => [
                'attendance', 'leave', 'overtime', 'contracts',
                'discipline', 'training', 'welfare', 'employee-medical',
                'loans', 'advances', 'staff-performance',
            ],

            'flocks' => [
                'batches', 'breeds', 'egg-inventory', 'bird-consumption',
            ],
        ];

        // Clear any previous nesting so a re-run is clean.
        DB::table('modules')->update(['parent_key' => null]);

        foreach ($tree as $parent => $children) {
            DB::table('modules')
                ->whereIn('key', $children)
                ->update(['parent_key' => $parent, 'updated_at' => now()]);
        }

        /*
         | Sort order within a parent decides the order shown, so it is set
         | explicitly rather than left to whatever each module was given
         | when it was created.
         */
        foreach ($tree as $parent => $children) {
            foreach (array_values($children) as $i => $key) {
                DB::table('modules')->where('key', $key)->update([
                    'sort_order' => ($i + 1) * 10,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /* ------------------------------------------------------------
     | 4. The Finance Manager
     |
     | They record everything. They approve nothing — the Director does
     | that, and separating the two is the oldest control in accounting.
     | ------------------------------------------------------------ */
    private function grantFinanceManager(): void
    {
        $roleId = DB::table('roles')->where('code', 'finance-manager')->value('id');

        if (! $roleId) {
            return;
        }

        /* Everything money touches. */
        $full = [
            // Money in
            'money-in', 'invoices', 'receipts', 'customers',
            'customer-ledger', 'returns', 'receivables', 'sales',

            // Money out
            'money-out', 'payments', 'expenses', 'purchases',
            'purchase-orders', 'quotations', 'suppliers', 'payables',

            // The books
            'the-books', 'transactions', 'journal-entries',
            'chart-of-accounts', 'general-ledger',
            'cash-book', 'bank-book', 'bank-reconcile',

            // Statements
            'statements', 'trial-balance', 'profit-loss',
            'balance-sheet', 'cash-flow',

            // Assets, budget, payroll
            'finance', 'assets', 'budget', 'payroll',
            'loans', 'advances',

            // What they need to do the job
            'dashboard', 'reports', 'analytics', 'kpi-dashboards',
            'inventory', 'data-entry',
            'messages', 'notifications',
        ];

        foreach ($full as $key) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'can_view'   => true,
                    'can_create' => true,
                    'can_edit'   => true,
                    // Corrections are made by reversing entries, never by
                    // deleting them. A deleted transaction is a hole.
                    'can_delete' => false,
                    /*
                     | Never. The Director approves money.
                     |
                     | A finance manager who can both record a payment and
                     | approve it is a finance manager whose approval means
                     | nothing — and the farm has no control over its own
                     | spending beyond trust.
                     */
                    'can_approve' => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        /*
         | Read-only on the things they must see but must not touch.
         | Staff records inform payroll; the flock register explains what a
         | feed invoice was for.
         */
        $readOnly = [
            'hr', 'staff', 'staff-performance', 'attendance',
            'flocks', 'batches', 'poultry', 'production',
            'audit',
        ];

        foreach ($readOnly as $key) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false,
                 'can_delete' => false, 'can_approve' => false,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // The containers the Director needs to reach their statements.
        $dirId = DB::table('roles')->where('code', 'director')->value('id');

        if ($dirId) {
            foreach (['money-in', 'money-out', 'the-books', 'statements',
                      'trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow',
                      'payments', 'expenses', 'bank-reconcile', 'staff', 'flocks', 'stores'] as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');

                if (! $moduleId) {
                    continue;
                }

                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $dirId, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false,
                     'can_delete' => false, 'can_approve' => true,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
