<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Feed mixing: ingredients are taken from feed stock and mixed; the mixed feed is sent to one or more stores,
 * whose store manager records what arrived. Each poultry house gets its mixed feed from one store, and the
 * feed an attendant gives is taken out of that store.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_stores', function (Blueprint $table) {
            $table->boolean('holds_mixed_feed')->default(false);
        });
        Schema::table('poultry_houses', function (Blueprint $table) {
            $table->foreignId('feed_store_id')->nullable()->constrained('inventory_stores')->nullOnDelete();
        });

        Schema::create('feed_mixings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->string('unit_code', 20)->default('poultry');
            $table->date('mixed_on');
            $table->string('taken_by', 160);                 // whoever took the ingredients from the store
            $table->decimal('mixed_kg', 12, 2);              // mixed feed made
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('feed_mixing_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_mixing_id')->constrained()->cascadeOnDelete();
            $table->string('feed_name', 80);
            $table->decimal('quantity_kg', 12, 2);
            $table->timestamps();
        });
        Schema::create('feed_mixing_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_mixing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_store_id')->constrained()->restrictOnDelete();
            $table->decimal('sent_kg', 12, 2);
            $table->decimal('received_kg', 12, 2)->nullable();
            $table->string('status', 20)->default('sent');   // sent, received
            $table->text('receive_note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        // Mixed feed in each store: received (from mixing) and given (to a house).
        Schema::create('mixed_feed_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_store_id')->constrained()->restrictOnDelete();
            $table->date('movement_date');
            $table->string('movement_type', 20);             // received, given
            $table->decimal('quantity_kg', 12, 2);
            $table->foreignId('poultry_house_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feed_mixing_dispatch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_store_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mixed_feed_movements');
        Schema::dropIfExists('feed_mixing_dispatches');
        Schema::dropIfExists('feed_mixing_ingredients');
        Schema::dropIfExists('feed_mixings');
        Schema::table('poultry_houses', fn (Blueprint $table) => $table->dropConstrainedForeignId('feed_store_id'));
        Schema::table('inventory_stores', fn (Blueprint $table) => $table->dropColumn('holds_mixed_feed'));
    }
};
