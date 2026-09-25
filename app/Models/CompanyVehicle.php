<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyVehicle extends Model
{
    protected $fillable = [
        'registration_number', 'make_model', 'vehicle_type', 'capacity',
        'default_driver_name', 'current_odometer_km', 'next_service_odometer_km',
        'is_active', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'current_odometer_km' => 'decimal:1',
            'next_service_odometer_km' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
