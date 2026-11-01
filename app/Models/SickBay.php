<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place where sick animals of one unit are kept apart and looked after. */
class SickBay extends Model
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs'];

    protected $fillable = ['unit_code', 'name', 'notes', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(SickBayAdmission::class);
    }
}
