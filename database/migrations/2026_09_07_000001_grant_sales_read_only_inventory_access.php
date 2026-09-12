<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'inventory')->value('id');

        if (! $roleId) {
            return;
        }

        if ($moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'can_view' => true,
                    'can_create' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_approve' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach (DB::table('inventory_stores')->where('is_active', true)->pluck('id') as $storeId) {
            DB::table('inventory_store_role')->updateOrInsert(
                ['inventory_store_id' => $storeId, 'role_id' => $roleId],
                [
                    'can_view' => true,
                    'can_receive' => false,
                    'can_issue' => false,
                    'can_adjust' => false,
                    'can_view_value' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'inventory')->value('id');

        if (! $roleId) {
            return;
        }

        DB::table('inventory_store_role')->where('role_id', $roleId)->delete();
        if ($moduleId) {
            DB::table('module_role')->where('role_id', $roleId)->where('module_id', $moduleId)->delete();
        }
    }
};
