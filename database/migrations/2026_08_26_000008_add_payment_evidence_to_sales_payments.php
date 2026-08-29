<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_payments', function (Blueprint $table) {
            $table->string('merchant_code', 100)->nullable()->after('reference');
            $table->string('receipt_path')->nullable()->after('merchant_code');
        });
    }

    public function down(): void
    {
        Schema::table('sales_payments', fn (Blueprint $table) => $table->dropColumn(['merchant_code', 'receipt_path']));
    }
};
