<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cheques: a payment method of their own (from a bank account only) with the cheque number, so the Finance daily
 * report's cheque section lists real cheques rather than every bank payment.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->string('cheque_number', 40)->nullable();
        });
        DB::table('finance_payment_methods')->updateOrInsert(['code' => 'cheque'], ['name' => 'Cheque', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('finance_payment_methods')->where('code', 'cheque')->delete();
        Schema::table('financial_transactions', fn (Blueprint $table) => $table->dropColumn('cheque_number'));
    }
};
