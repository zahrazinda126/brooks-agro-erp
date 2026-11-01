<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_accommodation_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('staff_accommodation_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
