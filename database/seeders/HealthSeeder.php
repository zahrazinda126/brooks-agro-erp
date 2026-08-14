<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Medications and the standard layer vaccination programme.
 |
 | THE WITHDRAWAL FIGURES BELOW ARE TYPICAL, NOT AUTHORITATIVE.
 |
 | The real withdrawal period is printed on the product you actually buy,
 | and it varies by brand, strength and formulation. They are seeded so the
 | system has something sensible from day one, and they are editable
 | because the label wins. The screen says so too.
 |
 | The programme follows common Ugandan practice for commercial layers.
 | Vets differ, disease pressure differs by district, and a farm should set
 | its own with its vet. This is a starting point, not a prescription.
 */
class HealthSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMedications();
        $this->seedLayerProgramme();
        $this->registerModules();
    }

    private function seedMedications(): void
    {
        $rows = [
            /* --- Vaccines. No withdrawal on produce. ------------------ */
            ['code' => 'mareks', 'name' => "Marek's disease vaccine", 'type' => 'vaccine',
             'route' => 'injection', 'treats' => "Marek's disease", 'sort_order' => 10,
             'note' => 'Given at the hatchery on day one. Confirm with your supplier that it was done.'],

            ['code' => 'ndv-lasota', 'name' => 'Newcastle (Lasota)', 'type' => 'vaccine',
             'route' => 'eye_drop', 'treats' => 'Newcastle disease', 'sort_order' => 20,
             'dose' => 'One drop per bird', 'note' => 'Live vaccine. Mix fresh and use within two hours.'],

            ['code' => 'ndv-killed', 'name' => 'Newcastle (killed)', 'type' => 'vaccine',
             'route' => 'injection', 'treats' => 'Newcastle disease', 'sort_order' => 25,
             'note' => 'Given before point of lay for longer cover.'],

            ['code' => 'gumboro', 'name' => 'Gumboro (IBD)', 'type' => 'vaccine',
             'route' => 'water', 'treats' => 'Infectious bursal disease', 'sort_order' => 30,
             'note' => 'Withdraw water two hours before. Use no chlorine in the water.'],

            ['code' => 'fowl-pox', 'name' => 'Fowl pox', 'type' => 'vaccine',
             'route' => 'topical', 'treats' => 'Fowl pox', 'sort_order' => 40,
             'note' => 'Wing web stab. Check for a scab a week later — no scab means it did not take.'],

            ['code' => 'fowl-typhoid', 'name' => 'Fowl typhoid', 'type' => 'vaccine',
             'route' => 'injection', 'treats' => 'Fowl typhoid', 'sort_order' => 50],

            ['code' => 'ib', 'name' => 'Infectious bronchitis', 'type' => 'vaccine',
             'route' => 'water', 'treats' => 'Infectious bronchitis', 'sort_order' => 60],

            /* --- Antibiotics. These carry withdrawal. ----------------- */
            ['code' => 'oxytetracycline', 'name' => 'Oxytetracycline', 'type' => 'antibiotic',
             'route' => 'water', 'treats' => 'Respiratory and general bacterial infection',
             'withdrawal_days_eggs' => 7, 'withdrawal_days_meat' => 7, 'sort_order' => 100,
             'note' => 'Check the pack. Some formulations require longer.'],

            ['code' => 'amprolium', 'name' => 'Amprolium', 'type' => 'coccidiostat',
             'route' => 'water', 'treats' => 'Coccidiosis',
             'withdrawal_days_eggs' => 3, 'withdrawal_days_meat' => 3, 'sort_order' => 110],

            ['code' => 'tylosin', 'name' => 'Tylosin', 'type' => 'antibiotic',
             'route' => 'water', 'treats' => 'Respiratory infection, CRD',
             'withdrawal_days_eggs' => 5, 'withdrawal_days_meat' => 5, 'sort_order' => 120],

            ['code' => 'sulphonamide', 'name' => 'Sulphonamide', 'type' => 'antibiotic',
             'route' => 'water', 'treats' => 'Coccidiosis, fowl typhoid',
             'withdrawal_days_eggs' => 10, 'withdrawal_days_meat' => 10, 'sort_order' => 130],

            ['code' => 'enrofloxacin', 'name' => 'Enrofloxacin', 'type' => 'antibiotic',
             'route' => 'water', 'treats' => 'Severe bacterial infection',
             'withdrawal_days_eggs' => 14, 'withdrawal_days_meat' => 14,
             'banned_in_lay' => true, 'sort_order' => 140,
             'note' => 'Not for birds in lay in many markets. Ask your vet before using on layers.'],

            /* --- Dewormers ------------------------------------------- */
            ['code' => 'piperazine', 'name' => 'Piperazine', 'type' => 'dewormer',
             'route' => 'water', 'treats' => 'Roundworms',
             'withdrawal_days_eggs' => 3, 'withdrawal_days_meat' => 3, 'sort_order' => 200],

            ['code' => 'levamisole', 'name' => 'Levamisole', 'type' => 'dewormer',
             'route' => 'water', 'treats' => 'Roundworms and some others',
             'withdrawal_days_eggs' => 7, 'withdrawal_days_meat' => 7, 'sort_order' => 210],

            ['code' => 'albendazole', 'name' => 'Albendazole', 'type' => 'dewormer',
             'route' => 'water', 'treats' => 'Roundworms and tapeworms',
             'withdrawal_days_eggs' => 4, 'withdrawal_days_meat' => 4, 'sort_order' => 220],

            /* --- Supplements. No withdrawal. -------------------------- */
            ['code' => 'multivitamin', 'name' => 'Multivitamin', 'type' => 'vitamin',
             'route' => 'water', 'treats' => 'Stress, after vaccination, heat', 'sort_order' => 300],

            ['code' => 'vitamin-c', 'name' => 'Vitamin C', 'type' => 'vitamin',
             'route' => 'water', 'treats' => 'Heat stress', 'sort_order' => 310],

            ['code' => 'electrolytes', 'name' => 'Electrolytes', 'type' => 'vitamin',
             'route' => 'water', 'treats' => 'Dehydration, heat, transport stress', 'sort_order' => 320],

            ['code' => 'probiotic', 'name' => 'Probiotic', 'type' => 'vitamin',
             'route' => 'water', 'treats' => 'Gut health, after antibiotics', 'sort_order' => 330],
        ];

        foreach ($rows as $r) {
            DB::table('medications')->updateOrInsert(
                ['code' => $r['code']],
                array_merge([
                    'brand'                => null,
                    'route'                => null,
                    'treats'               => null,
                    'dose'                 => null,
                    'withdrawal_days_eggs' => 0,
                    'withdrawal_days_meat' => 0,
                    'banned_in_lay'        => false,
                    'cost_per_unit'        => null,
                    'unit'                 => null,
                    'note'                 => null,
                    'is_active'            => true,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ], $r)
            );
        }
    }

    /*
     | A common layer programme. Ages in days, because the early weeks are
     | day-precise — Gumboro at 14 days is not the same as at 21.
     */
    private function seedLayerProgramme(): void
    {
        $plan = [
            ['med' => 'mareks',       'age' => 1,   'window' => 1,  'note' => 'Normally done at the hatchery.'],
            ['med' => 'ndv-lasota',   'age' => 7,   'window' => 2],
            ['med' => 'gumboro',      'age' => 14,  'window' => 2],
            ['med' => 'gumboro',      'age' => 21,  'window' => 2,  'note' => 'Booster.'],
            ['med' => 'ndv-lasota',   'age' => 28,  'window' => 3,  'note' => 'Booster.'],
            ['med' => 'fowl-pox',     'age' => 42,  'window' => 5],
            ['med' => 'ndv-lasota',   'age' => 56,  'window' => 5],
            ['med' => 'fowl-typhoid', 'age' => 70,  'window' => 7],
            ['med' => 'ib',           'age' => 84,  'window' => 7],
            ['med' => 'ndv-killed',   'age' => 112, 'window' => 7,  'note' => 'Before point of lay, for longer cover.'],
            ['med' => 'levamisole',   'age' => 119, 'window' => 7,  'note' => 'Deworm before lay begins.'],

            // Through lay
            ['med' => 'ndv-lasota', 'age' => 140, 'window' => 10,
             'repeat' => 70, 'until' => 560, 'note' => 'Every ten weeks through lay.'],

            ['med' => 'levamisole', 'age' => 210, 'window' => 14,
             'repeat' => 90, 'until' => 560, 'note' => 'Deworm every three months.'],
        ];

        foreach ($plan as $p) {
            $medId = DB::table('medications')->where('code', $p['med'])->value('id');

            if (! $medId) {
                continue;
            }

            DB::table('vaccination_plans')->updateOrInsert(
                ['medication_id' => $medId, 'age_days' => $p['age'], 'purpose' => 'layer'],
                [
                    'breed_id'          => null,
                    'window_days'       => $p['window'],
                    'repeat_every_days' => $p['repeat'] ?? null,
                    'repeat_until_days' => $p['until'] ?? null,
                    'is_essential'      => true,
                    'note'              => $p['note'] ?? null,
                    'is_active'         => true,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]
            );
        }
    }

    private function registerModules(): void
    {
        $modules = [
            ['key' => 'health', 'name' => 'Flock Health', 'group' => 'Livestock',
             'route' => 'health.index', 'sort_order' => 120],

            ['key' => 'medications', 'name' => 'Medicines & Vaccines', 'group' => 'Livestock',
             'route' => 'medications.index', 'sort_order' => 126],
        ];

        foreach ($modules as $m) {
            if (! DB::table('modules')->where('key', $m['key'])->exists()) {
                DB::table('modules')->insert(array_merge($m, [
                    'is_worker_module' => false,
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]));
            }
        }

        // Who may see and record health.
        $grants = [
            'production-manager' => false,
            'veterinary-officer' => true,
            'department-manager' => true,
            'daily-supervisor'   => false,   // records, does not approve
            'ceo'                => true,
        ];

        foreach ($grants as $code => $canApprove) {
            $roleId = DB::table('roles')->where('code', $code)->value('id');

            if (! $roleId) {
                continue;
            }

            foreach (['health', 'medications'] as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');

                if (! $moduleId) {
                    continue;
                }

                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $roleId, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => true, 'can_edit' => true,
                     'can_delete' => false, 'can_approve' => $canApprove,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // The Director sees health but records nothing, as everywhere else.
        $dirId = DB::table('roles')->where('code', 'director')->value('id');

        if ($dirId) {
            foreach (['health', 'medications'] as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');

                if ($moduleId) {
                    DB::table('module_role')->updateOrInsert(
                        ['role_id' => $dirId, 'module_id' => $moduleId],
                        ['can_view' => true, 'can_create' => false, 'can_edit' => false,
                         'can_delete' => false, 'can_approve' => false,
                         'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }
}
