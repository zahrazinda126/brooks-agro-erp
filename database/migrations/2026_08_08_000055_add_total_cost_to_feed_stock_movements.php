<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_stock_movements', function (Blueprint $table) {
            $table->decimal('total_cost', 16, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('feed_stock_movements', fn (Blueprint $table) => $table->dropColumn('total_cost'));
    }
};
