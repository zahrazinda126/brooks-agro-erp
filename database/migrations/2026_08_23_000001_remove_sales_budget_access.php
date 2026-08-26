<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'budget')->value('id');

        if ($roleId && $moduleId) {
            DB::table('module_role')->where('role_id', $roleId)->where('module_id', $moduleId)->delete();
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('code', 'sales-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'budget')->value('id');

        if ($roleId && $moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['can_view'=>true, 'can_create'=>true, 'can_edit'=>true, 'can_delete'=>true, 'can_approve'=>false, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }
    }
};
