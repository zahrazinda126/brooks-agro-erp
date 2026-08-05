<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Feed types become data the farm maintains, not a list in a template.
 |
 | Two things the first version got wrong:
 |
 |   1. Five feed types were written into the form. Every farm mixes its
 |      own — maize bran, silverfish, cotton seed cake, shells, a premix —
 |      and the list changes with what the supplier has.
 |
 |   2. It assumed one feed per flock per day. Mixing is normal: layer mash
 |      plus a bran top-up plus shells for shell quality. Recording only
 |      one of them makes the feed conversion figure wrong, which is the
 |      number the whole module exists to produce.
 |
 | So: a feed_types table, and one row per feed used per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);

            // complete | energy | protein | mineral | supplement
            $table->string('category', 20)->default('complete');

            /*
             | Which birds it suits, so the entry screen can offer the
             | likely ones first. Null means any age.
             */
            $table->unsignedSmallInteger('from_week')->nullable();
            $table->unsignedSmallInteger('to_week')->nullable();

            // The usual sack size, so the counter starts on the right one.
            $table->unsignedSmallInteger('default_kg_per_bag')->default(50);

            $table->decimal('cost_per_kg', 10, 2)->nullable();
            $table->text('note')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'sort_order']);
        });

        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->foreignId('feed_type_id')->nullable()->after('department_id')
                  ->constrained()->nullOnDelete();
        });

        /*
         | The old constraint was on the free-text feed_type column. Replace
         | it with one on the reference, so a flock can have several feeds
         | on one day but not the same feed twice.
         */
        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->dropUnique('feed_use_unique');
        });

        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->unique(['animal_group_id', 'used_on', 'feed_type_id'], 'feed_use_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->dropUnique('feed_use_unique');
            $table->dropConstrainedForeignId('feed_type_id');
            $table->unique(['animal_group_id', 'used_on', 'feed_type'], 'feed_use_unique');
        });

        Schema::dropIfExists('feed_types');
    }
};
