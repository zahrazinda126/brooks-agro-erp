<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\User;
use Illuminate\Console\Command;

/*
 | Shows exactly what one account can see, and where each grant comes from.
 |
 | Built because "can a poultry worker see Milk Record?" was being answered
 | by signing in and looking, which is slow and easy to get wrong. This
 | answers it in a second, and shows WHY — role or department — which is
 | what you need to fix a wrong grant.
 |
 |   php artisan access:show clemence
 |   php artisan access:show clemence --species
 */
class ShowUserAccess extends Command
{
    protected $signature = 'access:show {username} {--species : only species-related screens}';

    protected $description = 'Show which screens a user can open, and why';

    /** Screens that are about a particular animal. */
    private const SPECIES_SCREENS = [
        'batches', 'egg-inventory', 'hatchery', 'poultry', 'goats', 'rabbits', 'dogs',
        'egg-entry', 'milk-record', 'kindling-record', 'dog-care',
        'litter-removal', 'pen-cleaning', 'mortality-entry',
        'feed-received', 'feed-consumption', 'vaccination-due', 'breeding',
    ];

    public function handle(): int
    {
        $user = User::with(['roles', 'departments', 'employee'])
            ->where('username', $this->argument('username'))
            ->first();

        if (! $user) {
            $this->error("No account with the username '{$this->argument('username')}'.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("<options=bold>{$user->displayName()}</>  ({$user->username})");
        $this->line('Roles:       ' . ($user->roles->pluck('name')->implode(', ') ?: '—'));
        $this->line('Departments: ' . ($user->departments->pluck('name')->implode(', ') ?: '—'));

        if ($user->isSuperuser()) {
            $this->newLine();
            $this->warn('Superuser — sees every screen. Department scoping does not apply.');

            return self::SUCCESS;
        }

        // Work out where each grant comes from.
        $fromRole = Module::query()
            ->join('module_role', 'modules.id', '=', 'module_role.module_id')
            ->whereIn('module_role.role_id', $user->roleIds())
            ->where('modules.is_active', true)
            ->pluck('modules.key')
            ->unique();

        $fromDept = Module::query()
            ->join('department_module', 'modules.id', '=', 'department_module.module_id')
            ->whereIn('department_module.department_id', $user->departmentIds())
            ->where('modules.is_active', true)
            ->pluck('modules.key')
            ->unique();

        $keys = array_keys($user->abilities());

        if ($this->option('species')) {
            $keys = array_intersect($keys, self::SPECIES_SCREENS);
        }

        $modules = Module::whereIn('key', $keys)->orderBy('group')->orderBy('sort_order')->get();

        $this->newLine();
        $this->line("<options=bold>Can open {$modules->count()} screens</>");
        $this->newLine();

        $rows = [];

        foreach ($modules as $m) {
            $sources = [];

            if ($fromRole->contains($m->key)) {
                $sources[] = 'role';
            }

            if ($fromDept->contains($m->key)) {
                $sources[] = 'department';
            }

            $rows[] = [
                $m->group,
                $m->name,
                implode(' + ', $sources),
            ];
        }

        $this->table(['Group', 'Screen', 'Granted by'], $rows);

        /*
         | The check that matters: is a species screen reaching this person
         | through their job title rather than their posting?
         */
        if (! $this->option('species')) {
            return self::SUCCESS;
        }

        $leaks = [];

        foreach ($modules as $m) {
            if (in_array($m->key, self::SPECIES_SCREENS, true)
                && $fromRole->contains($m->key)
                && ! $fromDept->contains($m->key)) {
                $leaks[] = $m->name;
            }
        }

        $this->newLine();

        if ($leaks) {
            $this->error('Granted by ROLE, not department: ' . implode(', ', $leaks));
            $this->line('These screens are about a species, so they should come from a');
            $this->line('posting. Granted by role, they follow the person between sections.');
        } else {
            $this->info('Every species screen comes from a department posting. Correct.');
        }

        return self::SUCCESS;
    }
}
