<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Reference data for daily records, plus two permission changes:
 |
 |  1. Financial approval belongs to the Director alone.
 |  2. Assessing why birds died belongs to the Production Manager and the
 |     Veterinary Officer — not to the worker who counted them.
 */
class DailyRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMortalityCauses();
        $this->seedStartingPrices();
        $this->restrictFinancialApprovalToDirector();
        $this->grantAssessmentToManagers();
    }

    /*
     | Causes are written in plain language a manager uses out loud, not in
     | veterinary Latin. Where a technical name matters it goes in the
     | description, so the list stays readable while the record stays
     | precise.
     */
    private function seedMortalityCauses(): void
    {
        $rows = [
            /* --- Disease --- */
            ['code' => 'newcastle', 'name' => 'Newcastle disease', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 10,
             'description' => 'Twisted neck, paralysis, green droppings. Spreads fast — report immediately.'],

            ['code' => 'gumboro', 'name' => 'Gumboro', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 20,
             'description' => 'Infectious bursal disease. Usually young birds, 3–6 weeks.'],

            ['code' => 'coccidiosis', 'name' => 'Coccidiosis', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 30,
             'description' => 'Bloody droppings, huddling, ruffled feathers. Often follows wet litter.'],

            ['code' => 'fowl-typhoid', 'name' => 'Fowl typhoid', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 40],

            ['code' => 'crd', 'name' => 'Respiratory infection', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 50,
             'description' => 'Coughing, sneezing, rattling breath, swollen face.'],

            ['code' => 'fowl-pox', 'name' => 'Fowl pox', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => true, 'sort_order' => 60],

            ['code' => 'other-disease', 'name' => 'Other illness', 'category' => 'disease',
             'needs_vet' => true, 'is_contagious' => false, 'sort_order' => 70,
             'description' => 'Sick but not clearly one of the above. Describe the signs in the notes.'],

            /* --- Environment --- */
            ['code' => 'heat-stress', 'name' => 'Heat stress', 'category' => 'environment',
             'sort_order' => 100,
             'description' => 'Panting, wings held out, deaths concentrated in the afternoon.'],

            ['code' => 'cold-stress', 'name' => 'Cold', 'category' => 'environment', 'sort_order' => 110],

            ['code' => 'suffocation', 'name' => 'Crowding or smothering', 'category' => 'environment',
             'sort_order' => 120,
             'description' => 'Birds piled in a corner, often after a fright at night.'],

            ['code' => 'no-water', 'name' => 'Water ran out', 'category' => 'environment',
             'sort_order' => 130],

            ['code' => 'poor-ventilation', 'name' => 'Poor ventilation', 'category' => 'environment',
             'sort_order' => 140, 'description' => 'Ammonia smell, damp litter, dull birds.'],

            /* --- Predator --- */
            ['code' => 'predator', 'name' => 'Predator', 'category' => 'predator', 'sort_order' => 200,
             'description' => 'Snake, mongoose, hawk, dog, rat. Note which in the assessment.'],

            ['code' => 'theft', 'name' => 'Theft', 'category' => 'predator', 'sort_order' => 210,
             'description' => 'Birds missing rather than found dead. Investigate separately.'],

            /* --- Injury --- */
            ['code' => 'injury', 'name' => 'Injury or accident', 'category' => 'injury', 'sort_order' => 300],
            ['code' => 'cannibalism', 'name' => 'Pecking / cannibalism', 'category' => 'injury',
             'sort_order' => 310, 'description' => 'Often caused by crowding, bright light or low protein.'],
            ['code' => 'prolapse', 'name' => 'Prolapse', 'category' => 'injury', 'sort_order' => 320,
             'description' => 'Laying injury. Common in flocks that started laying too early.'],

            /* --- Culling --- */
            ['code' => 'culled-sick', 'name' => 'Culled — sick', 'category' => 'culling', 'sort_order' => 400],
            ['code' => 'culled-poor', 'name' => 'Culled — poor layer', 'category' => 'culling', 'sort_order' => 410],
            ['code' => 'culled-age', 'name' => 'Culled — end of lay', 'category' => 'culling', 'sort_order' => 420],

            /* --- Unknown. Deliberately present. --- */
            ['code' => 'unknown', 'name' => 'Cause not established', 'category' => 'unknown',
             'sort_order' => 900,
             'description' => 'Use this honestly rather than guessing. A guess recorded today becomes a fact in next year\'s report.'],
        ];

        foreach ($rows as $r) {
            DB::table('mortality_causes')->updateOrInsert(
                ['code' => $r['code']],
                array_merge([
                    'description'   => null,
                    'needs_vet'     => false,
                    'is_contagious' => false,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ], $r)
            );
        }
    }

    /**
     * Opening prices, valid from today. These are placeholders — the real
     * ones are entered on the Egg Prices screen, and each change closes
     * the previous period rather than overwriting it.
     */
    private function seedStartingPrices(): void
    {
        $defaults = [
            'starter'     => 9000,
            'small'       => 10000,
            'medium'      => 11500,
            'large'       => 13000,
            'extra-large' => 14000,
            'cracked'     => 6000,
            'dirty'       => 8000,
            'broken'      => 0,
            'soft-shell'  => 0,
        ];

        foreach ($defaults as $code => $price) {
            $gradeId = DB::table('egg_grades')->where('code', $code)->value('id');

            if (! $gradeId) {
                continue;
            }

            $exists = DB::table('egg_prices')->where('egg_grade_id', $gradeId)->exists();

            if ($exists) {
                continue;   // never overwrite a real price
            }

            DB::table('egg_prices')->insert([
                'egg_grade_id'   => $gradeId,
                'price_per_tray' => $price,
                'effective_from' => now()->toDateString(),
                'effective_to'   => null,
                'note'           => 'Opening placeholder — replace with the current market price.',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    /*
     | Financial approval sits with the Director.
     |
     | Managers may still raise and record financial transactions; what is
     | removed is the ability to approve them. Separating who spends from
     | who authorises is the oldest control in accounting, and it is worth
     | more than any amount of reporting after the fact.
     */
    private function restrictFinancialApprovalToDirector(): void
    {
        $financial = [
            'finance', 'payroll', 'purchases', 'purchase-orders', 'quotations',
            'transactions', 'journal-entries', 'general-ledger', 'cash-book',
            'bank-book', 'payables', 'receivables', 'assets', 'budget',
            'invoices', 'receipts', 'returns', 'loans', 'advances',
        ];

        $moduleIds = DB::table('modules')->whereIn('key', $financial)->pluck('id');

        // Everyone except the Director loses the approve bit.
        $keepIds = DB::table('roles')->whereIn('code', ['director'])->pluck('id');

        DB::table('module_role')
            ->whereIn('module_id', $moduleIds)
            ->whereNotIn('role_id', $keepIds)
            ->update(['can_approve' => false, 'updated_at' => now()]);

        DB::table('department_module')
            ->whereIn('module_id', $moduleIds)
            ->update(['can_approve' => false, 'updated_at' => now()]);

        // And the Director explicitly has it.
        $directorId = DB::table('roles')->where('code', 'director')->value('id');

        if ($directorId) {
            foreach ($moduleIds as $mid) {
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $directorId, 'module_id' => $mid],
                    [
                        'can_view' => true, 'can_create' => true, 'can_edit' => true,
                        'can_delete' => false, 'can_approve' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]
                );
            }
        }
    }

    /*
     | Assessing a death is a judgement, so it belongs to the people
     | qualified to make it. The worker's count stands on its own and is
     | never altered by the assessment.
     */
    private function grantAssessmentToManagers(): void
    {
        // Add the assessment screen if it is not there yet.
        if (! DB::table('modules')->where('key', 'mortality-assess')->exists()) {
            DB::table('modules')->insert([
                'key'              => 'mortality-assess',
                'name'             => 'Assess Deaths',
                'group'            => 'Workflow',
                'route'            => 'mortality.assess.index',
                'sort_order'       => 345,
                'is_worker_module' => false,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('key', 'mortality-assess')->value('id');

        foreach (['production-manager', 'veterinary-officer', 'director', 'ceo'] as $code) {
            $roleId = DB::table('roles')->where('code', $code)->value('id');

            if (! $roleId || ! $moduleId) {
                continue;
            }

            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                [
                    'can_view' => true, 'can_create' => true, 'can_edit' => true,
                    'can_delete' => false, 'can_approve' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }
}
