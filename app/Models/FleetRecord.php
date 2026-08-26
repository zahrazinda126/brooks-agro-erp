<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetRecord extends Model
{
    protected $fillable = [
        'recorded_on', 'vehicle_id', 'driver_name', 'set_off_at', 'pickup_point',
        'destination', 'people_count', 'purpose', 'fuel_consumption_litres',
        'starting_odometer_km', 'ending_odometer_km', 'fuel_cost_ugx',
        'fuel_receipt', 'next_service_odometer_km', 'vehicle_issues', 'maintenance', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['recorded_on'=>'date', 'set_off_at'=>'datetime', 'fuel_consumption_litres'=>'decimal:2', 'starting_odometer_km'=>'decimal:1', 'ending_odometer_km'=>'decimal:1', 'fuel_cost_ugx'=>'decimal:2', 'next_service_odometer_km'=>'decimal:1'];
    }

    public function distanceKm():?float{return $this->starting_odometer_km!==null&&$this->ending_odometer_km!==null?(float)$this->ending_odometer_km-(float)$this->starting_odometer_km:null;}
    public function kilometresPerLitre():?float{return $this->distanceKm()!==null&&(float)$this->fuel_consumption_litres>0?$this->distanceKm()/(float)$this->fuel_consumption_litres:null;}
    public function litresPerHundredKm():?float{return $this->distanceKm()>0&&$this->fuel_consumption_litres!==null?(float)$this->fuel_consumption_litres/$this->distanceKm()*100:null;}
    public function costPerKm():?float{return $this->distanceKm()>0&&$this->fuel_cost_ugx!==null?(float)$this->fuel_cost_ugx/$this->distanceKm():null;}

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
