<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NAMES = ['broken eggs', 'dirty eggs', 'soft shell eggs'];

    public function up(): void
    {
        DB::table('sale_products')->whereIn(DB::raw('lower(name)'), self::NAMES)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('sale_products')->whereIn(DB::raw('lower(name)'), self::NAMES)->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};
