<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->date('probation_ends_on')->nullable()->after('ends_on');
            $table->unsignedSmallInteger('notice_period_days')->nullable()->after('probation_ends_on');
            $table->date('signed_on')->nullable()->after('notice_period_days');
        });
    }

    public function down(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->dropColumn(['probation_ends_on', 'notice_period_days', 'signed_on']);
        });
    }
};
