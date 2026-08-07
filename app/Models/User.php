<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'name', 'email', 'password',
        'employee_id', 'role_id', 'department_id',
        'is_active', 'must_change_password',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password'             => 'hashed',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at'        => 'datetime',
            'password_changed_at'  => 'datetime',
            'password_expires_at'  => 'datetime',
            'locked_until'         => 'datetime',
        ];
    }

    /* Primary posting — what shows on a badge. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /* The full set — what permissions are computed from. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['is_primary', 'assigned_by'])
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['is_primary', 'assigned_by'])
            ->withTimestamps();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function passwordHasExpired(): bool
    {
        return $this->password_expires_at !== null
            && $this->password_expires_at->isPast();
    }

    public function isSuperuser(): bool
    {
        return $this->roles->contains('is_superuser', true)
            || (bool) ($this->role?->is_superuser);
    }

    /** Seniority is the most senior role held — lower rank is more senior. */
    public function effectiveRank(): int
    {
        $ranks = $this->roles->pluck('rank')->filter()->all();

        if ($this->role?->rank) {
            $ranks[] = $this->role->rank;
        }

        return $ranks ? (int) min($ranks) : 999;
    }

    private ?array $abilityCache = null;

    /**
     * The union of every grant from every role held and every department
     * posted to. A worker in both Rabbits and Goats sees Kindling Record
     * and Milk Record; a worker in Poultry alone sees neither.
     */
    public function abilities(): array
    {
        if ($this->abilityCache !== null) {
            return $this->abilityCache;
        }

        $abilities = [];

        if ($this->isSuperuser()) {
            foreach (Module::where('is_active', true)->pluck('key') as $key) {
                $abilities[$key] = [
                    'view' => true, 'create' => true, 'edit' => true,
                    'delete' => true, 'approve' => true,
                ];
            }

            return $this->abilityCache = $abilities;
        }

        $merge = function (Collection $rows) use (&$abilities) {
            foreach ($rows as $row) {
                $current = $abilities[$row->key] ?? [
                    'view' => false, 'create' => false, 'edit' => false,
                    'delete' => false, 'approve' => false,
                ];

                $abilities[$row->key] = [
                    'view'    => $current['view']    || $row->can_view,
                    'create'  => $current['create']  || $row->can_create,
                    'edit'    => $current['edit']    || $row->can_edit,
                    'delete'  => $current['delete']  || $row->can_delete,
                    'approve' => $current['approve'] || $row->can_approve,
                ];
            }
        };

        $roleIds = $this->roleIds();
        $deptIds = $this->departmentIds();

        if ($roleIds) {
            $merge(
                Module::query()
                    ->join('module_role', 'modules.id', '=', 'module_role.module_id')
                    ->whereIn('module_role.role_id', $roleIds)
                    ->where('modules.is_active', true)
                    ->select('modules.key', 'module_role.can_view', 'module_role.can_create',
                             'module_role.can_edit', 'module_role.can_delete', 'module_role.can_approve')
                    ->get()
            );
        }

        if ($deptIds) {
            $merge(
                Module::query()
                    ->join('department_module', 'modules.id', '=', 'department_module.module_id')
                    ->whereIn('department_module.department_id', $deptIds)
                    ->where('modules.is_active', true)
                    ->select('modules.key', 'department_module.can_view', 'department_module.can_create',
                             'department_module.can_edit', 'department_module.can_delete',
                             'department_module.can_approve')
                    ->get()
            );
        }

        return $this->abilityCache = $abilities;
    }

    public function roleIds(): array
    {
        $ids = $this->roles->pluck('id')->all();

        if ($this->role_id) {
            $ids[] = $this->role_id;
        }

        return array_values(array_unique($ids));
    }

    public function departmentIds(): array
    {
        $ids = $this->departments->pluck('id')->all();

        if ($this->department_id) {
            $ids[] = $this->department_id;
        }

        return array_values(array_unique($ids));
    }

    public function canAccess(string $moduleKey): bool
    {
        return (bool) ($this->abilities()[$moduleKey]['view'] ?? false);
    }

    public function canDo(string $moduleKey, string $action): bool
    {
        return (bool) ($this->abilities()[$moduleKey][$action] ?? false);
    }

    public function sidebarModules(): Collection
    {
        $allowed = array_keys(array_filter(
            $this->abilities(),
            fn ($a) => $a['view'] === true
        ));

        return Module::whereIn('key', $allowed)
            ->where('is_active', true)
            ->get()
            // Containers are legacy finance headings. Every role now sees
            // one consistent business-area menu instead.
            ->filter(fn (Module $module) => $module->key !== 'dashboard' && ! $module->is_container)
            ->sortBy(fn (Module $module) => sprintf('%03d-%05d',
                Module::navigationGroupOrder($module->navigationGroup()), $module->sort_order))
            ->groupBy(fn (Module $module) => $module->navigationGroup());
    }

        /*
     | Where this person lands after signing in.
     |
     | A dashboard is a management tool — it summarises so that someone can
     | decide. A worker is not deciding anything; they are recording what
     | happened in their house. Sending them to a screen of totals puts a
     | step between signing in and doing the job.
     |
     | Checked by role rather than by grant, because a worker's DEPARTMENT
     | may still carry the dashboard and we do not want to strip it from
     | supervisors in the same section.
     */
    public function isDataEntryOnly(): bool
    {
        if ($this->isSuperuser()) {
            return false;
        }

        $codes = $this->roles->pluck('code')->all();

        if ($this->role?->code) {
            $codes[] = $this->role->code;
        }

        $codes = array_unique($codes);

        // Only if EVERY role they hold is a data-entry role.
        return $codes !== [] && empty(array_diff($codes, ['casual-worker']));
    }

    /** The route name this person should be sent to on sign-in. */
    public function homeRoute(): string
    {
        if ($this->isDataEntryOnly()) {
            return 'daily.index';
        }

        return $this->canAccess('dashboard') ? 'dashboard' : 'daily.index';
    }

    public function displayName(): string
    {
        return $this->employee
            ? trim($this->employee->first_name . ' ' . $this->employee->last_name)
            : $this->name;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->displayName()))[0];
    }

    /** "Production Manager + 1 more" for the top bar. */
    public function roleSummary(): string
    {
        $names = $this->roles->pluck('name');

        if ($names->isEmpty()) {
            return $this->role?->name ?? 'No role';
        }

        $primary = $this->roles->firstWhere('pivot.is_primary', true) ?? $this->roles->first();
        $extra   = $names->count() - 1;

        return $extra > 0 ? "{$primary->name} + {$extra} more" : $primary->name;
    }

    public function departmentSummary(): string
    {
        $names = $this->departments->pluck('name');

        if ($names->isEmpty()) {
            return $this->department?->name ?? 'No department';
        }

        return $names->implode(', ');
    }
}
