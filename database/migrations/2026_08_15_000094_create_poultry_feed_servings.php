<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poultry_feed_servings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poultry_house_id')->constrained('poultry_houses')->cascadeOnDelete();
            $table->date('fed_on')->index();
            $table->time('fed_at');
            $table->decimal('quantity_kg', 10, 2);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['poultry_house_id', 'fed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poultry_feed_servings');
    }
};
