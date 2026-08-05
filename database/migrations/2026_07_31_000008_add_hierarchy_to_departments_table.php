<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Adds the DIVISION level from the org chart.
 |
 | Administration / Production / Finance / HR are divisions; Poultry,
 | Goats, Veterinary etc. are departments inside them. Self-referencing
 | parent_id keeps one table instead of two, and lets reports roll up
 | from department -> division without a second join path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                  ->constrained('departments')->nullOnDelete();

            // 'division' | 'department'
            $table->string('level', 20)->default('department')->after('name');

            $table->foreignId('head_employee_id')->nullable()->after('level')
                  ->constrained('employees')->nullOnDelete();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('head_employee_id');
            $table->dropColumn('level');
        });
    }
};
