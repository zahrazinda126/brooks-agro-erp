<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('financial_transactions', function (Blueprint $t) {
    $t->id(); $t->date('transaction_date'); $t->string('type',30); $t->string('account',120);
    $t->string('counterparty',160)->nullable(); $t->decimal('amount',14,2); $t->string('payment_method',30);
    $t->string('reference',80)->nullable(); $t->text('description'); $t->foreignId('recorded_by')->constrained('users');
    $t->timestamps(); $t->index(['transaction_date','type']); $t->index(['account','transaction_date']);
}); } public function down(): void { Schema::dropIfExists('financial_transactions'); } };
