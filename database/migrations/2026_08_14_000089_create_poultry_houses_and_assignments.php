<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('poultry_houses', function(Blueprint $t){$t->id();$t->string('house_number',60)->unique();$t->foreignId('animal_group_id')->nullable()->constrained()->nullOnDelete();$t->unsignedInteger('bird_quantity')->default(0);$t->boolean('is_active')->default(true);$t->text('notes')->nullable();$t->timestamps();});
        Schema::create('poultry_house_assignments', function(Blueprint $t){$t->id();$t->foreignId('poultry_house_id')->constrained()->cascadeOnDelete();$t->foreignId('employee_id')->constrained()->cascadeOnDelete();$t->date('assigned_from');$t->date('assigned_until')->nullable();$t->foreignId('assigned_by')->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['employee_id','assigned_until']);});
        $used=[];foreach(DB::table('animal_groups')->where('status','active')->get() as $batch){$base=trim($batch->house?:$batch->code);$house=$base;if(in_array(strtolower($house),$used,true))$house=$base.' - '.$batch->code;$used[]=strtolower($house);DB::table('poultry_houses')->insert(['house_number'=>$house,'animal_group_id'=>$batch->id,'bird_quantity'=>$batch->current_quantity,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
    }
    public function down(): void {Schema::dropIfExists('poultry_house_assignments');Schema::dropIfExists('poultry_houses');}
};
