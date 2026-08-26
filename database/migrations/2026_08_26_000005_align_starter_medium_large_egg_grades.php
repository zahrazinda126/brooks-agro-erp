<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('egg_grades')->where('code', 'small')->update(['name' => 'Starter eggs', 'is_active' => true, 'sort_order' => 10, 'updated_at' => now()]);
        DB::table('egg_grades')->where('code', 'medium')->update(['name' => 'Medium eggs', 'is_active' => true, 'sort_order' => 20, 'updated_at' => now()]);
        DB::table('egg_grades')->where('code', 'large')->update(['name' => 'Large eggs', 'is_active' => true, 'sort_order' => 30, 'updated_at' => now()]);

        $starterId = DB::table('egg_grades')->where('code', 'small')->value('id');
        if ($starterId) DB::table('sale_products')->where('egg_grade_id', $starterId)->update(['name' => 'Starter eggs', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('egg_grades')->where('code', 'small')->update(['name' => 'Small', 'updated_at' => now()]);
    }
};
