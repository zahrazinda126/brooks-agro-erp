<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 | A feed the farm buys or mixes.
 |
 | Grouped by what it is FOR rather than by brand, because that is how a
 | farmer chooses one and it keeps the entry screen short: a complete
 | ration is fed on its own, everything else is mixed in.
 */
class FeedType extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'from_week', 'to_week',
        'default_kg_per_bag', 'cost_per_kg', 'note', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'cost_per_kg' => 'decimal:2',
        ];
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(FeedConsumption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Plain wording a worker would recognise. */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'complete'   => 'Main feed',
            'energy'     => 'Energy — mixed in',
            'protein'    => 'Protein — mixed in',
            'mineral'    => 'Minerals and shells',
            'supplement' => 'Supplements',
            default      => 'Other',
        };
    }

    /**
     * Does this feed suit a flock of the given age?
     *
     * Used to put the likely feeds first rather than to forbid anything —
     * a farm may legitimately feed something unusual, and a screen that
     * refuses what someone actually did just gets worked around.
     */
    public function suitsWeek(?int $week): bool
    {
        if ($week === null || $this->from_week === null) {
            return true;
        }

        return $week >= $this->from_week
            && ($this->to_week === null || $week <= $this->to_week);
    }

    public function isComplete(): bool
    {
        return $this->category === 'complete';
    }
}
