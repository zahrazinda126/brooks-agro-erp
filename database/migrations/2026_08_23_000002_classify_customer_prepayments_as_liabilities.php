<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_transactions')
            ->where('description', 'like', 'Customer prepayment%')
            ->update([
                'transaction_category' => 'customer_prepayment',
                'account_group' => 'Current Liabilities',
                'entry_account' => 'Customer Prepayments',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('financial_transactions')
            ->where('transaction_category', 'customer_prepayment')
            ->where('description', 'like', 'Customer prepayment%')
            ->update([
                'transaction_category' => 'revenue',
                'account_group' => 'Other Income',
                'entry_account' => 'Other Sales',
                'updated_at' => now(),
            ]);
    }
};
