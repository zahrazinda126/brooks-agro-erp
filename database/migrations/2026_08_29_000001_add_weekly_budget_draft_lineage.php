<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('source_budget_id')->nullable()->after('recorded_by')->constrained('budgets')->nullOnDelete();
            $table->string('carryover_type', 30)->nullable()->after('source_budget_id');
        });

        Schema::create('weekly_budget_draft_initializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('source_week')->nullable();
            $table->foreignId('initialized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'week_start'], 'weekly_budget_draft_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_budget_draft_initializations');
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_budget_id');
            $table->dropColumn('carryover_type');
        });
    }
};
