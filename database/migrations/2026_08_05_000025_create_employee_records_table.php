<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->date('recorded_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->decimal('amount', 14, 2)->nullable();
            $table->boolean('is_payroll_deduction')->default(false);
            $table->string('payroll_deduction_status', 20)->nullable();
            $table->text('details')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'recorded_on']);
            $table->index(['employee_id', 'type']);
            $table->index(['is_payroll_deduction', 'payroll_deduction_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_records');
    }
};
