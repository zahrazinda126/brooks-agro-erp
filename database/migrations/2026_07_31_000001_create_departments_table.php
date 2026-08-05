<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Departments — the organisational unit a person belongs to.
 | Mirrors DEPARTMENT_MODULES in the prototype's js/roles.js.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();   // 'poultry', 'finance', 'all'
            $table->string('name', 100);            // 'Poultry', 'Finance', 'All'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
