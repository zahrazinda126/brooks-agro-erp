<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Two different times, and conflating them loses information.
 |
 |   collected_at  when the eggs came out of the house
 |   recorded_at   when someone typed it in
 |
 | These are not the same. A worker collects at half past six and enters it
 | at nine when they next have signal, or at the end of a shift. Storing
 | only one of them means either the production figure is wrong (if you
 | keep the typing time) or the audit trail is (if you keep the collection
 | time).
 |
 | recorded_at and recorded_by were already being captured automatically
 | from the signed-in account — they simply were not shown anywhere. That
 | is fixed on the review screen.
 |
 | What was genuinely missing is collected_at: the worker's own statement
 | of when they did the round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egg_collections', function (Blueprint $table) {
            $table->time('collected_at')->nullable()->after('collected_on');
        });

        Schema::table('mortality_records', function (Blueprint $table) {
            $table->time('found_at')->nullable()->after('occurred_on');
        });

        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->time('fed_at')->nullable()->after('used_on');
        });
    }

    public function down(): void
    {
        Schema::table('egg_collections', function (Blueprint $table) {
            $table->dropColumn('collected_at');
        });

        Schema::table('mortality_records', function (Blueprint $table) {
            $table->dropColumn('found_at');
        });

        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->dropColumn('fed_at');
        });
    }
};
