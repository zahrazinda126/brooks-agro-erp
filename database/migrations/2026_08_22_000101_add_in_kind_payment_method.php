<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('finance_payment_methods')->updateOrInsert(
            ['code' => 'in_kind'],
            ['name' => 'In kind / barter', 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('finance_payment_methods')->where('code', 'in_kind')->delete();
    }
};
