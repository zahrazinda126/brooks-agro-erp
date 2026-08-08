<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetRecord extends Model
{
    protected $fillable = [
        'recorded_on', 'vehicle_id', 'driver_name', 'set_off_at', 'pickup_point',
        'destination', 'people_count', 'purpose', 'fuel_consumption_litres',
        'fuel_receipt', 'vehicle_issues', 'maintenance', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['recorded_on'=>'date', 'set_off_at'=>'datetime', 'fuel_consumption_litres'=>'decimal:2'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
