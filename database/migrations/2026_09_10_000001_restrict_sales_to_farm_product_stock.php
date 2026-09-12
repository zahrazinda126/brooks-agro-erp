<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'inventory')->value('id');

        if (! $roleId) return;

        DB::table('inventory_store_role')->where('role_id', $roleId)->delete();
        if ($moduleId) {
            DB::table('module_role')->where('role_id', $roleId)->where('module_id', $moduleId)->delete();
        }
    }

    public function down(): void
    {
        // The previous migration restores this access when rolling back further.
    }
};
