<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | ROLE      = what kind of work you may do   (view, add, approve)
 | DEPARTMENT = which animals you may do it to
 |
 | If a screen is about a species, it is granted by department. Never role.
 |
 | Written as "strip from every role EXCEPT these" rather than a list of
 | roles to strip. A hand-written strip list silently misses any role added
 | later, which is how a poultry worker ended up with Milk Record twice.
 |
 | Run AFTER every other seeder. Safe to re-run.
 */
class SpeciesScopeSeeder extends Seeder
{
    /** Screens that are about a particular animal. */
    private const SPECIES_SCREENS = [
        'batches', 'egg-inventory', 'hatchery',
        'poultry', 'goats', 'rabbits', 'dogs',
        'egg-entry', 'milk-record', 'kindling-record', 'dog-care',
        'litter-removal', 'pen-cleaning',
        'mortality-entry', 'feed-received', 'feed-consumption',
        'vaccination-due', 'breeding',
    ];

    /*
     | Roles that legitimately span every section. Overseeing all species
     | is the job, not an accident. Superusers bypass permission checks
     | entirely and are listed only for clarity.
     */
    private const OVERSEES_ALL = [
        'director', 'ceo', 'system-admin', 'production-manager',
    ];

    public function run(): void
    {
        $this->removeSpeciesScreensFromRoles();
        $this->grantSpeciesScreensToDepartments();
    }

    private function removeSpeciesScreensFromRoles(): void
    {
        $moduleIds = DB::table('modules')
            ->whereIn('key', self::SPECIES_SCREENS)
            ->pluck('id');

        // Every role that is not an all-sections role.
        $roleIds = DB::table('roles')
            ->whereNotIn('code', self::OVERSEES_ALL)
            ->pluck('id');

        DB::table('module_role')
            ->whereIn('role_id', $roleIds)
            ->whereIn('module_id', $moduleIds)
            ->delete();
    }

    private function grantSpeciesScreensToDepartments(): void
    {
        // Every section keeps animals, so every section needs these.
        $common = [
            'batches',
            'mortality-entry', 'feed-received', 'feed-consumption',
            'vaccination', 'vaccination-due', 'pen-cleaning',
            'veterinary',
        ];

        $map = [
            'poultry' => array_merge($common, [
                'poultry', 'hatchery', 'egg-entry', 'egg-inventory', 'litter-removal',
            ]),

            'goats' => array_merge($common, [
                'goats', 'milk-record', 'breeding',
            ]),

            'rabbits' => array_merge($common, [
                'rabbits', 'kindling-record', 'breeding',
            ]),

            'dogs' => array_merge($common, [
                'dogs', 'dog-care', 'breeding',
            ]),

            // Production and Veterinary oversee every section.
            'production' => array_merge($common, [
                'poultry', 'goats', 'rabbits', 'dogs',
                'hatchery', 'egg-entry', 'egg-inventory', 'litter-removal',
                'milk-record', 'kindling-record', 'dog-care',
                'breeding', 'production', 'feedmill', 'quality',
            ]),

            'veterinary' => array_merge($common, [
                'poultry', 'goats', 'rabbits', 'dogs',
                'biosecurity', 'breeding',
            ]),
        ];

        foreach ($map as $deptCode => $keys) {
            $deptId = DB::table('departments')->where('code', $deptCode)->value('id');

            if (! $deptId) {
                continue;
            }

            foreach (array_unique($keys) as $key) {
                $moduleId = DB::table('modules')->where('key', $key)->value('id');

                if (! $moduleId) {
                    continue;
                }

                DB::table('department_module')->updateOrInsert(
                    ['department_id' => $deptId, 'module_id' => $moduleId],
                    [
                        'can_view'   => true,
                        'can_create' => true,
                        // Workers submit records; correcting them is a
                        // supervisor's job, granted through their role.
                        'can_edit'   => false,
                        'can_delete' => false,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}