<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* Each unit has its own bank account, which pays that unit's expenses (Garden already has one). */
return new class extends Migration {
    private const ACCOUNTS = ['1021008' => 'Centenary Bank - Poultry', '1021009' => 'Centenary Bank - Goats', '1021010' => 'Centenary Bank - Rabbits', '1021011' => 'Centenary Bank - Dogs'];

    public function up(): void
    {
        $parent = DB::table('finance_accounts')->where('code', '1021000')->value('id');
        $sort = (int) DB::table('finance_accounts')->max('sort_order');
        foreach (self::ACCOUNTS as $code => $name) {
            if (! DB::table('finance_payment_accounts')->where('name', $name)->exists()) {
                DB::table('finance_payment_accounts')->insert(['name' => $name, 'kind' => 'bank', 'currency' => 'UGX', 'institution' => 'Centenary Bank', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($parent && ! DB::table('finance_accounts')->where('code', $code)->exists()) {
                DB::table('finance_accounts')->insert(['code' => $code, 'name' => 'Centenary Bank UGX Account ('.str_replace('Centenary Bank - ', '', $name).')', 'type' => 'asset', 'parent_id' => $parent, 'is_active' => true, 'statement' => 'balance_sheet', 'is_group' => false, 'sort_order' => ++$sort, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('finance_payment_accounts')->whereIn('name', array_values(self::ACCOUNTS))->delete();
        DB::table('finance_accounts')->whereIn('code', array_keys(self::ACCOUNTS))->delete();
    }
};
