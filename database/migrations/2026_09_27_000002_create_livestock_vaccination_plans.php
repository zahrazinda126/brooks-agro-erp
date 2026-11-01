<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Vaccination schedule for goats, rabbits and dogs. Unlike poultry (whole batches of one
 | age), these animals are kept in mixed-age groups, so a schedule item is calendar based:
 | first due on a date, then repeated every N months after the last dose given.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('livestock_vaccination_plans', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 20)->index();
            $table->foreignId('livestock_group_id')->nullable()->constrained()->cascadeOnDelete(); // null = every group in the unit
            $table->string('vaccine', 180);
            $table->date('first_due_on');
            $table->unsignedSmallInteger('repeat_every_months')->nullable(); // null = given once
            $table->boolean('is_essential')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('livestock_health_records', function (Blueprint $table) {
            $table->foreignId('livestock_vaccination_plan_id')->nullable()->after('livestock_animal_id')
                ->constrained('livestock_vaccination_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('livestock_health_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('livestock_vaccination_plan_id');
        });
        Schema::dropIfExists('livestock_vaccination_plans');
    }
};
