<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Flock health.
 |
 | Three tables, and one of them carries a responsibility the others do not.
 |
 |   medications        what can be given, and how long afterwards the
 |                      eggs must not be sold
 |   vaccination_plans  the standard programme — what is due at what age
 |   health_events      what was actually given, to which flock, by whom
 |
 | WITHDRAWAL IS THE POINT.
 |
 | After most antibiotics, eggs must not be sold for several days. Selling
 | them is illegal, unsafe, and the kind of thing that ends a farm's name
 | in the market. Every treatment therefore computes the date the eggs are
 | clean again, and that date is stored on the record rather than worked
 | out later — so changing a medication's withdrawal period next year can
 | never silently rewrite whether last month's eggs were safe.
 |
 | A system that records treatments without tracking withdrawal is worse
 | than no system, because it creates a false sense of control.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ------------------------------------------------------------
         | What can be given
         | ------------------------------------------------------------ */
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->string('brand', 160)->nullable();

            // vaccine | antibiotic | dewormer | coccidiostat | vitamin | other
            $table->string('type', 30);

            // water | feed | eye_drop | nasal | injection | spray | topical
            $table->string('route', 30)->nullable();

            $table->string('treats', 255)->nullable();   // what it is for
            $table->string('dose', 160)->nullable();     // as written on the pack

            /*
             | Days after the last dose before produce is safe to sell.
             |
             | These are TYPICAL figures. The real one is on the product
             | label and varies by brand and strength, which is why they
             | are editable and why the screen says so.
             */
            $table->unsignedSmallInteger('withdrawal_days_eggs')->default(0);
            $table->unsignedSmallInteger('withdrawal_days_meat')->default(0);

            // Some drugs must never be given to laying birds at all.
            $table->boolean('banned_in_lay')->default(false);

            $table->decimal('cost_per_unit', 12, 2)->nullable();
            $table->string('unit', 30)->nullable();      // dose, ml, sachet, vial

            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'sort_order']);
        });

        /* ------------------------------------------------------------
         | The standard programme
         | ------------------------------------------------------------ */
        Schema::create('vaccination_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();

            // Null breed = applies to every flock.
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20)->default('layer');

            // Age in DAYS, because the early programme is day-precise:
            // Gumboro at 14 days is not the same as at 21.
            $table->unsignedSmallInteger('age_days');

            // How many days either side still counts as on time.
            $table->unsignedSmallInteger('window_days')->default(3);

            /*
             | Repeats. Newcastle is given every 8–12 weeks through lay,
             | and deworming every couple of months.
             */
            $table->unsignedSmallInteger('repeat_every_days')->nullable();
            $table->unsignedSmallInteger('repeat_until_days')->nullable();

            $table->boolean('is_essential')->default(true);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['purpose', 'age_days']);
        });

        /* ------------------------------------------------------------
         | What actually happened
         | ------------------------------------------------------------ */
        Schema::create('health_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            // vaccination | treatment | deworming | inspection | vet_visit
            $table->string('event_type', 30);

            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurred_on');

            $table->unsignedInteger('birds_treated')->default(0);
            $table->string('dose_given', 160)->nullable();
            $table->unsignedSmallInteger('days_of_course')->default(1);

            /*
             | Worked out when the record is saved and stored, not derived
             | on demand. Changing a medication's withdrawal period next
             | year must never silently rewrite whether last month's eggs
             | were safe to sell.
             */
            $table->date('withdrawal_ends_eggs')->nullable();
            $table->date('withdrawal_ends_meat')->nullable();

            $table->string('reason', 255)->nullable();
            $table->text('findings')->nullable();

            $table->decimal('cost', 12, 2)->default(0);

            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administered_name', 160)->nullable();  // an outside vet
            $table->boolean('vet_involved')->default(false);

            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();

            $table->timestamps();

            $table->index(['animal_group_id', 'occurred_on']);
            $table->index(['department_id', 'occurred_on']);
            $table->index('withdrawal_ends_eggs');
        });

        /*
         | Marked on the collection itself, not looked up later. An egg
         | collected during withdrawal was collected during withdrawal,
         | whatever anyone edits afterwards.
         */
        Schema::table('egg_collections', function (Blueprint $table) {
            $table->boolean('under_withdrawal')->default(false)->after('total_eggs');
            $table->date('safe_to_sell_from')->nullable()->after('under_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('egg_collections', function (Blueprint $table) {
            $table->dropColumn(['under_withdrawal', 'safe_to_sell_from']);
        });

        Schema::dropIfExists('health_events');
        Schema::dropIfExists('vaccination_plans');
        Schema::dropIfExists('medications');
    }
};
