<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('daily_sales_balances', 'receipt_path')) {
            Schema::table('daily_sales_balances', function (Blueprint $table) {
                $table->string('receipt_path')->nullable()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('daily_sales_balances', 'receipt_path')) {
            Schema::table('daily_sales_balances', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }
    }
};
