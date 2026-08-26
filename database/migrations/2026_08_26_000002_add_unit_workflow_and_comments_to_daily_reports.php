<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration{
 public function up():void{
  Schema::table('daily_management_report_sections',function(Blueprint $t){$t->string('department_code',30)->nullable()->after('report_date');});
  DB::table('daily_management_report_sections')->whereNull('department_code')->update(['department_code'=>DB::raw('section_code'),'section_code'=>'overview']);
  Schema::table('daily_management_report_sections',function(Blueprint $t){$t->dropUnique('daily_management_report_sections_report_date_section_code_unique');$t->unique(['report_date','department_code','section_code'],'daily_report_date_department_unit_unique');});
  Schema::create('daily_management_report_comments',function(Blueprint $t){$t->id();$t->date('report_date')->index();$t->string('department_code',30)->index();$t->foreignId('unit_report_id')->nullable()->constrained('daily_management_report_sections')->cascadeOnDelete();$t->string('comment_type',20)->default('director');$t->text('body');$t->foreignId('author_id')->constrained('users')->restrictOnDelete();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('daily_management_report_comments');Schema::table('daily_management_report_sections',function(Blueprint $t){$t->dropUnique('daily_report_date_department_unit_unique');$t->dropColumn('department_code');$t->unique(['report_date','section_code']);});}
};
