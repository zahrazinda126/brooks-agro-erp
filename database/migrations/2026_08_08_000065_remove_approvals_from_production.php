<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->where('key', 'approvals')->value('id');
        if (! $moduleId) return;

        $roleIds = DB::table('roles')->whereIn('code', ['production-manager'])->pluck('id');
        DB::table('module_role')->where('module_id', $moduleId)->whereIn('role_id', $roleIds)->update([
            'can_view'=>false, 'can_create'=>false, 'can_edit'=>false,
            'can_delete'=>false, 'can_approve'=>false, 'updated_at'=>now(),
        ]);

        $departmentIds = DB::table('departments')->whereIn('code', ['production', 'div-production'])->pluck('id');
        DB::table('department_module')->where('module_id', $moduleId)->whereIn('department_id', $departmentIds)->update([
            'can_view'=>false, 'can_create'=>false, 'can_edit'=>false,
            'can_delete'=>false, 'can_approve'=>false, 'updated_at'=>now(),
        ]);

        foreach ($roleIds as $roleId) {
            DB::table('module_role')->where('role_id', $roleId)->update(['can_approve'=>false, 'updated_at'=>now()]);
        }
    }

    public function down(): void {}
};
