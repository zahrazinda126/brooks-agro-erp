<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $module = DB::table('modules')->where('key', 'assets')->value('id');
        $role = DB::table('roles')->where('code', 'hr-manager')->value('id');
        if (!$module || !$role) return;

        $key = ['module_id' => $module, 'role_id' => $role];
        if (DB::table('module_role')->where($key)->exists()) {
            DB::table('module_role')->where($key)->update([
                'can_view' => true, 'can_create' => true, 'updated_at' => now(),
            ]);
        } else {
            DB::table('module_role')->insert($key + [
                'can_view' => true, 'can_create' => true, 'can_edit' => false,
                'can_delete' => false, 'can_approve' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve any permissions that predated this additive grant.
    }
};
