<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id(); $table->string('supplier_name', 160); $table->string('invoice_number', 80)->nullable();
            $table->date('invoice_date'); $table->date('due_date')->nullable(); $table->text('description');
            $table->decimal('amount', 14, 2); $table->decimal('amount_paid', 14, 2)->default(0); $table->string('status', 20)->default('open');
            $table->foreignId('recorded_by')->constrained('users'); $table->timestamps(); $table->index(['status', 'due_date']);
        });
        Schema::create('budgets', function (Blueprint $table) {
            $table->id(); $table->string('category', 120); $table->date('period_start'); $table->date('period_end');
            $table->decimal('planned_amount', 14, 2); $table->text('notes')->nullable(); $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps(); $table->index(['period_start', 'period_end']);
        });
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id(); $table->string('name', 160); $table->string('asset_tag', 80)->nullable(); $table->string('category', 100);
            $table->date('purchase_date'); $table->decimal('purchase_cost', 14, 2); $table->integer('useful_life_years')->nullable();
            $table->string('location', 160)->nullable(); $table->text('notes')->nullable(); $table->string('status', 20)->default('active');
            $table->foreignId('recorded_by')->constrained('users'); $table->timestamps(); $table->index(['status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets'); Schema::dropIfExists('budgets'); Schema::dropIfExists('payables');
    }
};
