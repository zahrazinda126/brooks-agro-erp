<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Modules — every screen in the system, stored as data rather than
 | hardcoded. Mirrors js/modules.js. Adding a module later is an
 | INSERT, not a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();     // 'poultry', 'egg-entry'
            $table->string('name', 120);             // 'Poultry'
            $table->string('group', 60);             // 'Livestock', 'Finance'
            $table->string('route', 160)->nullable();// laravel route name
            $table->string('icon', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_worker_module')->default(false); // casual-worker screens
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
