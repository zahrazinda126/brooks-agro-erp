<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->string('title', 160)->nullable()->after('category');
            $table->string('provider', 160)->nullable()->after('title');
            $table->decimal('score', 5, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('employee_records', function (Blueprint $table) {
            $table->dropColumn(['title', 'provider', 'score']);
        });
    }
};
