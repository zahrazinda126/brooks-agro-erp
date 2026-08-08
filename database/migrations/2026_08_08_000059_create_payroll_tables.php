<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id(); $t->string('payroll_number',50)->unique(); $t->string('frequency',20);
            $t->date('period_start'); $t->date('period_end'); $t->date('pay_date')->nullable();
            $t->string('status',25)->default('draft'); $t->decimal('gross_total',18,2)->default(0);
            $t->decimal('deduction_total',18,2)->default(0); $t->decimal('net_total',18,2)->default(0);
            $t->decimal('employer_contribution_total',18,2)->default(0); $t->text('notes')->nullable();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('paid_at')->nullable();
            $t->timestamps(); $t->unique(['frequency','period_start','period_end']);
        });
        Schema::create('payroll_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete(); $t->decimal('basic_pay',16,2);
            $t->decimal('allowances',16,2)->default(0); $t->decimal('overtime_pay',16,2)->default(0);
            $t->decimal('gross_pay',16,2); $t->decimal('nssf_employee',16,2)->default(0);
            $t->decimal('paye',16,2)->default(0); $t->decimal('salary_advance',16,2)->default(0);
            $t->decimal('penalties',16,2)->default(0); $t->decimal('other_deductions',16,2)->default(0);
            $t->decimal('total_deductions',16,2); $t->decimal('net_pay',16,2);
            $t->decimal('nssf_employer',16,2)->default(0); $t->string('payment_method',30)->nullable();
            $t->string('payment_account',120)->nullable(); $t->text('notes')->nullable(); $t->timestamps();
            $t->unique(['payroll_run_id','employee_id']);
        });
        Schema::table('employee_records', function (Blueprint $t) {
            $t->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
        });
        $moduleId=DB::table('modules')->where('key','payroll')->value('id');
        foreach(DB::table('roles')->whereIn('code',['hr-manager','finance-manager','ceo','director'])->get() as $role){
            if($moduleId)DB::table('module_role')->updateOrInsert(['role_id'=>$role->id,'module_id'=>$moduleId],[
                'can_view'=>true,'can_create'=>$role->code==='hr-manager','can_edit'=>in_array($role->code,['hr-manager','finance-manager']),
                'can_delete'=>false,'can_approve'=>in_array($role->code,['finance-manager','ceo','director']),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
    public function down():void{Schema::table('employee_records',fn(Blueprint $t)=>$t->dropConstrainedForeignId('payroll_run_id'));Schema::dropIfExists('payroll_lines');Schema::dropIfExists('payroll_runs');}
};
