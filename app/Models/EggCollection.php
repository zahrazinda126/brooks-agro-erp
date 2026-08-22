<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EggCollection extends Model
{
    protected $fillable = [
        'animal_group_id', 'poultry_house_id', 'department_id', 'collected_on', 'collected_at', 'session',
        'total_eggs', 'egg_mass_g', 'damaged_eggs', 'bird_weight_kg', 'feed_amount_kg',
        'recorded_by', 'recorded_at', 'worker_note',
        'status', 'approved_by', 'approved_at', 'query_note',
    ];

    protected function casts(): array
    {
        return [
            'collected_on' => 'date',
            'recorded_at'  => 'datetime',
            'approved_at'  => 'datetime',
            'egg_mass_g' => 'decimal:2',
            'bird_weight_kg' => 'decimal:2',
            'feed_amount_kg' => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EggCollectionLine::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function trayCount(): float
    {
        return round($this->total_eggs / 30, 1);
    }

    /**
     * Eggs per 100 hens — the number that actually says whether a flock is
     * performing. A raw egg count means nothing without the bird count
     * beside it.
     */
    public function layPercent(): ?float
    {
        $birds = $this->batch?->current_quantity ?? 0;

        if ($birds < 1) {
            return null;
        }

        return round($this->total_eggs / $birds * 100, 1);
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperuser()) {
            return $query;
        }

        return $query->whereIn('department_id', $user->departmentIds());
    }
}
