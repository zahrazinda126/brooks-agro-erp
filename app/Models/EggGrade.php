<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 | An egg grade is a price band AND a signal about the flock.
 |
 | A hen's first eggs are small and grow with her, so the grade recorded
 | says something about age. isExpectedAtWeek() is what lets the system
 | distinguish a young flock laying small eggs (normal) from a mature
 | flock laying small eggs (a problem worth investigating).
 */
class EggGrade extends Model
{
    protected $fillable = [
        'code', 'name', 'sort_order', 'min_weight_g', 'max_weight_g',
        'typical_from_week', 'typical_to_week',
        'is_defect', 'default_price', 'eggs_per_tray', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_defect'     => 'boolean',
            'is_active'     => 'boolean',
            'default_price' => 'decimal:2',
        ];
    }

    public function scopeSaleable($query)
    {
        return $query->where('is_defect', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /** Would this size be normal for a flock of the given age? */
    public function isExpectedAtWeek(?int $week): bool
    {
        // Defects have no age range — they happen at any age.
        if ($week === null || $this->typical_from_week === null) {
            return true;
        }

        return $week >= $this->typical_from_week
            && ($this->typical_to_week === null || $week <= $this->typical_to_week);
    }

    public function weightRange(): string
    {
        if (! $this->min_weight_g) {
            return '—';
        }

        return "{$this->min_weight_g}–{$this->max_weight_g} g";
    }
}
