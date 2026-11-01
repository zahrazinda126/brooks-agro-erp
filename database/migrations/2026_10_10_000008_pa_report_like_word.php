<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The PA's report follows his Word report: the Chairman's engagements carry the person or group, the reason
 * (invitation, appointment…) and a contact; the report keeps the projects / farm activities table he fills in.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('executive_meetings', function (Blueprint $table) {
            $table->string('host', 180)->nullable();     // group / person
            $table->string('reason', 60)->nullable();    // invitation, appointment, visit…
            $table->string('contact', 60)->nullable();
        });
        Schema::table('pa_daily_reports', function (Blueprint $table) {
            $table->json('activities')->nullable();      // [{project, activity, status}]
        });
    }

    public function down(): void
    {
        Schema::table('executive_meetings', fn (Blueprint $table) => $table->dropColumn(['host', 'reason', 'contact']));
        Schema::table('pa_daily_reports', fn (Blueprint $table) => $table->dropColumn('activities'));
    }
};
