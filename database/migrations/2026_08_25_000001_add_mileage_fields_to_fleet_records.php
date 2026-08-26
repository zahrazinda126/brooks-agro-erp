<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('fleet_records',function(Blueprint $t){$t->decimal('starting_odometer_km',12,1)->nullable();$t->decimal('ending_odometer_km',12,1)->nullable();$t->decimal('fuel_cost_ugx',14,2)->nullable();$t->decimal('next_service_odometer_km',12,1)->nullable();}); }
 public function down():void { Schema::table('fleet_records',fn(Blueprint $t)=>$t->dropColumn(['starting_odometer_km','ending_odometer_km','fuel_cost_ugx','next_service_odometer_km'])); }
};
