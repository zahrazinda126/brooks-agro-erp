<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('payroll_runs',function(Blueprint $t){$t->string('funding_account',160)->nullable();$t->string('funding_reference',100)->nullable();$t->foreignId('funded_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('funded_at')->nullable();});
  Schema::table('payroll_lines',function(Blueprint $t){$t->decimal('wallet_credited',16,2)->default(0);$t->decimal('wallet_withdrawn',16,2)->default(0);});
  Schema::create('staff_salary_wallet_transactions',function(Blueprint $t){$t->id();$t->foreignId('employee_id')->constrained()->restrictOnDelete();$t->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('payroll_line_id')->nullable()->constrained()->nullOnDelete();$t->string('type',20);$t->decimal('amount',16,2);$t->date('transaction_date');$t->string('company_account',160)->nullable();$t->string('payment_method',30)->nullable();$t->string('reference',100)->nullable();$t->text('notes')->nullable();$t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['payroll_line_id','type']);});
 }
 public function down():void{Schema::dropIfExists('staff_salary_wallet_transactions');Schema::table('payroll_lines',fn(Blueprint $t)=>$t->dropColumn(['wallet_credited','wallet_withdrawn']));Schema::table('payroll_runs',function(Blueprint $t){$t->dropConstrainedForeignId('funded_by');$t->dropColumn(['funding_account','funding_reference','funded_at']);});}
};
