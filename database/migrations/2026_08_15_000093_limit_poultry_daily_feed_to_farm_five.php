<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('feed_types')->update(['is_active' => false, 'updated_at' => now()]);

        $feeds = [
            ['code' => 'bran', 'name' => 'Bran', 'category' => 'energy', 'sort_order' => 10],
            ['code' => 'broken-maize', 'name' => 'Broken Maize', 'category' => 'energy', 'sort_order' => 20],
            ['code' => 'sunflower', 'name' => 'Sunflower', 'category' => 'protein', 'sort_order' => 30],
            ['code' => 'soya', 'name' => 'Soya', 'category' => 'protein', 'sort_order' => 40],
            ['code' => 'lime', 'name' => 'Lime', 'category' => 'mineral', 'sort_order' => 50],
        ];

        foreach ($feeds as $feed) {
            DB::table('feed_types')->updateOrInsert(
                ['code' => $feed['code']],
                $feed + [
                    'from_week' => null,
                    'to_week' => null,
                    'default_kg_per_bag' => 100,
                    'cost_per_kg' => null,
                    'note' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('feed_types')->whereIn('code', ['bran', 'broken-maize', 'sunflower', 'soya', 'lime'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
