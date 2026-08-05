<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Which screens are for DOING the work, and which are for MANAGING it.
 |
 | The two-axis model handles species correctly — a Poultry worker does not
 | see Milk Record. But it cannot separate a worker from a supervisor in the
 | SAME section, because both hold the Poultry department, and the
 | department is what grants Batch Management, Hatchery and Veterinary.
 |
 | So there is a third distinction, and it belongs on the module itself:
 |
 |   ROLE       = what kind of work you may do
 |   DEPARTMENT = which animals you may do it to
 |   is_worker_module = whether this screen is for doing or for managing
 |
 | Someone whose only role is data entry sees worker modules and nothing
 | else, regardless of what their department grants. A supervisor in the
 | same section sees everything.
 |
 | Run AFTER every other seeder.
 */
class WorkerModuleSeeder extends Seeder
{
    /*
     | Screens a worker uses to record what they did.
     |
     | Deliberately short. If a screen summarises, compares, lists history
     | or configures anything, it is not on this list — those are for
     | someone deciding, not someone recording.
     */
    private const WORKER_MODULES = [
        // Their own work
        'my-work', 'my-tasks', 'my-performance',
        'notifications', 'messages',

        // Daily recording
        'egg-entry', 'mortality-entry',
        'feed-received', 'feed-consumption',
        'pen-cleaning', 'litter-removal',
        'vaccination-due',
        'milk-record', 'kindling-record', 'dog-care',
    ];

    public function run(): void
    {
        // Everything is a management screen unless named above.
        DB::table('modules')->update(['is_worker_module' => false]);

        DB::table('modules')
            ->whereIn('key', self::WORKER_MODULES)
            ->update(['is_worker_module' => true, 'updated_at' => now()]);

        $count = DB::table('modules')->where('is_worker_module', true)->count();

        $this->command?->info("Marked {$count} screens as worker screens.");
    }
}
