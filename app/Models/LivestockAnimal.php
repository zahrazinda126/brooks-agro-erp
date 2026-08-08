<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivestockAnimal extends Model
{
    protected $fillable=['unit_code','animal_tag','name','sex','breed','date_of_birth','acquired_on','acquisition_type','dam_tag','sire_tag','acquisition_cost','status','notes','recorded_by'];
    protected function casts(): array { return ['date_of_birth'=>'date','acquired_on'=>'date','acquisition_cost'=>'decimal:2']; }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class,'recorded_by'); }
}
