<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Breeds, and what each one is expected to do.
 |
 | This is what lets the system know when a flock should start laying and
 | what size of egg to expect at a given age. Without it, "the eggs are
 | small" is a shrug; with it, "this flock is 34 weeks old and still
 | producing pullet eggs" is a flag worth acting on.
 |
 | Figures are defaults. Every farm's conditions differ, so they are
 | editable rather than hardcoded, and the actual dates recorded against
 | each batch are what reporting uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('species', 20)->default('poultry'); // poultry|goat|rabbit|dog
            $table->string('purpose', 20)->default('layer');

            /* --- Laying expectations (poultry) ---------------------- */
            // Point of lay: the first eggs appear. Peak: the flock is at
            // its highest daily rate.
            $table->unsignedSmallInteger('lay_start_week')->nullable();
            $table->unsignedSmallInteger('peak_week')->nullable();
            $table->unsignedSmallInteger('lay_end_week')->nullable();

            // Peak rate as a percentage: 92 means 92 eggs per 100 hens
            // per day at peak.
            $table->unsignedSmallInteger('peak_lay_percent')->nullable();

            /* --- Feed and growth ------------------------------------ */
            $table->unsignedSmallInteger('mature_weight_g')->nullable();
            $table->unsignedSmallInteger('daily_feed_g')->nullable();

            // Broilers only: when they are ready for slaughter.
            $table->unsignedSmallInteger('slaughter_week')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['species', 'purpose']);
        });

        /*
         | Egg grades, and the age at which each becomes normal.
         |
         | A hen's first eggs are small and the size climbs with age. So a
         | grade is not just a price band — it is a signal about the
         | flock. `typical_from_week` / `typical_to_week` record when each
         | size should dominate, which lets the system tell the difference
         | between a young flock laying small eggs (expected) and a mature
         | flock laying small eggs (a problem).
         */
        Schema::create('egg_grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();     // starter, small, medium...
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedSmallInteger('min_weight_g')->nullable();
            $table->unsignedSmallInteger('max_weight_g')->nullable();

            // Null on both = not age-related (cracked, dirty, broken can
            // happen at any age).
            $table->unsignedSmallInteger('typical_from_week')->nullable();
            $table->unsignedSmallInteger('typical_to_week')->nullable();

            // Saleable at full price, or a defect grade sold cheap /
            // consumed on the farm.
            $table->boolean('is_defect')->default(false);
            $table->decimal('default_price', 12, 2)->default(0);  // per tray
            $table->unsignedSmallInteger('eggs_per_tray')->default(30);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_grades');
        Schema::dropIfExists('breeds');
    }
};
