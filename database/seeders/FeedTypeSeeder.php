<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Feeds common on Ugandan poultry farms.
 |
 | Grouped by what they are FOR, because that is how a farmer thinks about
 | them and it keeps the entry screen short:
 |
 |   complete   — a full ration, fed on its own
 |   energy     — bulk carbohydrate, mixed in
 |   protein    — mixed in to raise protein
 |   mineral    — shells and grit, for shell strength
 |   supplement — vitamins and additives
 |
 | Every farm mixes differently and suppliers change, so this is a starting
 | list. Staff add their own on the Feed Types screen.
 */
class FeedTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            /* --- Complete rations ------------------------------------- */
            ['code' => 'chick-mash', 'name' => 'Chick mash', 'category' => 'complete',
             'from_week' => 0, 'to_week' => 8, 'sort_order' => 10,
             'note' => 'Day old to about 8 weeks.'],

            ['code' => 'growers-mash', 'name' => 'Growers mash', 'category' => 'complete',
             'from_week' => 8, 'to_week' => 18, 'sort_order' => 20,
             'note' => 'From 8 weeks until point of lay.'],

            ['code' => 'layer-mash', 'name' => 'Layer mash', 'category' => 'complete',
             'from_week' => 17, 'sort_order' => 30,
             'note' => 'From point of lay onwards. Higher calcium.'],

            ['code' => 'broiler-starter', 'name' => 'Broiler starter', 'category' => 'complete',
             'from_week' => 0, 'to_week' => 3, 'sort_order' => 40],

            ['code' => 'broiler-grower', 'name' => 'Broiler grower', 'category' => 'complete',
             'from_week' => 3, 'to_week' => 5, 'sort_order' => 50],

            ['code' => 'broiler-finisher', 'name' => 'Broiler finisher', 'category' => 'complete',
             'from_week' => 5, 'sort_order' => 60],

            ['code' => 'kienyeji-mash', 'name' => 'Kienyeji mash', 'category' => 'complete',
             'sort_order' => 70, 'note' => 'For local and dual-purpose birds.'],

            /* --- Energy ----------------------------------------------- */
            ['code' => 'maize-bran', 'name' => 'Maize bran', 'category' => 'energy',
             'sort_order' => 100, 'note' => 'Cheap bulk. Too much dilutes protein.'],

            ['code' => 'maize-germ', 'name' => 'Maize germ', 'category' => 'energy', 'sort_order' => 110],

            ['code' => 'wheat-bran', 'name' => 'Wheat bran', 'category' => 'energy', 'sort_order' => 120],

            ['code' => 'cassava-meal', 'name' => 'Cassava meal', 'category' => 'energy', 'sort_order' => 130],

            /* --- Protein ---------------------------------------------- */
            ['code' => 'silverfish', 'name' => 'Silverfish (mukene)', 'category' => 'protein',
             'sort_order' => 200, 'note' => 'Strong protein source. Check it is not spoiled.'],

            ['code' => 'soya-meal', 'name' => 'Soya meal', 'category' => 'protein', 'sort_order' => 210],

            ['code' => 'cotton-seed-cake', 'name' => 'Cotton seed cake', 'category' => 'protein',
             'sort_order' => 220],

            ['code' => 'sunflower-cake', 'name' => 'Sunflower cake', 'category' => 'protein',
             'sort_order' => 230],

            ['code' => 'concentrate', 'name' => 'Layer concentrate', 'category' => 'protein',
             'sort_order' => 240, 'default_kg_per_bag' => 25,
             'note' => 'Mixed with bran and maize to make a full ration.'],

            /* --- Mineral ---------------------------------------------- */
            ['code' => 'shells', 'name' => 'Shells / limestone', 'category' => 'mineral',
             'sort_order' => 300, 'note' => 'For shell strength. Thin shells usually mean too little.'],

            ['code' => 'grit', 'name' => 'Grit', 'category' => 'mineral', 'sort_order' => 310],

            ['code' => 'bone-meal', 'name' => 'Bone meal', 'category' => 'mineral', 'sort_order' => 320],

            /* --- Supplement ------------------------------------------- */
            ['code' => 'premix', 'name' => 'Vitamin premix', 'category' => 'supplement',
             'sort_order' => 400, 'default_kg_per_bag' => 25],

            ['code' => 'salt', 'name' => 'Salt', 'category' => 'supplement',
             'sort_order' => 410, 'default_kg_per_bag' => 25],
        ];

        foreach ($rows as $r) {
            DB::table('feed_types')->updateOrInsert(
                ['code' => $r['code']],
                array_merge([
                    'from_week'          => null,
                    'to_week'            => null,
                    'default_kg_per_bag' => 50,
                    'cost_per_kg'        => null,
                    'note'               => null,
                    'is_active'          => true,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ], $r)
            );
        }

        // Carry across anything already recorded with the old text column.
        $map = [
            'chick_mash'       => 'chick-mash',
            'growers_mash'     => 'growers-mash',
            'layer_mash'       => 'layer-mash',
            'broiler_starter'  => 'broiler-starter',
            'broiler_finisher' => 'broiler-finisher',
        ];

        foreach ($map as $old => $new) {
            $id = DB::table('feed_types')->where('code', $new)->value('id');

            if ($id) {
                DB::table('feed_consumptions')
                    ->where('feed_type', $old)
                    ->whereNull('feed_type_id')
                    ->update(['feed_type_id' => $id]);
            }
        }

        // Make the module reachable.
        if (! DB::table('modules')->where('key', 'feed-types')->exists()) {
            DB::table('modules')->insert([
                'key' => 'feed-types', 'name' => 'Feed Types', 'group' => 'Operations',
                'route' => 'feed-types.index', 'sort_order' => 235,
                'is_worker_module' => false, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('key', 'feed-types')->value('id');

        foreach (['production-manager', 'inventory-officer', 'store-keeper'] as $code) {
            $roleId = DB::table('roles')->where('code', $code)->value('id');

            if ($roleId && $moduleId) {
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $roleId, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => true, 'can_edit' => true,
                     'can_delete' => false, 'can_approve' => false,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
