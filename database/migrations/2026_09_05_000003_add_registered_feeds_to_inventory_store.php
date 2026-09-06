<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $store = DB::table('inventory_stores')->where('code', 'feed')->first();
        $creatorId = DB::table('users')->orderBy('id')->value('id');
        if (! $store || ! $creatorId) return;

        foreach (DB::table('feed_types')->where('is_active', true)->get() as $feed) {
            DB::table('inventory_items')->updateOrInsert(
                ['inventory_store_id'=>$store->id, 'code'=>'FEED-'.strtoupper($feed->code)],
                [
                    'name'=>$feed->name,
                    'category'=>'feed',
                    'unit'=>'kg',
                    'reorder_level'=>0,
                    'default_location'=>$store->name,
                    'notes'=>'Linked to the registered feed type.',
                    'is_active'=>true,
                    'created_by'=>$creatorId,
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Keep historical feed items because stock movements may reference them.
    }
};
