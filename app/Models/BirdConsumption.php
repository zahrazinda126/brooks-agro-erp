<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | A bird that left the flock for the kitchen.
 |
 | Kept apart from mortality on purpose. Mortality is a health figure that
 | managers act on; a staff function should never look like a disease
 | spike. And it is not a sale either — no money arrived.
 |
 | What it is: a bird gone, no cash in, and a cost the farm avoided by not
 | buying meat. Worth recording precisely because it is the easiest thing
 | on a farm to lose track of.
 */
class BirdConsumption extends Model
{
    protected $fillable = [
        'animal_group_id', 'department_id', 'consumed_on', 'quantity',
        'purpose', 'detail', 'estimated_value',
        'authorised_by', 'authorised_name',
        'recorded_by', 'recorded_at',
        'status', 'confirmed_by', 'confirmed_at', 'query_note',
    ];

    protected function casts(): array
    {
        return [
            'consumed_on'     => 'date',
            'recorded_at'     => 'datetime',
            'confirmed_at'    => 'datetime',
            'estimated_value' => 'decimal:2',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function authoriser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        return $query->whereIn('department_id', $user->departmentIds());
    }

    public function scopeUnconfirmed($query)
    {
        return $query->where('status', 'submitted');
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            'staff_meals' => 'Staff meals',
            'management'  => 'Management household',
            'visitors'    => 'Visitors',
            'event'       => 'Farm event',
            'sick_bird'   => 'Sick bird, slaughtered early',
            default       => 'Other',
        };
    }

    public function whoAuthorised(): string
    {
        return $this->authoriser?->displayName()
            ?? $this->authorised_name
            ?? 'not recorded';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
