<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | Two stages, deliberately separated.
 |
 |   The worker records HOW MANY. That is an observation.
 |   The manager records WHY.      That is a judgement.
 |
 | Keeping them apart means an assessment can be revised without touching
 | the count, and a count is never quietly reinterpreted by whoever
 | assessed it later.
 */
class MortalityRecord extends Model
{
    protected $fillable = [
        'animal_group_id', 'poultry_house_id', 'department_id', 'occurred_on',
        'quantity_died', 'quantity_culled', 'recorded_by', 'recorded_at', 'worker_note',
        'mortality_cause_id', 'assessed_by', 'assessed_at',
        'assessment_note', 'action_taken', 'vet_called', 'vet_name', 'status',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'recorded_at' => 'datetime',
            'assessed_at' => 'datetime',
            'vet_called'  => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(PoultryHouse::class, 'poultry_house_id');
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(MortalityCause::class, 'mortality_cause_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function totalLost(): int
    {
        return $this->quantity_died + $this->quantity_culled;
    }

    public function needsAssessment(): bool
    {
        return $this->status === 'pending_assessment';
    }

    public function scopePendingAssessment($query)
    {
        return $query->where('status', 'pending_assessment');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        return $query->whereIn('department_id', $user->departmentIds());
    }

    /**
     * How long this has sat unassessed. A death waiting three days for a
     * cause is a death whose cause nobody will now remember.
     */
    public function daysWaiting(): int
    {
        return (int) $this->recorded_at->diffInDays(now());
    }
}
