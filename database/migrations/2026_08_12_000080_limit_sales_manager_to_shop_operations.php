<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        if (! $roleId) {
            return;
        }

        $allowedIds = DB::table('modules')->whereIn('key', ['dashboard', 'sales'])->pluck('id');
        DB::table('module_role')->where('role_id', $roleId)->whereNotIn('module_id', $allowedIds)->delete();

        foreach ($allowedIds as $moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => false, 'can_approve' => false]
            );
        }
    }

    public function down(): void
    {
        // Access is intentionally not broadened automatically on rollback.
    }
};
