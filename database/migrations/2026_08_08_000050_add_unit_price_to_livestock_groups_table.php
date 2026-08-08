<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livestock_groups', function (Blueprint $table) {
            $table->decimal('unit_price', 16, 2)->nullable()->after('initial_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('livestock_groups', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
