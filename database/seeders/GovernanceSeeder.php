<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Three changes to who can do what.
 |
 |  1. The Production Manager oversees every section, not just Production.
 |  2. The Director reviews and approves. They do not enter data.
 |  3. Reports reach the Director only after the CEO has passed them.
 |
 | Run AFTER every other seeder.
 */
class GovernanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->productionManagerSeesEverySection();
        $this->directorReviewsRatherThanOperates();
        $this->ceoApprovesBeforeDirector();
    }

    /* ------------------------------------------------------------
     | 1. Production Manager — every section
     |
     | Species screens are granted by department, which is correct. But the
     | Production Manager is responsible for all the animals, so they hold
     | every section rather than one.
     |
     | Done by giving the ROLE the species screens directly — the one
     | exception to the department rule, and it earns it: the alternative
     | is remembering to add them to each new section by hand, which
     | someone will eventually forget.
     | ------------------------------------------------------------ */
    private function productionManagerSeesEverySection(): void
    {
        $roleId = DB::table('roles')->where('code', 'production-manager')->value('id');

        if (! $roleId) {
            return;
        }

        $keys = [
            'poultry', 'goats', 'rabbits', 'dogs',
            'batches', 'hatchery', 'breeding', 'egg-inventory',
            'egg-entry', 'mortality-entry', 'mortality-assess',
            'feed-received', 'feed-consumption', 'feed-types',
            'pen-cleaning', 'litter-removal',
            'vaccination', 'vaccination-due', 'veterinary', 'biosecurity',
            'milk-record', 'kindling-record', 'dog-care',
            'production', 'feedmill', 'quality', 'breeds',
            'staff-performance',
            'reports', 'analytics', 'kpi-dashboards',
            'dashboard', 'messages', 'notifications',
        ];

        foreach ($keys as $key) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'can_view'    => true,
                    'can_create'  => true,
                    'can_edit'    => true,
                    'can_delete'  => false,
                    'can_approve' => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        // And posted to every section, so department-scoped lists show
        // every flock rather than one section's.
        $deptIds = DB::table('departments')
            ->where('level', 'department')
            ->where('is_active', true)
            ->pluck('id');

        foreach ($deptIds as $deptId) {
            foreach ($keys as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');

                if (! $moduleId) {
                    continue;
                }

                DB::table('department_module')->updateOrInsert(
                    ['department_id' => $deptId, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => true, 'can_edit' => false,
                     'can_delete' => false, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    /* ------------------------------------------------------------
     | 2. The Director reviews. They do not operate.
     |
     | This is a demotion in access and a promotion in authority, and it is
     | the right way round. An owner who can edit a mortality record is an
     | owner whose approval means nothing — the point of an approval is
     | that the approver did not also do the work.
     |
     | So: superuser is removed, and what remains is reports, analytics and
     | approval rights. Including every financial approval, which was
     | already theirs.
     | ------------------------------------------------------------ */
    private function directorReviewsRatherThanOperates(): void
    {
        $roleId = DB::table('roles')->where('code', 'director')->value('id');

        if (! $roleId) {
            return;
        }

        // No longer bypasses permission checks.
        DB::table('roles')->where('id', $roleId)->update([
            'is_superuser' => false,
            'description'  => 'Reviews and approves. Does not enter operational data.',
            'updated_at'   => now(),
        ]);

        // Start from nothing so nothing is inherited by accident.
        DB::table('module_role')->where('role_id', $roleId)->delete();

        /* What a Director may SEE. */
        $view = [
            'dashboard', 'reports', 'scheduled-reports',
            'analytics', 'kpi-dashboards', 'ai',
            'approvals', 'audit',
            'finance', 'general-ledger', 'cash-book', 'bank-book',
            'receivables', 'payables', 'assets', 'budget',
            'payroll', 'transactions', 'journal-entries', 'chart-of-accounts',
            'purchases', 'purchase-orders', 'quotations',
            'invoices', 'receipts', 'returns', 'customer-ledger',
            'sales', 'customers', 'suppliers', 'inventory',
            'production', 'poultry', 'batches', 'egg-inventory',
            'hr', 'staff-performance', 'contracts',
            'loans', 'advances',
            'messages', 'notifications', 'assign-supervisors',
        ];

        /* What a Director may APPROVE. Money, and the reports that reach them. */
        $approve = [
            'approvals', 'reports',
            'finance', 'payroll', 'transactions', 'journal-entries',
            'general-ledger', 'cash-book', 'bank-book',
            'receivables', 'payables', 'assets', 'budget',
            'purchases', 'purchase-orders', 'quotations',
            'invoices', 'receipts', 'returns',
            'loans', 'advances',
        ];

        foreach ($view as $key) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('module_role')->insert([
                'role_id'     => $roleId,
                'module_id'   => $moduleId,
                'can_view'    => true,
                // Deliberately no create and no edit anywhere.
                'can_create'  => false,
                'can_edit'    => false,
                'can_delete'  => false,
                'can_approve' => in_array($key, $approve, true),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    /* ------------------------------------------------------------
     | 3. Reports pass the CEO before the Director sees them
     |
     | Recorded as settings rather than written into code, because the
     | chain will change — a second reviewer, a different order, a
     | delegation while someone is away.
     |
     | The Reports module is not built yet. This puts the rule in place so
     | the module is written to it rather than retrofitted.
     | ------------------------------------------------------------ */
    private function ceoApprovesBeforeDirector(): void
    {
        $rows = [
            ['key' => 'reports.approval_chain', 'group' => 'reports',
             'value' => '["ceo","director"]', 'type' => 'json',
             'label' => 'Who approves a report, in order',
             'description' => 'A report goes to each role in turn. The Director sees it only after the CEO has passed it.'],

            ['key' => 'reports.require_approval', 'group' => 'reports',
             'value' => '1', 'type' => 'bool',
             'label' => 'Reports need approval before release'],

            ['key' => 'hr.staff_no_prefix_permanent', 'group' => 'hr',
             'value' => 'BAF', 'type' => 'string', 'label' => 'Staff number prefix — permanent'],

            ['key' => 'hr.staff_no_prefix_casual', 'group' => 'hr',
             'value' => 'CAS', 'type' => 'string', 'label' => 'Staff number prefix — casual'],

            ['key' => 'hr.staff_no_prefix_temporary', 'group' => 'hr',
             'value' => 'TMP', 'type' => 'string', 'label' => 'Staff number prefix — temporary'],

            ['key' => 'hr.staff_no_prefix_intern', 'group' => 'hr',
             'value' => 'INT', 'type' => 'string', 'label' => 'Staff number prefix — intern'],

            ['key' => 'hr.staff_no_prefix_vacist', 'group' => 'hr',
             'value' => 'VAC', 'type' => 'string', 'label' => 'Staff number prefix — holiday worker'],
        ];

        foreach ($rows as $r) {
            DB::table('settings')->updateOrInsert(
                ['key' => $r['key']],
                array_merge([
                    'description' => null,
                    'is_locked'   => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ], $r)
            );
        }

        // The CEO stays a superuser and gains the approval right explicitly.
        $ceoId = DB::table('roles')->where('code', 'ceo')->value('id');
        $reportsId = DB::table('modules')->where('key', 'reports')->value('id');

        if ($ceoId && $reportsId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $ceoId, 'module_id' => $reportsId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true,
                 'can_delete' => false, 'can_approve' => true,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }
        $supervisorsId = DB::table('modules')->where('key', 'assign-supervisors')->value('id');
        if ($ceoId && $supervisorsId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id'=>$ceoId,'module_id'=>$supervisorsId],
                ['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,
                 'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]
            );
        }
    }
}
