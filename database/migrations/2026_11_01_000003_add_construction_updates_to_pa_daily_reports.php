<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pa_daily_reports', function (Blueprint $table) {
            $table->json('construction_updates')->nullable()->after('activities');
        });
    }

    public function down(): void
    {
        Schema::table('pa_daily_reports', function (Blueprint $table) {
            $table->dropColumn('construction_updates');
        });
    }
};
