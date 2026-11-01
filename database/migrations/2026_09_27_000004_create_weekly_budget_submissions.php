<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Each department submits its weekly budget draft to Finance. The draft stays editable
 | until Finance submits the combined budget; changes after submitting show as
 | "changed since submitted" so the department can submit again.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('weekly_budget_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedInteger('line_count');
            $table->decimal('total', 16, 2);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['department_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_budget_submissions');
    }
};
