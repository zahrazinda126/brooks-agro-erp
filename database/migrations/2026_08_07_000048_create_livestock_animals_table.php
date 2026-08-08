<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestock_animals', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 20)->index();
            $table->string('animal_tag', 80);
            $table->string('name', 120)->nullable();
            $table->string('sex', 10);
            $table->string('breed', 120)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('acquired_on');
            $table->string('acquisition_type', 20);
            $table->string('dam_tag', 80)->nullable();
            $table->string('sire_tag', 80)->nullable();
            $table->decimal('acquisition_cost', 16, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['unit_code','animal_tag']);
        });
    }
    public function down(): void { Schema::dropIfExists('livestock_animals'); }
};
