<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $moduleId=DB::table('modules')->where('key','budget')->value('id');
        if (! $moduleId) return;
        foreach (DB::table('roles')->where('code','hr-manager')->pluck('id') as $roleId) {
            DB::table('module_role')->updateOrInsert(['role_id'=>$roleId,'module_id'=>$moduleId],['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach (DB::table('departments')->whereIn('code',['hr','div-hr'])->pluck('id') as $departmentId) {
            DB::table('department_module')->updateOrInsert(['department_id'=>$departmentId,'module_id'=>$moduleId],['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void {}
};
