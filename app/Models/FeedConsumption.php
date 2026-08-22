<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | One row per feed, per flock, per day.
 |
 | Mixing is normal — layer mash plus a bran top-up plus shells — so a day
 | can have several rows. Recording only the main ration would make the
 | feed conversion figure wrong, and that figure is what the module exists
 | to produce.
 */
class FeedConsumption extends Model
{
    protected $fillable = [
        'animal_group_id', 'poultry_house_id', 'department_id', 'feed_type_id', 'used_on', 'feed_type',
        'quantity_kg', 'bags', 'kg_per_bag',
        'recorded_by', 'recorded_at', 'worker_note',
        'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'used_on'     => 'date',
            'recorded_at' => 'datetime',
            'approved_at' => 'datetime',
            'quantity_kg' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(PoultryHouse::class, 'poultry_house_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(FeedType::class, 'feed_type_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Grams per bird per day, across everything fed that day — comparable
     * against the breed's expected figure, which is how waste and theft
     * show up.
     */
    public function gramsPerBird(): ?float
    {
        $birds = $this->batch?->current_quantity ?? 0;

        if ($birds < 1) {
            return null;
        }

        return round((float) $this->quantity_kg * 1000 / $birds, 1);
    }

    public function label(): string
    {
        return $this->type?->name ?? ucfirst(str_replace('_', ' ', (string) $this->feed_type));
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        return $query->whereIn('department_id', $user->departmentIds());
    }
}
