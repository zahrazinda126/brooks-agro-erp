<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('daily_department_report_closures',function(Blueprint $t){$t->id();$t->date('report_date');$t->string('department_code',40);$t->foreignId('closed_by')->constrained('users')->restrictOnDelete();$t->timestamp('closed_at');$t->timestamps();$t->unique(['report_date','department_code'],'daily_department_closed_once');});
  $groups=\Illuminate\Support\Facades\DB::table('daily_management_report_sections')->where('status','ceo_reviewed')->select('report_date','department_code')->selectRaw('min(prepared_by) as owner')->groupBy('report_date','department_code')->get();
  foreach($groups as $group){$incomplete=\Illuminate\Support\Facades\DB::table('daily_management_report_sections')->whereDate('report_date',$group->report_date)->where('department_code',$group->department_code)->where('status','!=','ceo_reviewed')->exists();if(!$incomplete)\Illuminate\Support\Facades\DB::table('daily_department_report_closures')->insert(['report_date'=>$group->report_date,'department_code'=>$group->department_code,'closed_by'=>$group->owner,'closed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}
 }
 public function down():void{Schema::dropIfExists('daily_department_report_closures');}
};
