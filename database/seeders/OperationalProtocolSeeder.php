<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OperationalProtocolSeeder extends Seeder
{
    public function run(): void
    {
        $approvalsId=DB::table('modules')->where('key','approvals')->value('id');
        $roleIds=DB::table('roles')->whereIn('code',['production-manager','daily-supervisor'])->pluck('id');
        DB::table('module_role')->whereIn('role_id',$roleIds)->update(['can_approve'=>false,'updated_at'=>now()]);
        if($approvalsId)DB::table('module_role')->whereIn('role_id',$roleIds)->where('module_id',$approvalsId)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]);
        if($approvalsId)DB::table('department_module')->where('module_id',$approvalsId)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]);

        DB::table('roles')->where('code','ceo')->update(['is_superuser'=>false,'updated_at'=>now()]);
        $taskIds=DB::table('modules')->whereIn('key',['assign-tasks','my-tasks'])->pluck('id');
        DB::table('module_role')->whereIn('module_id',$taskIds)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]);
        DB::table('department_module')->whereIn('module_id',$taskIds)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]);
        $ceoId=DB::table('roles')->where('code','ceo')->value('id');$moduleId=DB::table('modules')->where('key','assign-supervisors')->value('id');
        if($ceoId&&$moduleId)DB::table('module_role')->updateOrInsert(['role_id'=>$ceoId,'module_id'=>$moduleId],['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]);
    }
}
