<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('code', ['finance-manager', 'ceo', 'director'])->pluck('id');
        $modules = DB::table('modules')->whereIn('key', [
            'reports', 'kpi-dashboards', 'profit-loss', 'balance-sheet', 'cash-flow', 'trial-balance',
        ])->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($modules as $moduleId) {
                $existing = DB::table('module_role')->where('role_id', $roleId)->where('module_id', $moduleId)->first();
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $roleId, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false,
                     'can_delete' => false, 'can_approve' => (bool) ($existing?->can_approve ?? false),
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Keep legitimate reporting grants if this data migration is rolled back.
    }
};
