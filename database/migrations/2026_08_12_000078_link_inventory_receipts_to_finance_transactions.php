<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('financial_transaction_id')->nullable()->after('inventory_item_id')->constrained('financial_transactions')->nullOnDelete();
            $table->unique('financial_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropUnique(['financial_transaction_id']);
            $table->dropConstrainedForeignId('financial_transaction_id');
        });
    }
};
