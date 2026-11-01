<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Second pass over the foundation, driven by the Brooks Agro org chart.
 |
 |  1. Adds the 16 modules the chart names that the prototype never had
 |     (the Finance and HR decomposition, Assets, Budget, Audit...).
 |  2. Creates the four DIVISIONS and files existing departments under
 |     them.
 |  3. Grants the new modules to the roles that own them.
 |
 | Run AFTER FoundationSeeder. Safe to re-run.
 */
class OrgChartSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDivisions();
        $this->seedAdditionalModules();
        $this->grantNewModules();
    }

    /* ------------------------------------------------------------
     | 1. Divisions, and which department sits under which
     | ------------------------------------------------------------ */
    private function seedDivisions(): void
    {
        $divisions = [
            ['code' => 'div-administration', 'name' => 'Administration Division'],
            ['code' => 'div-production',     'name' => 'Production Division'],
            ['code' => 'div-finance',        'name' => 'Finance Division'],
            ['code' => 'div-hr',             'name' => 'HR Division'],
        ];

        foreach ($divisions as $d) {
            DB::table('departments')->updateOrInsert(
                ['code' => $d['code']],
                $d + [
                    'level'      => 'division',
                    'parent_id'  => null,
                    'is_active'  => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $placement = [
            'div-administration' => ['administration', 'supervision'],
            'div-production'     => ['production', 'poultry', 'goats', 'rabbits',
                                     'dogs', 'veterinary', 'inventory'],
            'div-finance'        => ['finance', 'sales'],
            'div-hr'             => ['hr'],
        ];

        foreach ($placement as $divCode => $deptCodes) {
            $divId = DB::table('departments')->where('code', $divCode)->value('id');
            if (! $divId) {
                continue;
            }

            DB::table('departments')
                ->whereIn('code', $deptCodes)
                ->update(['parent_id' => $divId, 'level' => 'department', 'updated_at' => now()]);
        }
    }

    /* ------------------------------------------------------------
     | 2. Modules the org chart names but the prototype lacked
     | ------------------------------------------------------------ */
    private function seedAdditionalModules(): void
    {
        $rows = [
            // Finance division, broken out properly
            ['key' => 'general-ledger', 'name' => 'General Ledger',      'group' => 'Finance',      'sort_order' => 500],
            ['key' => 'cash-book',      'name' => 'Cash Book',           'group' => 'Finance',      'sort_order' => 510],
            ['key' => 'bank-book',      'name' => 'Bank Book',           'group' => 'Finance',      'sort_order' => 520],
            ['key' => 'receivables',    'name' => 'Accounts Receivable', 'group' => 'Finance',      'sort_order' => 530],
            ['key' => 'payables',       'name' => 'Accounts Payable',    'group' => 'Finance',      'sort_order' => 540],
            ['key' => 'assets',         'name' => 'Fixed Assets',        'group' => 'Finance',      'sort_order' => 550],
            ['key' => 'budget',         'name' => 'Budget',              'group' => 'Finance',      'sort_order' => 560],

            // HR division, broken out properly
            ['key' => 'attendance',     'name' => 'Attendance',          'group' => 'People',       'sort_order' => 600],
            ['key' => 'leave',          'name' => 'Leave',               'group' => 'People',       'sort_order' => 610],
            ['key' => 'training',       'name' => 'Training',            'group' => 'People',       'sort_order' => 620],
            ['key' => 'discipline',     'name' => 'Discipline',          'group' => 'People',       'sort_order' => 630],
            ['key' => 'welfare',        'name' => 'Welfare',             'group' => 'People',       'sort_order' => 640],

            // Operations gap: the prototype recorded feed IN but never feed USED
            ['key' => 'feed-consumption', 'name' => 'Feed Consumption',  'group' => 'Daily Work',   'sort_order' => 380,
             'is_worker_module' => true],

            // Administration / reporting
            ['key' => 'audit',            'name' => 'Audit Trail',       'group' => 'System',       'sort_order' => 700],
            ['key' => 'kpi-dashboards',   'name' => 'KPI Dashboards',    'group' => 'Insight',      'sort_order' => 710],
            ['key' => 'scheduled-reports','name' => 'Scheduled Reports', 'group' => 'Insight',      'sort_order' => 720],
        ];

        foreach ($rows as $r) {
            DB::table('modules')->updateOrInsert(
                ['key' => $r['key']],
                $r + [
                    'icon'             => null,
                    'is_worker_module' => $r['is_worker_module'] ?? false,
                    'is_active'        => true,
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ]
            );
        }
    }

    /* ------------------------------------------------------------
     | 3. Who can open the new screens
     | ------------------------------------------------------------ */
    private function grantNewModules(): void
    {
        $roleGrants = [
            'finance-manager' => ['general-ledger', 'cash-book', 'bank-book', 'receivables',
                                  'payables', 'assets', 'budget', 'kpi-dashboards'],
            'hr-manager'      => ['attendance', 'leave', 'training', 'discipline',
                                  'welfare', 'kpi-dashboards'],
            'pa'              => ['audit', 'kpi-dashboards', 'scheduled-reports'],
            'production-manager' => ['feed-consumption', 'attendance', 'kpi-dashboards'],
            'daily-supervisor'   => ['feed-consumption', 'attendance'],
            'casual-worker'      => ['feed-consumption'],
            'poultry-attendant'  => ['feed-consumption'],
        ];

        $approvers = ['finance-manager', 'hr-manager', 'daily-supervisor'];

        foreach ($roleGrants as $roleCode => $keys) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }

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
                        'can_edit'    => ! in_array($roleCode, \App\Models\User::DATA_ENTRY_ROLES, true),
                        'can_delete'  => false,
                        'can_approve' => in_array($roleCode, $approvers, true),
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }

        $deptGrants = [
            'finance' => ['general-ledger', 'cash-book', 'bank-book', 'receivables',
                          'payables', 'assets', 'budget'],
            'hr'      => ['attendance', 'leave', 'training', 'discipline', 'welfare'],
        ];

        foreach ($deptGrants as $deptCode => $keys) {
            $deptId = DB::table('departments')->where('code', $deptCode)->value('id');
            if (! $deptId) {
                continue;
            }

            foreach ($keys as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');
                if (! $moduleId) {
                    continue;
                }

                DB::table('department_module')->updateOrInsert(
                    ['department_id' => $deptId, 'module_id' => $moduleId],
                    [
                        'can_view'   => true,
                        'can_create' => true,
                        'can_edit'   => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
