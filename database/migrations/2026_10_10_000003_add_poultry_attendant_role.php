<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* "Poultry Attendant" job role: a farm worker with the same worker screens (My Work) as a casual worker. */
return new class extends Migration {
    public function up(): void
    {
        if (! DB::table('roles')->where('code', 'poultry-attendant')->exists()) {
            DB::table('roles')->insert(['code' => 'poultry-attendant', 'name' => 'Poultry Attendant', 'is_superuser' => false, 'rank' => 90, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $roleId = DB::table('roles')->where('code', 'poultry-attendant')->value('id');
        $casualId = DB::table('roles')->where('code', 'casual-worker')->value('id');
        if (! $casualId) return;
        foreach (DB::table('module_role')->where('role_id', $casualId)->get() as $grant) {
            DB::table('module_role')->updateOrInsert(['role_id' => $roleId, 'module_id' => $grant->module_id], [
                'can_view' => $grant->can_view, 'can_create' => $grant->can_create, 'can_edit' => $grant->can_edit,
                'can_delete' => $grant->can_delete, 'can_approve' => $grant->can_approve, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'poultry-attendant')->value('id');
        if (! $roleId) return;
        DB::table('module_role')->where('role_id', $roleId)->delete();
        DB::table('roles')->where('id', $roleId)->delete();
    }
};
