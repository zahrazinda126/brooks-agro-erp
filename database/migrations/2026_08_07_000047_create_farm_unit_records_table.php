<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_unit_records', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 30)->index();
            $table->string('record_type', 50)->index();
            $table->date('record_date')->index();
            $table->string('subject', 180);
            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('measure_unit', 40)->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->string('status', 40)->default('recorded');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        $gardenId = DB::table('modules')->where('key', 'garden')->value('id');
        if (! $gardenId) {
            $gardenId = DB::table('modules')->insertGetId(['key'=>'garden','name'=>'Garden','group'=>'Livestock','sort_order'=>245,'is_worker_module'=>false,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        $moduleIds = DB::table('modules')->whereIn('key', ['goats','rabbits','dogs','garden'])->pluck('id');
        foreach (DB::table('roles')->whereIn('code', ['production-manager','ceo','director'])->get(['id','code']) as $role) {
            foreach ($moduleIds as $moduleId) DB::table('module_role')->updateOrInsert(['role_id'=>$role->id,'module_id'=>$moduleId], ['can_view'=>true,'can_create'=>$role->code==='production-manager','can_edit'=>$role->code==='production-manager','can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]);
        }
        foreach (DB::table('departments')->whereIn('code', ['production','div-production'])->pluck('id') as $departmentId) {
            foreach ($moduleIds as $moduleId) DB::table('department_module')->updateOrInsert(['department_id'=>$departmentId,'module_id'=>$moduleId], ['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    public function down(): void { Schema::dropIfExists('farm_unit_records'); }
};
