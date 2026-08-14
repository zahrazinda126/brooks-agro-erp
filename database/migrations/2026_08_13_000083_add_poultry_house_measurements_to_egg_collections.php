<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::table('egg_collections',function(Blueprint $t){$t->decimal('egg_mass_g',10,2)->nullable();$t->unsignedInteger('damaged_eggs')->default(0);$t->decimal('bird_weight_kg',10,2)->nullable();$t->decimal('feed_amount_kg',10,2)->nullable();});}public function down():void{Schema::table('egg_collections',fn(Blueprint $t)=>$t->dropColumn(['egg_mass_g','damaged_eggs','bird_weight_kg','feed_amount_kg']));}};
