<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->id(); $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
            $table->string('unit_code',30); $table->date('starts_on'); $table->date('ends_on')->nullable();
            $table->string('shift',30)->default('all_day'); $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true); $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(); $table->index(['unit_code','starts_on','ends_on','is_active']);
        });
        Schema::create('farm_tasks', function (Blueprint $table) {
            $table->id(); $table->foreignId('supervisor_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->restrictOnDelete(); $table->string('unit_code',30);
            $table->date('task_date'); $table->string('title',180); $table->text('instructions')->nullable();
            $table->string('status',25)->default('assigned'); $table->text('completion_note')->nullable();
            $table->timestamp('submitted_at')->nullable(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable(); $table->timestamps();
            $table->index(['worker_id','task_date','status']);
        });

        foreach (['farm_unit_records','livestock_feed_records','livestock_health_records','livestock_mortality_records','livestock_production_records','mortality_records'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('verification_status',20)->default('submitted');
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable(); $table->text('verification_note')->nullable();
            });
            DB::table($name)->update(['verification_status'=>'approved','verified_at'=>now()]);
        }
        DB::table('egg_collections')->where('status','submitted')->update(['status'=>'approved','approved_at'=>now()]);
        DB::table('feed_consumptions')->where('status','submitted')->update(['status'=>'approved','approved_at'=>now()]);

        $moduleIds=DB::table('modules')->whereIn('key',['assign-supervisors','assign-tasks','my-tasks'])->pluck('id','key');
        foreach(DB::table('roles')->whereIn('code',['ceo','director','daily-supervisor','casual-worker','production-manager'])->get(['id','code']) as $role){
            foreach($moduleIds as $key=>$id){$allowed=match($key){'assign-supervisors'=>in_array($role->code,['ceo','director'],true),'assign-tasks'=>in_array($role->code,['daily-supervisor','ceo','director'],true),'my-tasks'=>in_array($role->code,['casual-worker','daily-supervisor','production-manager'],true)};if($allowed)DB::table('module_role')->updateOrInsert(['role_id'=>$role->id,'module_id'=>$id],['can_view'=>true,'can_create'=>$key!=='my-tasks','can_edit'=>true,'can_delete'=>false,'can_approve'=>$key==='assign-tasks','created_at'=>now(),'updated_at'=>now()]);}
        }
    }
    public function down(): void {}
};
