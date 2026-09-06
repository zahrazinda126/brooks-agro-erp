<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'store-keeper')->value('id');
        $moduleId = DB::table('modules')->where('key', 'inventory')->value('id');

        if (! $roleId || ! $moduleId) {
            return;
        }

        DB::table('module_role')->updateOrInsert(
            ['role_id' => $roleId, 'module_id' => $moduleId],
            [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => false,
                'can_approve' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        foreach (DB::table('inventory_stores')->pluck('id') as $storeId) {
            DB::table('inventory_store_role')->updateOrInsert(
                ['inventory_store_id' => $storeId, 'role_id' => $roleId],
                [
                    'can_view' => true,
                    'can_receive' => true,
                    'can_issue' => true,
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
        // Deliberately retain operational access rather than unexpectedly
        // removing a Store Manager's ability to record stock movements.
    }
};
