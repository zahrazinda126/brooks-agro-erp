<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_payments', fn (Blueprint $table) => $table->string('receipt_number', 50)->nullable()->after('sale_id'));
        DB::table('sales_payments')->orderBy('id')->each(fn ($payment) => DB::table('sales_payments')->where('id', $payment->id)->update(['receipt_number' => sprintf('RCT-%06d', $payment->id)]));
        Schema::table('sales_payments', fn (Blueprint $table) => $table->unique('receipt_number'));
    }

    public function down(): void
    {
        Schema::table('sales_payments', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
