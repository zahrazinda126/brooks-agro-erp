<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person store assignments. When a person has any rows here, these decide which
 * stores they handle (e.g. one store manager for Feed and Veterinary, another for
 * Kitchen and Staff Welfare); otherwise their role's store access applies as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_store_id')->constrained('inventory_stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_receive')->default(false);
            $table->boolean('can_issue')->default(false);
            $table->boolean('can_adjust')->default(false);
            $table->boolean('can_view_value')->default(false);
            $table->timestamps();
            $table->unique(['inventory_store_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_store_user');
    }
};
