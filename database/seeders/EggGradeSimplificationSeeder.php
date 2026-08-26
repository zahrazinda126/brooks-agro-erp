<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | The farm grades eggs as Small, Medium and Large. That is all.
 |
 | I seeded five sizes on the assumption that a hen's first eggs would be
 | sold as a separate "starter" grade and the biggest as "extra large".
 | Brooks Agro does neither — the small early eggs go out as Small, and
 | nothing is sold above Large.
 |
 | Two grades are therefore switched OFF rather than deleted, because
 | deleting would orphan any record already made against them. Small then
 | widens to cover what starter used to, and Large to cover the top end.
 |
 | The age ranges matter as much as the weights: they are what let the
 | system tell a young flock laying small eggs (normal) from a mature one
 | doing the same (a feed or health problem).
 */
class EggGradeSimplificationSeeder extends Seeder
{
    public function run(): void
    {
        // Off, not deleted.
        DB::table('egg_grades')
            ->whereIn('code', ['starter', 'extra-large'])
            ->update(['is_active' => false, 'updated_at' => now()]);

        /*
         | Small now begins at point of lay and takes the lightest eggs a
         | flock produces. 40 g rather than 46, because the first eggs of a
         | young hen really are that light and they are still sold.
         */
        DB::table('egg_grades')->where('code', 'small')->update([
            'name'              => 'Small',
            'min_weight_g'      => 40,
            'max_weight_g'      => 52,
            'typical_from_week' => 18,
            'typical_to_week'   => 27,
            'sort_order'        => 10,
            'is_active'         => true,
            'updated_at'        => now(),
        ]);

        DB::table('egg_grades')->where('code', 'medium')->update([
            'min_weight_g'      => 53,
            'max_weight_g'      => 62,
            'typical_from_week' => 25,
            'typical_to_week'   => 40,
            'sort_order'        => 20,
            'is_active'         => true,
            'updated_at'        => now(),
        ]);

        // Large takes everything above medium, with no upper weight.
        DB::table('egg_grades')->where('code', 'large')->update([
            'min_weight_g'      => 63,
            'max_weight_g'      => 90,
            'typical_from_week' => 32,
            'typical_to_week'   => 80,
            'sort_order'        => 30,
            'is_active'         => true,
            'updated_at'        => now(),
        ]);

        $damagedId = DB::table('egg_grades')->whereIn('code', ['damaged','cracked'])->orderByRaw("case when code = 'damaged' then 0 else 1 end")->value('id');
        if ($damagedId) {
            DB::table('egg_grades')->where('id', $damagedId)->update(['code'=>'damaged','name'=>'Damaged eggs','sort_order'=>60,'is_defect'=>true,'is_active'=>true,'updated_at'=>now()]);
            DB::table('egg_grades')->where('is_defect', true)->where('id', '!=', $damagedId)->update(['is_active'=>false,'updated_at'=>now()]);
        }

        /*
         | Close the price period on the two retired grades so they stop
         | showing as current. Their history stays intact.
         */
        $retired = DB::table('egg_grades')
            ->whereIn('code', ['starter', 'extra-large'])
            ->pluck('id');

        DB::table('egg_prices')
            ->whereIn('egg_grade_id', $retired)
            ->whereNull('effective_to')
            ->update([
                'effective_to' => now()->subDay()->toDateString(),
                'note'         => 'Grade retired — the farm sells Small, Medium and Large only.',
                'updated_at'   => now(),
            ]);

        $this->command?->info('Egg grades set to Small, Medium, Large and Damaged eggs.');
    }
}
