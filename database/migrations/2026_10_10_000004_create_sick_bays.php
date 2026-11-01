<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sick bays: places where sick animals are kept apart and looked after (several per unit).
 * An admission is a group of sick animals taken in on one day; each check records how they are doing
 * and how many recovered (back to their house or group), died or were culled.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sick_bays', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 20); // poultry, goats, rabbits, dogs
            $table->string('name', 80);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['unit_code', 'name']);
        });

        Schema::create('sick_bay_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sick_bay_id')->constrained()->restrictOnDelete();
            $table->string('unit_code', 20);
            $table->foreignId('animal_group_id')->nullable()->constrained()->nullOnDelete();      // poultry batch
            $table->foreignId('poultry_house_id')->nullable()->constrained()->nullOnDelete();     // the house the birds came from
            $table->foreignId('livestock_group_id')->nullable()->constrained()->nullOnDelete();   // goats, rabbits, dogs
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('in_care');
            $table->unsignedInteger('recovered')->default(0);
            $table->unsignedInteger('died')->default(0);
            $table->unsignedInteger('culled')->default(0);
            $table->date('admitted_on');
            $table->json('signs')->nullable();
            $table->string('condition', 160)->nullable(); // suspected illness or injury
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('in_care'); // in_care, discharged
            $table->date('discharged_on')->nullable();
            $table->foreignId('admitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['unit_code', 'status']);
        });

        Schema::create('sick_bay_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sick_bay_admission_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedInteger('recovered')->default(0);
            $table->unsignedInteger('died')->default(0);
            $table->unsignedInteger('culled')->default(0);
            $table->unsignedInteger('still_sick');
            $table->string('progress', 20); // improving, same, worse
            $table->string('treatment', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_bay_checks');
        Schema::dropIfExists('sick_bay_admissions');
        Schema::dropIfExists('sick_bays');
    }
};
