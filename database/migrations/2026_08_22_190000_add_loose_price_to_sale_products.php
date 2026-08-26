<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('sale_products',fn(Blueprint $t)=>$t->decimal('loose_unit_price',16,2)->nullable()->after('unit_price'));DB::table('sale_products')->where('code','EGG-DAMAGED')->update(['loose_unit_price'=>250,'updated_at'=>now()]);}
 public function down():void{Schema::table('sale_products',fn(Blueprint $t)=>$t->dropColumn('loose_unit_price'));}
};
