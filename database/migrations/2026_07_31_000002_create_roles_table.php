<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Roles — job function. Mirrors ROLE_PERMISSIONS in js/roles.js.
 |
 | is_superuser replaces the "*" wildcard the prototype used for
 | Director and CEO, so we never have to special-case names in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();   // 'director', 'hr-manager'
            $table->string('name', 100);            // 'Director', 'HR Manager'
            $table->text('description')->nullable();
            $table->boolean('is_superuser')->default(false);
            $table->unsignedSmallInteger('rank')->default(100); // lower = more senior
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
