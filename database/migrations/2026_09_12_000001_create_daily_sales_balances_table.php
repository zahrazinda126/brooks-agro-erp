<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_sales_balances', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->foreignId('sales_person_id')->constrained('users')->restrictOnDelete();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('cash_received', 16, 2)->default(0);
            $table->decimal('amount_transferred', 16, 2);
            $table->string('destination', 30);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['business_date', 'sales_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_balances');
    }
};
