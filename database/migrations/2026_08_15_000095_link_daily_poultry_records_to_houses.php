<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::table('egg_collections',fn(Blueprint $t)=>$t->foreignId('poultry_house_id')->nullable()->after('animal_group_id')->constrained('poultry_houses')->nullOnDelete());
        Schema::table('mortality_records',fn(Blueprint $t)=>$t->foreignId('poultry_house_id')->nullable()->after('animal_group_id')->constrained('poultry_houses')->nullOnDelete());
    }
    public function down():void {
        Schema::table('egg_collections',fn(Blueprint $t)=>$t->dropConstrainedForeignId('poultry_house_id'));
        Schema::table('mortality_records',fn(Blueprint $t)=>$t->dropConstrainedForeignId('poultry_house_id'));
    }
};
