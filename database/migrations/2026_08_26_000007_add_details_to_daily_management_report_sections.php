<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_management_report_sections', function (Blueprint $table) {
            $table->json('details')->nullable()->after('recommendations');
        });
    }

    public function down(): void
    {
        Schema::table('daily_management_report_sections', fn (Blueprint $table) => $table->dropColumn('details'));
    }
};
