<?php

namespace App\Support;

use App\Models\User;

/**
 * Monitor tabs (Production, Feeds Management, Health & Treatment) are split by task
 * (Feed consumption, Deaths …). Each task page has a Unit filter; picking a unit
 * opens that unit's page for the same task.
 */
class MonitorTasks
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden'];

    public const GROUPS = ['production' => 'Production', 'feeds' => 'Feeds Management', 'health' => 'Health & Treatment'];

    /**
     * group => task => [label, hint, [unit => page], landing page (optional), [other pages of this task] (optional)];
     * a page is [route, params].
     */
    public static function tasks(): array
    {
        $livestock = fn (string $section) => collect(['goats', 'rabbits', 'dogs'])
            ->mapWithKeys(fn ($unit) => [$unit => ['operations.livestock.section', ['unit' => $unit, 'section' => $section]]])->all();
        $garden = fn (string $type) => ['garden' => ['operations.units.index', ['unit' => 'garden', 'type' => $type]]];
        $allAnimals = fn (string $task) => collect(['poultry', 'goats', 'rabbits', 'dogs'])
            ->mapWithKeys(fn ($unit) => [$unit => ['monitor.health', ['task' => $task, 'unit' => $unit]]])->all();

        return [
            'production' => [
                'eggs' => ['Production', 'Eggs, births, milk and harvest.', ['poultry' => ['poultry.report.index', []]]
                    + collect(['goats', 'rabbits', 'dogs'])->mapWithKeys(fn ($unit) => [$unit => ['operations.livestock.section', ['unit' => $unit, 'section' => 'production']]])->all()
                    + $garden('harvest')],
                // Registers that used to be reached from the Farm Units tabs.
                'register' => ['Batches & groups', 'Start a poultry batch or register a goat, rabbit or dog group.', ['poultry' => ['batches.index', []]] + $livestock('register') + $garden('plot')],
                'breeding' => ['Breeding', 'Mating, pregnancies and expected births.', $livestock('breeding')],
                'garden' => ['Garden work', 'Planting, field activity, inputs, irrigation and produce sales.', ['garden' => ['operations.units.index', ['unit' => 'garden']]]],
            ],
            'feeds' => [
                'consumption' => ['Feed consumption', 'Feed given to each batch or group.', ['poultry' => ['poultry.report.index', ['view' => 'feed']]] + $livestock('feed')],
                'mixing' => ['Feed mixing', 'Ingredients taken for mixing, mixed feed sent to the stores and received.', ['poultry' => ['operations.feed-mixing.index', []]]],
                'verification' => ['Feeds verification', 'Supplier deliveries checked against farm weights.', collect(['poultry', 'goats', 'rabbits', 'dogs'])->mapWithKeys(fn ($unit) => [$unit => ['operations.feed-stock.index', ['unit' => $unit]]])->all()],
            ],
            'health' => [
                // Treatments and Deaths list every animal unit together (with a Unit filter on the page).
                'treatments' => ['Treatments', 'Illness, treatment, deworming and spraying.', $allAnimals('treatments'), ['monitor.health', ['task' => 'treatments']],
                    [['operations.livestock.section', ['section' => 'treatment']], ['operations.livestock.section', ['section' => 'deworming']], ['operations.livestock.section', ['section' => 'spraying']], ['poultry.report.index', ['view' => 'health']]]],
                'vaccination' => ['Vaccination', 'Vaccines due and vaccines given.', ['poultry' => ['vaccination-schedule.index', []]] + $livestock('vaccination')],
                'deaths' => ['Deaths', 'Animals that died or were culled.', $allAnimals('deaths'), ['monitor.health', ['task' => 'deaths']],
                    [['operations.livestock.section', ['section' => 'mortality']], ['poultry.report.index', ['view' => 'mortality']]]],
                // One page for every unit's sick bays (with a Unit filter).
                'sickbay' => ['Sick bay', 'Sick animals kept apart, how they are doing, recovered or lost.', collect(['poultry', 'goats', 'rabbits', 'dogs'])->mapWithKeys(fn ($unit) => [$unit => ['sick-bay.index', ['unit' => $unit]]])->all(), ['sick-bay.index', []]],
                'examination' => ['Death examination', 'Why birds died and the action taken.', ['poultry' => ['mortality.assess.index', []]]],
            ],
        ];
    }

    /** Units this user can open for a task, as unit => url (carrying the date filters). */
    public static function unitLinks(User $user, string $group, string $task, array $keep = []): array
    {
        $pages = self::tasks()[$group][$task][2] ?? [];
        $links = [];
        foreach ($pages as $unit => [$route, $params]) {
            if (! self::canOpen($user, $unit, $route)) continue;
            $links[$unit] = route($route, $params + array_filter($keep) + ['monitor' => $group]);
        }
        return $links;
    }

    /** Where a task's card leads: its landing page, or the first unit it covers. */
    public static function landing(User $user, string $group, string $task): ?string
    {
        $units = self::unitLinks($user, $group, $task);
        if ($units === []) return null;
        [$route, $params] = self::tasks()[$group][$task][3] ?? [null, []];
        return $route ? route($route, $params) : reset($units);
    }

    /** Tasks reached from a button on another task page instead of their own card (Death examination is on Deaths). */
    private const NOT_ON_HUB = ['health' => ['examination']];

    /** Tasks of a group this user can open at least one unit for. */
    public static function visibleTasks(User $user, string $group): array
    {
        return array_filter(self::tasks()[$group] ?? [], fn ($task, $key) => ! in_array($key, self::NOT_ON_HUB[$group] ?? [], true)
            && self::unitLinks($user, $group, $key) !== [], ARRAY_FILTER_USE_BOTH);
    }

    /** The task a page belongs to, found from its unit and route (for the Unit filter and trail). */
    public static function taskFor(string $group, string $unit, string $route, array $params = []): ?string
    {
        foreach (self::tasks()[$group] ?? [] as $key => $definition) {
            $candidates = array_merge([$definition[2][$unit] ?? [null, []]], $definition[4] ?? []);
            foreach ($candidates as [$pageRoute, $pageParams]) {
                if ($pageRoute === $route && array_intersect_assoc($pageParams, $params) == $pageParams) return $key;
            }
        }
        return null;
    }

    /**
     * Farm Units › one unit: every page of that unit as [label, route, params], in Monitor's order
     * (production, feeds, health), then the unit's houses and report. Same pages as Monitor, reached unit-first.
     */
    public static function unitPages(User $user, string $unit): array
    {
        $labels = ['register' => $unit === 'poultry' ? 'Batches' : ($unit === 'garden' ? 'Plot register' : 'Register'),
            'eggs' => $unit === 'poultry' ? 'Eggs' : 'Production', 'consumption' => 'Feeding'];
        $pages = [];
        // Goats, rabbits and dogs: the unit's own sections (the same tabs the unit page always had), in that order.
        if (in_array($unit, ['goats', 'rabbits', 'dogs'], true) && $user->canAccessFarmUnit($unit)) {
            foreach (['register' => 'Register & Intake', 'feed' => 'Daily Feeding', 'breeding' => 'Breeding', 'vaccination' => 'Vaccination',
                'treatment' => 'Treatment & Illness', 'mortality' => 'Mortality', 'production' => 'Production & Births'] as $section => $label) {
                $pages['operations.livestock.section?'.http_build_query(['unit' => $unit, 'section' => $section])] = [$label, 'operations.livestock.section', ['unit' => $unit, 'section' => $section]];
            }
        }
        // Garden: its record types, as on the Garden page itself.
        if ($unit === 'garden') {
            if (! $user->canAccessFarmUnit('garden')) return [];
            $pages = [['All activity', 'operations.units.index', ['unit' => 'garden']]];
            foreach (['plot' => 'Plot register', 'planting' => 'Planting', 'field_activity' => 'Field activity', 'input_application' => 'Input application',
                'irrigation' => 'Irrigation', 'harvest' => 'Harvest', 'loss' => 'Crop loss', 'sale' => 'Produce sale'] as $type => $label) {
                $pages[] = [$label, 'operations.units.index', ['unit' => 'garden', 'type' => $type]];
            }
            $pages[] = ['Report', 'operations.units.report', ['unit' => 'garden']];

            return $pages;
        }
        foreach (['production', 'feeds', 'health'] as $group) {
            foreach (self::tasks()[$group] as $key => $definition) {
                [$route, $params] = $definition[2][$unit] ?? [null, []];
                if (! $route || ! self::canOpen($user, $unit, $route)) continue;
                if (in_array($unit, ['goats', 'rabbits', 'dogs'], true) && in_array($route, ['operations.livestock.section', 'monitor.health'], true)) continue;
                $pages[$route.'?'.http_build_query($params)] ??= [$labels[$key] ?? $definition[0], $route, $params];
            }
        }
        if ($unit === 'poultry' && \Illuminate\Support\Facades\Route::has('poultry-houses.index')) {
            $pages['houses'] = ['Houses & workers', 'poultry-houses.index', []];
        }
        // Poultry's report is its Eggs page (with feed, deaths and health views); other units have their unit report.
        if ($unit !== 'poultry') {
            $pages['report'] = ['Report', 'operations.units.report', ['unit' => $unit]];
        }

        // Production first for poultry is the egg report; keep Batches as the opening page.
        $ordered = collect($pages)->sortBy(fn ($p, $k) => str_starts_with($k, 'batches.index') ? 0 : 1, SORT_REGULAR, false);

        return $ordered->values()->all();
    }

    private static function canOpen(User $user, string $unit, string $route): bool
    {
        // Feed deliveries are handled by production and the store for every unit.
        if ($route === 'operations.feed-stock.index') return $user->canAccess('feed-received');
        if ($route === 'operations.feed-mixing.index') return $user->canAccess('feed-received') || $user->canViewAllReports() || $user->canAccess('production');
        if ($route === 'sick-bay.index' && \App\Http\Controllers\SickBayController::canRecord($user)) return true;
        if ($unit !== 'poultry') return $user->canAccessFarmUnit($unit);

        $report = $user->canViewAllReports() || $user->canAccess('production') || $user->canAccess('batches') || $user->canAccess('egg-inventory');
        return match ($route) {
            'sick-bay.index' => $report || \App\Http\Controllers\SickBayController::canRecord($user),
            'vaccination-schedule.index' => $user->canAccess('vaccination') || $user->roles->pluck('code')->push($user->role?->code)->intersect(['production-manager', 'ceo', 'director'])->isNotEmpty(),
            'operations.feed-stock.index' => $user->canAccess('feed-received'),
            'mortality.assess.index' => $user->canAccess('mortality-assess'),
            default => $report,
        };
    }
}
