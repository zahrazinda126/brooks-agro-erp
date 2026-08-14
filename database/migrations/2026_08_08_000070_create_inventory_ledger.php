<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 160);
            $table->string('category', 60)->index();
            $table->string('unit', 40);
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->string('default_location', 100)->default('Main Store');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->date('movement_date')->index();
            $table->string('movement_type', 30)->index();
            $table->string('location', 100)->index();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 16, 2)->nullable();
            $table->string('supplier_or_recipient', 160)->nullable();
            $table->string('reference', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'location', 'movement_date'], 'inventory_balance_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
