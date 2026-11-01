<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Construction, refined:
 |   - each person on a project has a role: lead (constructor), builder or helper. The farm pays the
 |     lead the agreed sum; the team is paid by the lead (pay_basis "by_lead").
 |   - a supplier bill (materials bought on credit) can belong to a project, so the project shows what
 |     is still owed as well as what is paid.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('construction_project_workers', function (Blueprint $table) {
            $table->string('role', 20)->default('builder')->after('employee_id'); // lead, builder, helper
        });
        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('construction_project_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payables', fn (Blueprint $table) => $table->dropConstrainedForeignId('construction_project_id'));
        Schema::table('construction_project_workers', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
