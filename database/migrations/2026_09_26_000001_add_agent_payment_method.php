<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Receivable forms already offered "Agent" but validation rejected it; register it as a payment method. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('finance_payment_methods')->where('code', 'agent')->exists()) return;
        DB::table('finance_payment_methods')->insert(['code' => 'agent', 'name' => 'Agent', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('finance_payment_methods')->where('code', 'agent')->delete();
    }
};
