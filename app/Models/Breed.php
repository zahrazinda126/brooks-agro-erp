<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    protected $fillable = [
        'code', 'name', 'species', 'purpose',
        'lay_start_week', 'peak_week', 'lay_end_week', 'peak_lay_percent',
        'mature_weight_g', 'daily_feed_g', 'slaughter_week',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(AnimalGroup::class);
    }

    public function scopePoultry($query)
    {
        return $query->where('species', 'poultry');
    }

    public function laysEggs(): bool
    {
        return $this->lay_start_week !== null;
    }
}
