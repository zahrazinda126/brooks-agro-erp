<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmUnitRecord extends Model
{
    protected $fillable = ['unit_code','record_type','record_date','subject','quantity','measure_unit','amount','status','notes','recorded_by'];
    protected function casts(): array { return ['record_date'=>'date','quantity'=>'decimal:2','amount'=>'decimal:2']; }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
