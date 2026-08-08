<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            OrgChartSeeder::class,
            SpecSeeder::class,
            PoultrySeeder::class,
            SpeciesScopeSeeder::class,
            DailyRecordsSeeder::class,
            WorkerHomeSeeder::class,
            FeedTypeSeeder::class,
            GovernanceSeeder::class,
            HealthSeeder::class,
            EggGradeSimplificationSeeder::class,
            FinanceStructureSeeder::class,
        ]);
    }
}
