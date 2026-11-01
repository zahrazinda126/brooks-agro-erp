<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/*
 | A batch of animals kept and costed together.
 |
 | Age drives almost everything about a poultry batch: when it starts
 | laying, what size eggs to expect, how much feed it eats, when it should
 | be culled. So age is computed here once and every other calculation
 | reads it, rather than each screen working it out again.
 */
class AnimalGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'department_id', 'breed_id', 'purpose',
        'received_date', 'age_days_on_arrival', 'expected_brooder_exit_date',
        'quantity_received', 'quantity_free', 'transit_deaths',
        'unit_cost', 'total_cost', 'supplier_id', 'supplier_name', 'invoice_no',
        'current_quantity', 'total_mortality', 'total_culled', 'total_sold',
        'expected_lay_date', 'actual_lay_date',
        'house', 'status', 'closed_date', 'notes', 'created_by',
        'edit_requested_by', 'edit_requested_at', 'edit_authorized_by',
        'edit_authorized_for', 'edit_authorized_until',
    ];

    protected function casts(): array
    {
        return [
            'received_date'     => 'date',
            'expected_brooder_exit_date' => 'date',
            'expected_lay_date' => 'date',
            'actual_lay_date'   => 'date',
            'closed_date'       => 'date',
            'edit_requested_at' => 'datetime',
            'edit_authorized_until' => 'datetime',
            'unit_cost'         => 'decimal:2',
            'total_cost'        => 'decimal:2',
        ];
    }

    /* ---------------------------------------------------------------
     | Relationships
     | --------------------------------------------------------------- */

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------------
     | How many arrived
     |
     | Suppliers throw in extras to cover early losses — 1,000 on the
     | invoice, 1,020 in the crate. Both figures matter and they are not
     | the same figure:
     |
     |   paid    decides the money
     |   total   decides the birds
     |
     | Keeping them apart is what makes cost per bird honest.
     | --------------------------------------------------------------- */

    public function totalReceived(): int
    {
        return $this->quantity_received + $this->quantity_free;
    }

    /** Birds physically alive when the consignment reached the farm. */
    public function liveReceived(): int
    {
        return max(0, $this->totalReceived() - $this->transit_deaths);
    }

    public function hasFreeExtras(): bool
    {
        return $this->quantity_free > 0;
    }

    /** The extras as a percentage of what was paid for. */
    public function freeExtraPercent(): float
    {
        if ($this->quantity_received < 1) {
            return 0;
        }

        return round($this->quantity_free / $this->quantity_received * 100, 1);
    }

    /* ---------------------------------------------------------------
     | Age
     |
     | Measured from hatch, not from arrival: birds bought at point of lay
     | are already 16 weeks old when they reach the farm, and every
     | expectation in the system is keyed to age since hatch.
     | --------------------------------------------------------------- */

    public function ageInDays(?Carbon $on = null): int
    {
        $on = $on ?: now();

        return (int) $this->age_days_on_arrival
            + (int) $this->received_date->diffInDays($on);
    }

    public function ageInWeeks(?Carbon $on = null): int
    {
        return intdiv($this->ageInDays($on), 7);
    }

    /**
     * Where this batch is in its life.
     *
     * The stages matter operationally: brooding needs heat, growing needs
     * a different feed, point of lay is when egg collection starts, and
     * "spent" is the signal to plan replacements.
     */
    public function stage(): string
    {
        $weeks = $this->ageInWeeks();

        if (! $this->willLay()) {
            $slaughter = $this->breed?->slaughter_week ?? 6;

            return match (true) {
                $weeks < 2          => 'brooding',
                $weeks < $slaughter => 'growing',
                default             => 'ready',
            };
        }

        $start = $this->layStartWeek();
        $peak  = $this->breed?->peak_week ?? 28;
        $end   = $this->breed?->lay_end_week ?? 80;

        return match (true) {
            $weeks < 8      => 'brooding',
            $weeks < $start => 'growing',
            $weeks < $peak  => 'early lay',
            $weeks < $end   => 'in lay',
            default         => 'spent',
        };
    }

    /**
     * Will these birds ever lay?
     *
     * The BREED has the final say. A batch's `purpose` is typed by a
     * person and can be wrong; the breed carries its own biology, and a
     * Cobb 500 does not lay whatever the form says. Without this, a
     * broiler batch cheerfully announces "first eggs expected in 18
     * weeks" — which is how people learn to distrust a system.
     */
    public function willLay(): bool
    {
        if ($this->breed) {
            return $this->breed->laysEggs();
        }

        return $this->purpose !== 'broiler';
    }

    public function layStartWeek(): int
    {
        return $this->breed?->lay_start_week ?? 18;
    }

    /** Should this batch be producing eggs yet? */
    public function shouldBeLaying(): bool
    {
        return $this->willLay() && $this->ageInWeeks() >= $this->layStartWeek();
    }

    /**
     * Weeks until the first eggs are expected. Negative once laying has
     * started or should have. Null when the birds will never lay.
     */
    public function weeksToLay(): ?int
    {
        if (! $this->willLay()) {
            return null;
        }

        return $this->layStartWeek() - $this->ageInWeeks();
    }

    /** True when the batch and its breed disagree about what these birds are. */
    public function purposeMismatch(): bool
    {
        if (! $this->breed) {
            return false;
        }

        return $this->breed->laysEggs() !== ($this->purpose !== 'broiler');
    }

    /**
     * Overdue if it should have started laying and hasn't. Two weeks of
     * grace, because a small delay is normal and a false alarm every time
     * would train people to ignore it.
     */
    public function layingIsOverdue(): bool
    {
        return $this->shouldBeLaying()
            && $this->actual_lay_date === null
            && ($this->weeksToLay() ?? 0) <= -2;
    }

    /**
     * Which egg grades this flock should mostly be producing now. Used to
     * order the collection form so the likely sizes come first, and to
     * flag a mature flock still laying small.
     */
    public function expectedGrades()
    {
        $weeks = $this->ageInWeeks();

        return EggGrade::query()
            ->where('is_defect', false)
            ->where('is_active', true)
            ->where('typical_from_week', '<=', $weeks)
            ->where(function ($q) use ($weeks) {
                $q->whereNull('typical_to_week')->orWhere('typical_to_week', '>=', $weeks);
            })
            ->orderBy('sort_order')
            ->get();
    }

    /* ---------------------------------------------------------------
     | Numbers
     | --------------------------------------------------------------- */

    /** Deaths as a percentage of every bird that arrived, free ones included. */
    public function mortalityRate(): float
    {
        $received = $this->liveReceived();

        if ($received < 1) {
            return 0;
        }

        return round($this->total_mortality / $received * 100, 2);
    }

    /**
     * Mortality is judged against age, not as one flat number. Losses in
     * the first fortnight are normal; the same rate at 30 weeks is not.
     */
    public function mortalityIsHigh(): bool
    {
        $weeks = $this->ageInWeeks();
        $rate  = $this->mortalityRate();

        return match (true) {
            $weeks <= 2  => $rate > 5,
            $weeks <= 8  => $rate > 8,
            $weeks <= 20 => $rate > 12,
            default      => $rate > 15,
        };
    }

    /**
     | Cost of the birds themselves, before feed and medicine.
     |
     | Real money divided by real birds. Free extras lower it, which is the
     | point of a supplier giving them.
     */
    public function costPerBirdAlive(): float
    {
        if ($this->current_quantity < 1) {
            return 0;
        }

        return round((float) $this->total_cost / $this->current_quantity, 2);
    }

    /** What each bird cost on arrival, extras included. */
    public function effectiveUnitCost(): float
    {
        $received = $this->liveReceived();

        if ($received < 1) {
            return 0;
        }

        return round((float) $this->total_cost / $received, 2);
    }

    /* ---------------------------------------------------------------
     | Scopes and helpers
     | --------------------------------------------------------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Restricts a list to the departments a user is posted to. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();
        $departmentCodes = $user->departments->pluck('code')->push($user->department?->code)->filter();
        if ($roleCodes->contains('production-manager')
            || ($roleCodes->contains('department-manager')
                && $departmentCodes->intersect(['production', 'div-production'])->isNotEmpty())) {
            return $query;
        }

        return $query->whereIn('department_id', $user->operationalDepartmentIds());
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function mayBeEditedBy(User $user): bool
    {
        $roles = $user->roles->pluck('code')->push($user->role?->code);
        if ($roles->contains('director')) return true;

        return $this->edit_authorized_for === $user->id
            && $this->edit_authorized_until?->isFuture();
    }

    public function label(): string
    {
        return $this->name ? "{$this->code} · {$this->name}" : $this->code;
    }
}
