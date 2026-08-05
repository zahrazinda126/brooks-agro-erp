<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/*
 | Something that was actually done to a flock — a vaccination, a course of
 | treatment, a deworming, an inspection.
 |
 | The withdrawal dates are computed once, when the record is saved, and
 | stored. They are deliberately NOT worked out on demand from the
 | medication: if someone corrects a medication's withdrawal period next
 | year, that must not silently change whether last month's eggs were safe
 | to sell. What was true at the time stays true.
 */
class HealthEvent extends Model
{
    protected $fillable = [
        'animal_group_id', 'department_id', 'event_type', 'medication_id',
        'occurred_on', 'birds_treated', 'dose_given', 'days_of_course',
        'withdrawal_ends_eggs', 'withdrawal_ends_meat',
        'reason', 'findings', 'cost',
        'administered_by', 'administered_name', 'vet_involved',
        'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on'          => 'date',
            'withdrawal_ends_eggs' => 'date',
            'withdrawal_ends_meat' => 'date',
            'recorded_at'          => 'datetime',
            'vet_involved'         => 'boolean',
            'cost'                 => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        return $query->whereIn('department_id', $user->departmentIds());
    }

    /* ---------------------------------------------------------------
     | Withdrawal
     |
     | The single most important thing this module does. Eggs laid while a
     | flock is under withdrawal must not be sold.
     | --------------------------------------------------------------- */

    /**
     * The date eggs from this flock become safe again, or null if nothing
     * is holding them.
     */
    public static function eggsSafeFrom(int $batchId, ?Carbon $on = null): ?Carbon
    {
        $on = $on ?: now();

        $date = static::where('animal_group_id', $batchId)
            ->whereNotNull('withdrawal_ends_eggs')
            ->where('withdrawal_ends_eggs', '>=', $on->toDateString())
            ->max('withdrawal_ends_eggs');

        return $date ? Carbon::parse($date) : null;
    }

    /** Is this flock under egg withdrawal right now? */
    public static function eggsUnderWithdrawal(int $batchId, ?Carbon $on = null): bool
    {
        return static::eggsSafeFrom($batchId, $on) !== null;
    }

    /** Days left before the eggs may be sold. */
    public static function daysOfWithdrawalLeft(int $batchId): ?int
    {
        $until = static::eggsSafeFrom($batchId);

        return $until ? (int) now()->startOfDay()->diffInDays($until, false) + 1 : null;
    }

    /**
     * Work out when produce is clean again.
     *
     | Counted from the END of the course, not the start. A five-day course
     | of a drug with seven days' withdrawal holds the eggs for twelve days
     | from the first dose, and getting that wrong is the difference between
     | safe eggs and an illegal sale.
     */
    public static function withdrawalEnd(?Medication $med, Carbon $start, int $courseDays, string $for = 'eggs'): ?Carbon
    {
        if (! $med) {
            return null;
        }

        $days = $for === 'meat' ? $med->withdrawal_days_meat : $med->withdrawal_days_eggs;

        if ($days < 1) {
            return null;
        }

        return $start->copy()
            ->addDays(max(1, $courseDays) - 1)   // last day of the course
            ->addDays($days);
    }

    /* --------------------------------------------------------------- */

    public function typeLabel(): string
    {
        return match ($this->event_type) {
            'vaccination' => 'Vaccination',
            'treatment'   => 'Treatment',
            'deworming'   => 'Deworming',
            'inspection'  => 'Health check',
            'vet_visit'   => 'Vet visit',
            default       => 'Health event',
        };
    }

    public function whoGave(): string
    {
        return $this->administrator?->displayName()
            ?? $this->administered_name
            ?? $this->recorder->displayName();
    }

    public function holdsEggs(): bool
    {
        return $this->withdrawal_ends_eggs !== null
            && $this->withdrawal_ends_eggs->gte(now()->startOfDay());
    }
}
