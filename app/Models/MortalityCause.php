<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MortalityCause extends Model
{
    protected $fillable = ['code', 'name', 'category', 'description',
                           'needs_vet', 'is_contagious', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'needs_vet'     => 'boolean',
            'is_contagious' => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function scopeOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Plain-language grouping for the assessment screen. */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'disease'     => 'Illness',
            'environment' => 'Conditions in the house',
            'predator'    => 'Predator or theft',
            'injury'      => 'Injury',
            'culling'     => 'Culled on purpose',
            default       => 'Not established',
        };
    }
}
