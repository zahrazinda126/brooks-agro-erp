<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('daily_management_report_sections',function(Blueprint $t){$t->id();$t->date('report_date')->index();$t->string('section_code',30);$t->string('status',30)->default('draft')->index();$t->text('summary')->nullable();$t->text('activities')->nullable();$t->text('challenges')->nullable();$t->text('recommendations')->nullable();$t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();$t->timestamp('shared_at')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();$t->text('review_note')->nullable();$t->timestamps();$t->unique(['report_date','section_code']);});}
 public function down():void{Schema::dropIfExists('daily_management_report_sections');}
};
