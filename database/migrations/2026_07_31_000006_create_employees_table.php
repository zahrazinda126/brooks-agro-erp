<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Employees — the HR record for a real person on the farm.
 |
 | Deliberately separate from `users` (the login). Casual workers can
 | exist here with no account at all, and when someone leaves you
 | disable their login while the employment history stays intact for
 | payroll and reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('staff_no', 30)->unique();      // BAF-0001
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('other_names', 80)->nullable();

            // Contact & identity
            $table->string('phone', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('national_id', 40)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('next_of_kin_name', 120)->nullable();
            $table->string('next_of_kin_phone', 30)->nullable();

            // Employment
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_title', 120)->nullable();
            $table->string('employment_type', 20)->default('permanent'); // permanent|contract|casual
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');   // active|suspended|terminated

            // Pay — numeric, never float, so money maths is exact
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('pay_frequency', 20)->default('monthly'); // monthly|weekly|daily
            $table->string('payment_method', 30)->nullable();        // cash|bank|mobile_money
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->string('mobile_money_number', 30)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
