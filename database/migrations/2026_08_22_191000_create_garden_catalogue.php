<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('garden_locations',function(Blueprint $t){$t->id();$t->string('code',40)->unique();$t->string('name',120)->unique();$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('garden_crops',function(Blueprint $t){$t->id();$t->string('code',60)->unique();$t->string('name',120)->unique();$t->string('default_unit',30)->default('kg');$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::table('farm_unit_records',function(Blueprint $t){$t->foreignId('garden_location_id')->nullable()->constrained()->nullOnDelete();$t->foreignId('garden_crop_id')->nullable()->constrained()->nullOnDelete();});
  foreach([['kituntu','Kituntu'],['mpaffe','Mpaffe'],['ssemukombe','Ssemukombe'],['farm-garden','Farm Garden']] as [$code,$name])DB::table('garden_locations')->insert(compact('code','name')+['created_at'=>now(),'updated_at'=>now()]);
  $crops=[['tomatoes','Tomatoes','kg'],['passion-fruit','Passion fruit','kg'],['matooke','Matooke','bunch'],['eggplant','Eggplant','kg'],['bitter-tomatoes','Bitter tomatoes','kg'],['cabbage','Cabbage','head'],['greens','Greens','bundle'],['onions','Onions','kg'],['pineapples','Pineapples','piece'],['watermelons','Watermelons','piece'],['guavas','Guavas','kg'],['kitafeeri','Kitafeeri','kg'],['sweet-potatoes','Sweet potatoes','kg'],['irish-potatoes','Irish potatoes','kg'],['beans','Beans','kg'],['pumpkin','Pumpkin','piece'],['plantain','Plantain','bunch'],['kivuvu','Kivuvu','bunch'],['cassava','Cassava','kg']];
  foreach($crops as [$code,$name,$unit]){DB::table('garden_crops')->insert(['code'=>$code,'name'=>$name,'default_unit'=>$unit,'created_at'=>now(),'updated_at'=>now()]);$existing=DB::table('sale_products')->whereRaw('lower(name)=?',[strtolower($name)])->first();$values=['name'=>$name,'category'=>'garden','unit'=>$unit,'is_active'=>true,'notes'=>'Company garden crop; selling price to be set by Finance or CEO.','updated_at'=>now()];if($existing)DB::table('sale_products')->where('id',$existing->id)->update($values);else DB::table('sale_products')->insert($values+['code'=>'GARDEN-'.strtoupper(str_replace('-','_',$code)),'unit_price'=>0,'created_at'=>now()]);}
 }
 public function down():void{Schema::table('farm_unit_records',function(Blueprint $t){$t->dropConstrainedForeignId('garden_location_id');$t->dropConstrainedForeignId('garden_crop_id');});Schema::dropIfExists('garden_crops');Schema::dropIfExists('garden_locations');}
};
