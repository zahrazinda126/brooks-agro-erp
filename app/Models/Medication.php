<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 | Something that can be given to birds — a vaccine, an antibiotic, a
 | dewormer, a vitamin.
 |
 | The important field is the withdrawal period. After most antibiotics,
 | eggs must not be sold for several days; selling them is illegal and
 | unsafe. The seeded figures are typical, but the real one is on the pack
 | you actually bought, which is why they are editable.
 */
class Medication extends Model
{
    protected $fillable = [
        'code', 'name', 'brand', 'type', 'route', 'treats', 'dose',
        'withdrawal_days_eggs', 'withdrawal_days_meat', 'banned_in_lay',
        'cost_per_unit', 'unit', 'note', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'banned_in_lay' => 'boolean',
            'is_active'     => 'boolean',
            'cost_per_unit' => 'decimal:2',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(VaccinationPlan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function isVaccine(): bool
    {
        return $this->type === 'vaccine';
    }

    /** Does giving this stop eggs being sold for a while? */
    public function hasWithdrawal(): bool
    {
        return $this->withdrawal_days_eggs > 0 || $this->withdrawal_days_meat > 0;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'vaccine'      => 'Vaccine',
            'antibiotic'   => 'Antibiotic',
            'dewormer'     => 'Dewormer',
            'coccidiostat' => 'For coccidiosis',
            'vitamin'      => 'Vitamin or supplement',
            default        => 'Other',
        };
    }

    public function routeLabel(): string
    {
        return match ($this->route) {
            'water'     => 'In drinking water',
            'feed'      => 'In feed',
            'eye_drop'  => 'Eye drop',
            'nasal'     => 'Nasal drop',
            'injection' => 'Injection',
            'spray'     => 'Spray',
            'topical'   => 'On the skin',
            default     => '—',
        };
    }
}
