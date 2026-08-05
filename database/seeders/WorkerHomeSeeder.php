<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Casual workers do not get a dashboard.
 |
 | A dashboard is a management tool — it summarises so that someone can
 | decide. A worker is not deciding anything; they are recording what
 | happened in their house. Giving them a screen of totals they cannot act
 | on adds a step between signing in and doing the job, and invites the
 | question "what am I supposed to do with this?"
 |
 | So a worker signs in and lands directly on My Work: their flocks, three
 | buttons each. Nothing else.
 |
 | Run AFTER every other seeder, since FoundationSeeder grants dashboard
 | to everyone.
 */
class WorkerHomeSeeder extends Seeder
{
    public function run(): void
    {
        $this->removeDashboardFromWorkers();
        $this->ensureWorkModules();
    }

    private function removeDashboardFromWorkers(): void
    {
        $dashboardId = DB::table('modules')->where('key', 'dashboard')->value('id');

        if (! $dashboardId) {
            return;
        }

        // Roles whose whole job is data entry.
        $workerRoles = DB::table('roles')
            ->whereIn('code', ['casual-worker'])
            ->pluck('id');

        DB::table('module_role')
            ->whereIn('role_id', $workerRoles)
            ->where('module_id', $dashboardId)
            ->delete();

        /*
         | Departments grant the dashboard too, and a worker holds a
         | department. Rather than stripping it from every section — which
         | would take it from supervisors as well — the landing-page logic
         | in the application checks the ROLE, not just the grant.
         |
         | Left here as a comment because it is the kind of thing someone
         | will otherwise "fix" later and wonder why workers see a
         | dashboard again.
         */
    }

    /** Make sure a worker actually has somewhere to land. */
    private function ensureWorkModules(): void
    {
        $roleId = DB::table('roles')->where('code', 'casual-worker')->value('id');

        if (! $roleId) {
            return;
        }

        foreach (['my-work', 'my-tasks', 'notifications', 'messages'] as $key) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');

            if (! $moduleId) {
                continue;
            }

            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'can_view'   => true,
                    'can_create' => true,
                    'can_edit'   => false,
                    'can_delete' => false,
                    'can_approve'=> false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // My Work is their home, so it sorts first in their menu.
        DB::table('modules')->where('key', 'my-work')->update([
            'sort_order' => 1,
            'route'      => 'daily.index',
            'updated_at' => now(),
        ]);
    }
}
