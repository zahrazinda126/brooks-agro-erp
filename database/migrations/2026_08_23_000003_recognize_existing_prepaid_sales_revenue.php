<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_payments') || ! Schema::hasTable('sales')) {
            return;
        }

        DB::table('sales_payments')
            ->join('sales', 'sales.id', '=', 'sales_payments.sale_id')
            ->where('sales_payments.payment_method', 'prepayment')
            ->select([
                'sales_payments.id', 'sales_payments.paid_on', 'sales_payments.amount',
                'sales_payments.receipt_number', 'sales_payments.recorded_by',
                'sales.customer_name', 'sales.invoice_number',
            ])
            ->orderBy('sales_payments.id')
            ->each(function ($payment): void {
                $alreadyRecorded = DB::table('financial_transactions')
                    ->where('type', 'revenue_recognized')
                    ->where('reference', $payment->receipt_number)
                    ->exists();

                if ($alreadyRecorded) {
                    return;
                }

                DB::table('financial_transactions')->insert([
                    'transaction_date' => $payment->paid_on,
                    'type' => 'revenue_recognized',
                    'transaction_category' => 'revenue',
                    'account_group' => 'Sales',
                    'entry_account' => 'Sales Revenue',
                    'account' => 'Customer Prepayments (Liability)',
                    'counterparty' => $payment->customer_name,
                    'amount' => $payment->amount,
                    'payment_method' => null,
                    'reference' => $payment->receipt_number,
                    'description' => 'Sales revenue recognized from customer prepayment for '.$payment->invoice_number,
                    'recorded_by' => $payment->recorded_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('financial_transactions')
            ->where('type', 'revenue_recognized')
            ->where('description', 'like', 'Sales revenue recognized from customer prepayment%')
            ->delete();
    }
};
