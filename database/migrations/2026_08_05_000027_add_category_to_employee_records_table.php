<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
