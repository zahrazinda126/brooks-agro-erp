<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Approvals — the "Approvals" box in your org chart, built once.
 |
 | Polymorphic on purpose: an approval row can point at an egg
 | collection entry, a purchase order, a leave request or a payroll
 | run. Without this you end up with approved_by / approved_at columns
 | copied into thirty tables and no single "what's waiting on me" query.
 |
 | The workflow is deliberately simple to start: submitted -> approved
 | or rejected, with an optional second level for high-value items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->string('approvable_type', 120);
            $table->unsignedBigInteger('approvable_id');

            $table->string('module_key', 60)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            // Who raised it
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->text('submission_note')->nullable();

            // Who acted on it
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedSmallInteger('required_levels')->default(1);

            // Lets the dashboard flag overdue approvals.
            $table->timestamp('due_at')->nullable();

            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['status', 'department_id']);
            $table->index(['decided_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
