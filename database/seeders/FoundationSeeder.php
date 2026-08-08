<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
 | Seeds the permission foundation: departments, roles, modules and the
 | two grant tables — transcribed directly from the prototype's
 | js/roles.js and js/modules.js so behaviour matches what you tested.
 |
 | Safe to re-run: everything is upserted by its unique code/key.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedRoles();
        $this->seedModules();
        $this->seedRoleGrants();
        $this->seedDepartmentGrants();
        $this->seedAdminAccount();
    }

    /* ------------------------------------------------------------ */

    private function seedDepartments(): void
    {
        $rows = [
            ['code' => 'all',            'name' => 'All'],
            ['code' => 'administration', 'name' => 'Administration'],
            ['code' => 'production',     'name' => 'Production'],
            ['code' => 'poultry',        'name' => 'Poultry'],
            ['code' => 'goats',          'name' => 'Goats'],
            ['code' => 'rabbits',        'name' => 'Rabbits'],
            ['code' => 'dogs',           'name' => 'Dogs'],
            ['code' => 'veterinary',     'name' => 'Veterinary'],
            ['code' => 'inventory',      'name' => 'Inventory'],
            ['code' => 'finance',        'name' => 'Finance'],
            ['code' => 'hr',             'name' => 'HR'],
            ['code' => 'sales',          'name' => 'Sales'],
            ['code' => 'supervision',    'name' => 'Supervision'],
        ];

        foreach ($rows as $r) {
            DB::table('departments')->updateOrInsert(
                ['code' => $r['code']],
                $r + ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedRoles(): void
    {
        $rows = [
            ['code' => 'director',           'name' => 'Director',           'is_superuser' => true,  'rank' => 1],
            ['code' => 'ceo',                'name' => 'CEO',                'is_superuser' => true,  'rank' => 2],
            ['code' => 'pa',                 'name' => 'PA',                 'is_superuser' => false, 'rank' => 10],
            ['code' => 'production-manager', 'name' => 'Production Manager', 'is_superuser' => false, 'rank' => 20],
            ['code' => 'finance-manager',    'name' => 'Finance Manager',    'is_superuser' => false, 'rank' => 20],
            ['code' => 'hr-manager',         'name' => 'HR Manager',         'is_superuser' => false, 'rank' => 20],
            ['code' => 'sales-manager',      'name' => 'Sales Manager',      'is_superuser' => false, 'rank' => 20],
            ['code' => 'inventory-officer',  'name' => 'Inventory Officer',  'is_superuser' => false, 'rank' => 30],
            ['code' => 'department-manager', 'name' => 'Department Manager', 'is_superuser' => false, 'rank' => 30],
            ['code' => 'daily-supervisor',   'name' => 'Daily Supervisor',   'is_superuser' => false, 'rank' => 40],
            ['code' => 'casual-worker',      'name' => 'Casual Worker',      'is_superuser' => false, 'rank' => 90],
        ];

        foreach ($rows as $r) {
            DB::table('roles')->updateOrInsert(
                ['code' => $r['code']],
                $r + ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedModules(): void
    {
        $rows = [
            ['key' => 'dashboard', 'name' => 'Dashboard', 'group' => 'Overview', 'icon' => 'fa-house', 'sort_order' => 10, 'is_worker_module' => false],
            ['key' => 'poultry', 'name' => 'Poultry', 'group' => 'Livestock', 'icon' => 'fa-egg', 'sort_order' => 20, 'is_worker_module' => false],
            ['key' => 'goats', 'name' => 'Goats', 'group' => 'Livestock', 'icon' => 'fa-cow', 'sort_order' => 30, 'is_worker_module' => false],
            ['key' => 'rabbits', 'name' => 'Rabbits', 'group' => 'Livestock', 'icon' => 'fa-paw', 'sort_order' => 40, 'is_worker_module' => false],
            ['key' => 'dogs', 'name' => 'Dogs', 'group' => 'Livestock', 'icon' => 'fa-dog', 'sort_order' => 50, 'is_worker_module' => false],
            ['key' => 'hatchery', 'name' => 'Hatchery', 'group' => 'Livestock', 'icon' => 'fa-kiwi-bird', 'sort_order' => 60, 'is_worker_module' => false],
            ['key' => 'breeding', 'name' => 'Breeding', 'group' => 'Livestock', 'icon' => 'fa-dna', 'sort_order' => 70, 'is_worker_module' => false],
            ['key' => 'veterinary', 'name' => 'Veterinary', 'group' => 'Livestock', 'icon' => 'fa-stethoscope', 'sort_order' => 80, 'is_worker_module' => false],
            ['key' => 'biosecurity', 'name' => 'Biosecurity', 'group' => 'Livestock', 'icon' => 'fa-shield-virus', 'sort_order' => 90, 'is_worker_module' => false],
            ['key' => 'production', 'name' => 'Production', 'group' => 'Operations', 'icon' => 'fa-industry', 'sort_order' => 100, 'is_worker_module' => false],
            ['key' => 'feedmill', 'name' => 'Feed Mill', 'group' => 'Operations', 'icon' => 'fa-wheat-awn', 'sort_order' => 110, 'is_worker_module' => false],
            ['key' => 'quality', 'name' => 'Quality Control', 'group' => 'Operations', 'icon' => 'fa-clipboard-check', 'sort_order' => 120, 'is_worker_module' => false],
            ['key' => 'maintenance', 'name' => 'Maintenance', 'group' => 'Operations', 'icon' => 'fa-screwdriver-wrench', 'sort_order' => 130, 'is_worker_module' => false],
            ['key' => 'fleet', 'name' => 'Fleet', 'group' => 'Operations', 'icon' => 'fa-truck', 'sort_order' => 140, 'is_worker_module' => false],
            ['key' => 'inventory', 'name' => 'Inventory', 'group' => 'Supply Chain', 'icon' => 'fa-boxes-stacked', 'sort_order' => 150, 'is_worker_module' => false],
            ['key' => 'purchases', 'name' => 'Purchases', 'group' => 'Supply Chain', 'icon' => 'fa-cart-shopping', 'sort_order' => 160, 'is_worker_module' => false],
            ['key' => 'suppliers', 'name' => 'Suppliers', 'group' => 'Supply Chain', 'icon' => 'fa-truck-field', 'sort_order' => 170, 'is_worker_module' => false],
            ['key' => 'sales', 'name' => 'Sales', 'group' => 'Commercial', 'icon' => 'fa-cash-register', 'sort_order' => 180, 'is_worker_module' => false],
            ['key' => 'customers', 'name' => 'Customers', 'group' => 'Commercial', 'icon' => 'fa-user-group', 'sort_order' => 190, 'is_worker_module' => false],
            ['key' => 'finance', 'name' => 'Finance', 'group' => 'Finance', 'icon' => 'fa-coins', 'sort_order' => 200, 'is_worker_module' => false],
            ['key' => 'payroll', 'name' => 'Payroll', 'group' => 'Finance', 'icon' => 'fa-money-check-dollar', 'sort_order' => 210, 'is_worker_module' => false],
            ['key' => 'data-entry', 'name' => 'Data Entry', 'group' => 'Finance', 'icon' => 'fa-keyboard', 'sort_order' => 220, 'is_worker_module' => false],
            ['key' => 'transactions', 'name' => 'Transactions', 'group' => 'Finance', 'icon' => 'fa-table-list', 'sort_order' => 230, 'is_worker_module' => false],
            ['key' => 'hr', 'name' => 'Human Resources', 'group' => 'People', 'icon' => 'fa-users', 'sort_order' => 240, 'is_worker_module' => false],
            ['key' => 'reports', 'name' => 'Reports', 'group' => 'Insight', 'icon' => 'fa-chart-column', 'sort_order' => 250, 'is_worker_module' => false],
            ['key' => 'analytics', 'name' => 'Analytics', 'group' => 'Insight', 'icon' => 'fa-chart-pie', 'sort_order' => 260, 'is_worker_module' => false],
            ['key' => 'ai', 'name' => 'AI Centre', 'group' => 'Insight', 'icon' => 'fa-brain', 'sort_order' => 270, 'is_worker_module' => false],
            ['key' => 'settings', 'name' => 'Settings', 'group' => 'System', 'icon' => 'fa-gear', 'sort_order' => 280, 'is_worker_module' => false],
            ['key' => 'assign-supervisors', 'name' => 'Daily Supervisors', 'group' => 'Workflow', 'icon' => 'fa-user-tie', 'sort_order' => 290, 'is_worker_module' => false],
            ['key' => 'assign-tasks', 'name' => 'Task Assignment', 'group' => 'Workflow', 'icon' => 'fa-clipboard-list', 'sort_order' => 300, 'is_worker_module' => false],
            ['key' => 'approvals', 'name' => 'Approvals', 'group' => 'Workflow', 'icon' => 'fa-check-double', 'sort_order' => 310, 'is_worker_module' => false],
            ['key' => 'messages', 'name' => 'Messages', 'group' => 'Workflow', 'icon' => 'fa-comments', 'sort_order' => 320, 'is_worker_module' => true],
            ['key' => 'vaccination', 'name' => 'Vaccination', 'group' => 'Livestock', 'icon' => 'fa-syringe', 'sort_order' => 330, 'is_worker_module' => false],
            ['key' => 'my-work', 'name' => 'My Work', 'group' => 'Overview', 'icon' => 'fa-house', 'sort_order' => 340, 'is_worker_module' => true],
            ['key' => 'my-tasks', 'name' => 'My Tasks', 'group' => 'Daily Work', 'icon' => 'fa-list-check', 'sort_order' => 350, 'is_worker_module' => true],
            ['key' => 'feed-received', 'name' => 'Feed Received', 'group' => 'Daily Work', 'icon' => 'fa-wheat-awn', 'sort_order' => 360, 'is_worker_module' => true],
            ['key' => 'mortality-entry', 'name' => 'Mortality', 'group' => 'Daily Work', 'icon' => 'fa-triangle-exclamation', 'sort_order' => 370, 'is_worker_module' => true],
            ['key' => 'vaccination-due', 'name' => 'Vaccination Due', 'group' => 'Daily Work', 'icon' => 'fa-syringe', 'sort_order' => 380, 'is_worker_module' => true],
            ['key' => 'notifications', 'name' => 'Notifications', 'group' => 'Updates', 'icon' => 'fa-bell', 'sort_order' => 390, 'is_worker_module' => true],
            ['key' => 'my-performance', 'name' => 'My Performance', 'group' => 'Updates', 'icon' => 'fa-chart-line', 'sort_order' => 400, 'is_worker_module' => true],
            ['key' => 'egg-entry', 'name' => 'Egg Collection', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 410, 'is_worker_module' => true],
            ['key' => 'pen-cleaning', 'name' => 'Pen Cleaning', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 420, 'is_worker_module' => true],
            ['key' => 'litter-removal', 'name' => 'Litter Removal', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 430, 'is_worker_module' => true],
            ['key' => 'milk-record', 'name' => 'Milk Record', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 440, 'is_worker_module' => true],
            ['key' => 'kindling-record', 'name' => 'Kindling Record', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 450, 'is_worker_module' => true],
            ['key' => 'dog-care', 'name' => 'Dog Care', 'group' => 'Daily Work', 'icon' => null, 'sort_order' => 460, 'is_worker_module' => true],        ];

        foreach ($rows as $r) {
            DB::table('modules')->updateOrInsert(
                ['key' => $r['key']],
                $r + ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /* ------------------------------------------------------------
     | Axis 1: role grants (from ROLE_PERMISSIONS in js/roles.js)
     | Superusers (Director, CEO) are not listed — they bypass checks.
     | ------------------------------------------------------------ */
    private function seedRoleGrants(): void
    {
        $map = [
            'pa' => [
                'dashboard', 'reports', 'analytics', 'ai', 'settings',
                'finance', 'inventory', 'customers', 'suppliers', 'purchases',
                'data-entry', 'transactions',
            ],
            'daily-supervisor' => [
                'dashboard', 'assign-tasks', 'messages',
                'poultry', 'production', 'veterinary', 'inventory',
                'reports', 'vaccination',
            ],
            'inventory-officer' => [
                'dashboard', 'inventory', 'purchases', 'suppliers',
                'feedmill', 'messages', 'reports', 'data-entry', 'transactions',
            ],
            'production-manager' => [
                'dashboard', 'assign-tasks', 'approvals', 'messages',
                'poultry', 'goats', 'rabbits', 'dogs',
                'hatchery', 'breeding', 'veterinary', 'biosecurity',
                'production', 'feedmill', 'quality', 'maintenance', 'fleet',
                'inventory', 'reports', 'analytics', 'vaccination',
            ],
            'finance-manager' => [
                'dashboard', 'finance', 'payroll', 'data-entry', 'transactions',
                'inventory', 'purchases', 'suppliers',
                'sales', 'customers', 'reports', 'analytics',
            ],
            'hr-manager' => [
                'dashboard', 'hr', 'payroll', 'fleet', 'reports',
            ],
            'sales-manager' => [
                'dashboard', 'sales', 'customers',
                'inventory', 'reports', 'analytics',
            ],
            'department-manager' => [
                'dashboard', 'production', 'veterinary', 'reports',
            ],
            // Casual workers get only the worker screens.
            'casual-worker' => [
                'my-work', 'my-tasks', 'my-performance', 'notifications', 'messages',
                'egg-entry', 'mortality-entry', 'feed-received',
                'pen-cleaning', 'litter-removal', 'vaccination-due',
                'milk-record', 'kindling-record', 'dog-care',
            ],
        ];

        // Roles allowed to approve what workers submit.
        $approvers = ['daily-supervisor', 'finance-manager', 'hr-manager'];

        foreach ($map as $roleCode => $keys) {
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
                        'can_edit'    => $roleCode !== 'casual-worker',
                        'can_delete'  => in_array($roleCode, ['production-manager', 'finance-manager', 'hr-manager'], true),
                        'can_approve' => in_array($roleCode, $approvers, true),
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }
    }

    /* ------------------------------------------------------------
     | Axis 2: department grants (from DEPARTMENT_MODULES)
     | ------------------------------------------------------------ */
    private function seedDepartmentGrants(): void
    {
        $map = [
            'poultry'        => ['poultry', 'hatchery', 'vaccination'],
            'goats'          => ['goats', 'breeding', 'vaccination'],
            'rabbits'        => ['rabbits', 'breeding', 'vaccination'],
            'dogs'           => ['dogs', 'vaccination'],
            'production'     => ['poultry', 'goats', 'rabbits', 'dogs',
                                 'production', 'feedmill', 'quality', 'hatchery'],
            'finance'        => ['finance', 'payroll', 'purchases', 'data-entry', 'transactions'],
            'hr'             => ['hr', 'payroll'],
            'sales'          => ['sales', 'customers'],
            'inventory'      => ['inventory', 'purchases', 'suppliers'],
            'veterinary'     => ['veterinary', 'biosecurity'],
            'administration' => ['reports', 'analytics', 'settings', 'ai'],
            'supervision'    => ['assign-tasks', 'approvals', 'messages', 'poultry'],
            // 'all' is intentionally empty: it pairs with superuser roles.
        ];

        foreach ($map as $deptCode => $keys) {
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

    /* ------------------------------------------------------------
     | The one account that exists before anyone logs in.
     | Change this password immediately after first login.
     | ------------------------------------------------------------ */
    private function seedAdminAccount(): void
    {
        $roleId = DB::table('roles')->where('code', 'director')->value('id');
        $deptId = DB::table('departments')->where('code', 'all')->value('id');

        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'name'                 => 'System Administrator',
                'email'                => null,
                'password'             => Hash::make('ChangeMe!2026'),
                'role_id'              => $roleId,
                'department_id'        => $deptId,
                'employee_id'          => null,
                'is_active'            => true,
                'must_change_password' => true,
                'updated_at'           => now(),
                'created_at'           => now(),
            ]
        );
    }
}
