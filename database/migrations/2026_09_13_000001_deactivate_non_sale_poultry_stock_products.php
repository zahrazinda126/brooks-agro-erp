<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODES = [
        'BIRD-BROILER',
        'BIRD-COCKEREL',
        'BIRD-CHICK',
        'BIRD-LOCAL',
        'BIRD-PULLET',
        'BIRD-SPENT-LAYER',
    ];

    public function up(): void
    {
        DB::table('sale_products')->whereIn('code', self::CODES)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('sale_products')->whereIn('code', self::CODES)->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};
