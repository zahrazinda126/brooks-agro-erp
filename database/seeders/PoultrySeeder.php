<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Breeds common on Ugandan farms, and the egg grades that follow from a
 | flock's age.
 |
 | Every figure here is a starting default, editable in the application.
 | Conditions, feed quality and management change these substantially —
 | what matters is that the system has a baseline to compare against, so
 | it can tell you when a flock is behind.
 */
class PoultrySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBreeds();
        $this->seedEggGrades();
    }

    private function seedBreeds(): void
    {
        $rows = [
            /* --- Commercial layers ------------------------------------ */
            [
                'code' => 'isa-brown', 'name' => 'ISA Brown', 'purpose' => 'layer',
                'lay_start_week' => 18, 'peak_week' => 28, 'lay_end_week' => 80,
                'peak_lay_percent' => 95, 'mature_weight_g' => 1900, 'daily_feed_g' => 115,
                'notes' => 'Reliable brown-egg layer. Point of lay around 18 weeks.',
            ],
            [
                'code' => 'lohmann-brown', 'name' => 'Lohmann Brown', 'purpose' => 'layer',
                'lay_start_week' => 18, 'peak_week' => 27, 'lay_end_week' => 80,
                'peak_lay_percent' => 94, 'mature_weight_g' => 2000, 'daily_feed_g' => 115,
            ],
            [
                'code' => 'hy-line-brown', 'name' => 'Hy-Line Brown', 'purpose' => 'layer',
                'lay_start_week' => 18, 'peak_week' => 28, 'lay_end_week' => 80,
                'peak_lay_percent' => 96, 'mature_weight_g' => 1980, 'daily_feed_g' => 112,
            ],
            [
                'code' => 'bovans-brown', 'name' => 'Bovans Brown', 'purpose' => 'layer',
                'lay_start_week' => 19, 'peak_week' => 28, 'lay_end_week' => 78,
                'peak_lay_percent' => 93, 'mature_weight_g' => 1950, 'daily_feed_g' => 114,
            ],

            /* --- Broilers --------------------------------------------- */
            [
                'code' => 'cobb-500', 'name' => 'Cobb 500', 'purpose' => 'broiler',
                'slaughter_week' => 6, 'mature_weight_g' => 2400, 'daily_feed_g' => 120,
                'notes' => 'Fast-growing broiler. Market weight around 6 weeks.',
            ],
            [
                'code' => 'ross-308', 'name' => 'Ross 308', 'purpose' => 'broiler',
                'slaughter_week' => 6, 'mature_weight_g' => 2500, 'daily_feed_g' => 122,
            ],

            /* --- Dual purpose and local ------------------------------- */
            [
                'code' => 'kuroiler', 'name' => 'Kuroiler', 'purpose' => 'dual',
                'lay_start_week' => 22, 'peak_week' => 32, 'lay_end_week' => 72,
                'peak_lay_percent' => 70, 'mature_weight_g' => 2200, 'daily_feed_g' => 100,
                'notes' => 'Hardy dual-purpose bird. Tolerates scavenging and local conditions.',
            ],
            [
                'code' => 'sasso', 'name' => 'Sasso', 'purpose' => 'dual',
                'lay_start_week' => 24, 'peak_week' => 34, 'lay_end_week' => 72,
                'peak_lay_percent' => 65, 'mature_weight_g' => 2600, 'daily_feed_g' => 105,
            ],
            [
                'code' => 'kienyeji', 'name' => 'Local (Kienyeji)', 'purpose' => 'dual',
                'lay_start_week' => 26, 'peak_week' => 36, 'lay_end_week' => 70,
                'peak_lay_percent' => 50, 'mature_weight_g' => 1600, 'daily_feed_g' => 85,
                'notes' => 'Indigenous stock. Lays later and less, but hardy and premium priced.',
            ],
        ];

        foreach ($rows as $r) {
            DB::table('breeds')->updateOrInsert(
                ['code' => $r['code']],
                array_merge([
                    'species'          => 'poultry',
                    'lay_start_week'   => null,
                    'peak_week'        => null,
                    'lay_end_week'     => null,
                    'peak_lay_percent' => null,
                    'mature_weight_g'  => null,
                    'daily_feed_g'     => null,
                    'slaughter_week'   => null,
                    'notes'            => null,
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ], $r)
            );
        }
    }

    /*
     | Egg grades.
     |
     | The age ranges are the point. A hen's first eggs are small and grow
     | with her: starter eggs at point of lay, small by about 22 weeks,
     | medium by 26, large from 32 onwards. Recording the grade therefore
     | says something about the flock, not just the price.
     |
     | Weights follow the common East African market bands.
     */
    private function seedEggGrades(): void
    {
        $rows = [
            [
                'code' => 'starter', 'name' => 'Starter (pullet)', 'sort_order' => 10,
                'min_weight_g' => 35, 'max_weight_g' => 45,
                'typical_from_week' => 18, 'typical_to_week' => 23,
                'default_price' => 9000,
                'is_defect' => false,
            ],
            [
                'code' => 'small', 'name' => 'Small', 'sort_order' => 20,
                'min_weight_g' => 46, 'max_weight_g' => 52,
                'typical_from_week' => 21, 'typical_to_week' => 27,
                'default_price' => 10000,
            ],
            [
                'code' => 'medium', 'name' => 'Medium', 'sort_order' => 30,
                'min_weight_g' => 53, 'max_weight_g' => 62,
                'typical_from_week' => 25, 'typical_to_week' => 40,
                'default_price' => 11500,
            ],
            [
                'code' => 'large', 'name' => 'Large', 'sort_order' => 40,
                'min_weight_g' => 63, 'max_weight_g' => 72,
                'typical_from_week' => 32, 'typical_to_week' => 80,
                'default_price' => 13000,
            ],
            [
                'code' => 'extra-large', 'name' => 'Extra large', 'sort_order' => 50,
                'min_weight_g' => 73, 'max_weight_g' => 90,
                'typical_from_week' => 45, 'typical_to_week' => 80,
                'default_price' => 14000,
            ],

            /* --- Defects. No age range: these happen at any age. ------ */
            [
                'code' => 'cracked', 'name' => 'Cracked', 'sort_order' => 60,
                'is_defect' => true, 'default_price' => 6000,
            ],
            [
                'code' => 'dirty', 'name' => 'Dirty', 'sort_order' => 70,
                'is_defect' => true, 'default_price' => 8000,
            ],
            [
                'code' => 'broken', 'name' => 'Broken', 'sort_order' => 80,
                'is_defect' => true, 'default_price' => 0,
            ],
            [
                'code' => 'soft-shell', 'name' => 'Soft shell', 'sort_order' => 90,
                'is_defect' => true, 'default_price' => 0,
                'notes' => null,
            ],
        ];

        foreach ($rows as $r) {
            DB::table('egg_grades')->updateOrInsert(
                ['code' => $r['code']],
                array_merge([
                    'min_weight_g'      => null,
                    'max_weight_g'      => null,
                    'typical_from_week' => null,
                    'typical_to_week'   => null,
                    'is_defect'         => false,
                    'default_price'     => 0,
                    'eggs_per_tray'     => 30,
                    'is_active'         => true,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ], array_diff_key($r, ['notes' => null]))
            );
        }
    }
}
