<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poultry_houses', function (Blueprint $table) {
            $table->string('house_type', 20)->default('standard')->after('house_number');
        });
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->date('expected_brooder_exit_date')->nullable()->after('age_days_on_arrival');
        });
        Schema::create('poultry_house_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_house_id')->nullable()->constrained('poultry_houses')->nullOnDelete();
            $table->foreignId('to_house_id')->constrained('poultry_houses')->restrictOnDelete();
            $table->unsignedInteger('bird_quantity');
            $table->date('moved_on');
            $table->string('reason', 180)->nullable();
            $table->foreignId('moved_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poultry_house_movements');
        Schema::table('animal_groups', fn (Blueprint $table) => $table->dropColumn('expected_brooder_exit_date'));
        Schema::table('poultry_houses', fn (Blueprint $table) => $table->dropColumn('house_type'));
    }
};
