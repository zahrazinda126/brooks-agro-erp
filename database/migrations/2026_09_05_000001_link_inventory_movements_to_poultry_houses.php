<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('poultry_house_id')->nullable()->after('inventory_store_id')->constrained('poultry_houses')->nullOnDelete();
            $table->index(['poultry_house_id', 'inventory_item_id', 'movement_date'], 'house_inventory_history');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('house_inventory_history');
            $table->dropConstrainedForeignId('poultry_house_id');
        });
    }
};
