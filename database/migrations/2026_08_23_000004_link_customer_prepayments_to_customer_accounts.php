<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_prepayments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained('sales_customers')->nullOnDelete();
            $table->index(['customer_id', 'received_date']);
        });

        DB::table('customer_prepayments')->orderBy('id')->each(function ($credit): void {
            if (strcasecmp(trim((string) $credit->customer_name), 'One-time customer') === 0) {
                return;
            }

            $customer = DB::table('sales_customers')
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $credit->customer_name))])
                ->first();

            if (! $customer) {
                $id = DB::table('sales_customers')->insertGetId([
                    'name' => trim((string) $credit->customer_name),
                    'credit_limit' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $id = $customer->id;
            }

            DB::table('customer_prepayments')->where('id', $credit->id)->update(['customer_id' => $id]);
        });
    }

    public function down(): void
    {
        Schema::table('customer_prepayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
