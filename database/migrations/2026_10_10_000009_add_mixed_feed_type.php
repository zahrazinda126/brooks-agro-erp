<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Feed reaches the poultry houses already mixed, so a poultry attendant records one figure: the mixed feed
 * given to the house. It is stored as feed consumption of this type.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('feed_types')->updateOrInsert(['code' => 'mixed-feed'], [
            'name' => 'Mixed feed', 'category' => 'complete', 'default_kg_per_bag' => 50, 'sort_order' => 1,
            'note' => 'Feed mixed on the farm and given to the birds.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feed_types')->where('code', 'mixed-feed')->delete();
    }
};
