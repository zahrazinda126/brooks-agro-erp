<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Customers pay through the farm's MTN and Airtel merchant codes, so "Mobile money" becomes two payment methods:
 * MTN Merchant and Airtel Merchant. Earlier mobile-money records are relabelled by the account the money went into.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['mtn_merchant' => 'MTN Merchant', 'airtel_merchant' => 'Airtel Merchant'] as $code => $name) {
            DB::table('finance_payment_methods')->updateOrInsert(['code' => $code], ['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('finance_payment_methods')->where('code', 'mobile_money')->update(['is_active' => false, 'updated_at' => now()]);

        foreach ([['sales', 'farm_account'], ['sales_payments', 'farm_account'], ['financial_transactions', 'account']] as [$table, $column]) {
            DB::table($table)->where('payment_method', 'mobile_money')->where($column, 'like', 'MTN%')->update(['payment_method' => 'mtn_merchant']);
            DB::table($table)->where('payment_method', 'mobile_money')->where($column, 'like', 'Airtel%')->update(['payment_method' => 'airtel_merchant']);
        }
    }

    public function down(): void
    {
        foreach ([['sales'], ['sales_payments'], ['financial_transactions']] as [$table]) {
            DB::table($table)->whereIn('payment_method', ['mtn_merchant', 'airtel_merchant'])->update(['payment_method' => 'mobile_money']);
        }
        DB::table('finance_payment_methods')->where('code', 'mobile_money')->update(['is_active' => true]);
        DB::table('finance_payment_methods')->whereIn('code', ['mtn_merchant', 'airtel_merchant'])->delete();
    }
};
