<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Which modules a DEPARTMENT may open. Axis 2 of the permission model.
 | A user's access is the UNION of their role grants and their
 | department grants, exactly as canAccess() worked in the prototype.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();

            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_approve')->default(false);

            $table->timestamps();
            $table->unique(['department_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_module');
    }
};
