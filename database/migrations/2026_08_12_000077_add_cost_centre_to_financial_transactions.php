<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', fn (Blueprint $table) => $table->string('cost_centre', 100)->nullable()->after('department_id'));
    }

    public function down(): void
    {
        Schema::table('financial_transactions', fn (Blueprint $table) => $table->dropColumn('cost_centre'));
    }
};
