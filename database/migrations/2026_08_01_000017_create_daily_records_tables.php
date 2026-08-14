<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Daily records — what happened in the houses each day.
 |
 | The central design decision, and it comes from how the farm actually
 | works: a worker records WHAT happened; a manager records WHY.
 |
 | A casual worker counting dead birds knows there are four of them. They
 | do not know whether it was heat stress, coccidiosis or a predator, and
 | asking them to choose from a list of diseases produces guesses that
 | then look like data. So the worker's job ends at the count, and the
 | cause is a separate field filled in by someone qualified to judge it.
 |
 | Every table here therefore has two stages:
 |
 |   Stage 1 — recorded_by      the worker, on a phone, in under a minute
 |   Stage 2 — assessed_by      the manager, adding cause and judgement
 |
 | Both are timestamped and attributed, so nobody's assessment can be
 | mistaken for the worker's observation.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------
         | Why birds die. A short, plain list — not a veterinary index.
         | Managers pick from this; workers never see it.
         | ------------------------------------------------------------ */
        Schema::create('mortality_causes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);

            // disease | environment | predator | injury | culling | unknown
            $table->string('category', 20);

            $table->text('description')->nullable();

            // Prompts the manager to involve the vet when it matters.
            $table->boolean('needs_vet')->default(false);

            // Spreads through a flock — worth an immediate warning.
            $table->boolean('is_contagious')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ------------------------------------------------------------
         | Egg collection
         | ------------------------------------------------------------ */
        Schema::create('egg_collections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            $table->date('collected_on');
            // Most farms collect more than once a day.
            $table->string('session', 20)->default('morning'); // morning|afternoon|evening

            $table->unsignedInteger('total_eggs')->default(0);

            /* Stage 1 — the worker */
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->text('worker_note')->nullable();

            /* Stage 2 — the supervisor */
            $table->string('status', 20)->default('submitted'); // submitted|approved|queried
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('query_note')->nullable();

            $table->timestamps();

            // One record per flock, per day, per session.
            $table->unique(['animal_group_id', 'collected_on', 'session'], 'egg_collection_unique');
            $table->index(['department_id', 'collected_on']);
            $table->index(['status', 'collected_on']);
        });

        /*
         | One row per grade. Stored as trays AND loose eggs because that
         | is how they are counted in the house — a worker counts full
         | trays and then the odd ones left over, never a single total.
         */
        Schema::create('egg_collection_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('egg_grade_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('trays')->default(0);
            $table->unsignedInteger('loose_eggs')->default(0);
            $table->unsignedInteger('total_eggs')->default(0);

            $table->timestamps();

            $table->unique(['egg_collection_id', 'egg_grade_id'], 'egg_line_unique');
        });

        /* ------------------------------------------------------------
         | Mortality — the clearest case of the two-stage model
         | ------------------------------------------------------------ */
        Schema::create('mortality_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            $table->date('occurred_on');

            /* Stage 1 — what the worker counted */
            $table->unsignedInteger('quantity_died')->default(0);
            $table->unsignedInteger('quantity_culled')->default(0);
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();

            // What they saw, in their own words. Optional, and deliberately
            // free text — "they were breathing badly" is more useful than a
            // wrong pick from a list of diseases.
            $table->text('worker_note')->nullable();

            /* Stage 2 — what the manager judged */
            $table->foreignId('mortality_cause_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->text('assessment_note')->nullable();
            $table->text('action_taken')->nullable();
            $table->boolean('vet_called')->default(false);

            // pending_assessment | assessed
            $table->string('status', 25)->default('pending_assessment');

            $table->timestamps();

            $table->index(['department_id', 'occurred_on']);
            $table->index(['status', 'occurred_on']);
            $table->index('animal_group_id');
        });

        /* ------------------------------------------------------------
         | Feed used
         | ------------------------------------------------------------ */
        Schema::create('feed_consumptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            $table->date('used_on');

            // Free text until the Inventory module supplies a real item list.
            $table->string('feed_type', 60)->default('layer_mash');
            $table->decimal('quantity_kg', 10, 2)->default(0);
            // Bags are how it is actually handled; kg is what it is measured in.
            $table->unsignedSmallInteger('bags')->default(0);
            $table->unsignedSmallInteger('kg_per_bag')->default(50);

            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->text('worker_note')->nullable();

            $table->string('status', 20)->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('query_note')->nullable();

            $table->timestamps();

            $table->index(['department_id', 'used_on']);
            $table->unique(['animal_group_id', 'used_on', 'feed_type'], 'feed_use_unique');
        });

        /* ------------------------------------------------------------
         | Egg prices, with history.
         |
         | Market prices move constantly. A single price column would
         | silently rewrite the value of every past sale each time it
         | changed. Instead each price has a period, so a sale in March is
         | always valued at March's price.
         | ------------------------------------------------------------ */
        Schema::create('egg_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_grade_id')->constrained()->cascadeOnDelete();

            $table->decimal('price_per_tray', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();   // null = still current

            $table->string('market', 60)->nullable();   // Kalerwe, farm gate...
            $table->text('note')->nullable();

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['egg_grade_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_prices');
        Schema::dropIfExists('feed_consumptions');
        Schema::dropIfExists('mortality_records');
        Schema::dropIfExists('egg_collection_lines');
        Schema::dropIfExists('egg_collections');
        Schema::dropIfExists('mortality_causes');
    }
};
