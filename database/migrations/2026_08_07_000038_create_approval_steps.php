<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('stage');
            $table->string('role_code', 60);
            $table->string('status', 20)->default('waiting');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['approval_id', 'stage']);
            $table->index(['role_code', 'status']);
        });

        foreach (DB::table('approvals')->where('module_key', 'budget')->orderBy('id')->get() as $approval) {
            $stageOneStatus = $approval->status === 'approved' ? 'approved'
                : ($approval->status === 'rejected' ? 'rejected' : 'pending');
            DB::table('approval_steps')->insert([
                ['approval_id'=>$approval->id, 'stage'=>1, 'role_code'=>'ceo', 'status'=>$stageOneStatus,
                 'decided_by'=>$approval->decided_by, 'decided_at'=>$approval->decided_at,
                 'decision_note'=>$approval->decision_note, 'created_at'=>now(), 'updated_at'=>now()],
                ['approval_id'=>$approval->id, 'stage'=>2, 'role_code'=>'director',
                 'status'=>$stageOneStatus === 'approved' ? 'pending' : ($stageOneStatus === 'rejected' ? 'cancelled' : 'waiting'),
                 'decided_by'=>null, 'decided_at'=>null, 'decision_note'=>null, 'created_at'=>now(), 'updated_at'=>now()],
            ]);

            if ($stageOneStatus === 'approved') {
                DB::table('approvals')->where('id',$approval->id)->update([
                    'status'=>'pending', 'level'=>2, 'required_levels'=>2,
                    'decided_by'=>null, 'decided_at'=>null, 'decision_note'=>null, 'updated_at'=>now(),
                ]);
                $budget = DB::table('budgets')->where('id',$approval->approvable_id)->first();
                if ($budget) DB::table('budgets')->where('period_start',$budget->period_start)->update(['status'=>'submitted']);
            } else {
                DB::table('approvals')->where('id',$approval->id)->update(['level'=>1, 'required_levels'=>2]);
            }
        }
    }

    public function down(): void { Schema::dropIfExists('approval_steps'); }
};
