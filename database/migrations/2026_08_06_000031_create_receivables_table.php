<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 160);
            $table->string('invoice_number', 80)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('open');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
