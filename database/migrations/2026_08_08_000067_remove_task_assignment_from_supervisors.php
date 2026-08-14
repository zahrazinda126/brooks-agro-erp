<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration { public function up():void { $ids=DB::table('modules')->whereIn('key',['assign-tasks','my-tasks'])->pluck('id');DB::table('module_role')->whereIn('module_id',$ids)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]);DB::table('department_module')->whereIn('module_id',$ids)->update(['can_view'=>false,'can_create'=>false,'can_edit'=>false,'can_delete'=>false,'can_approve'=>false,'updated_at'=>now()]); } public function down():void {} };
