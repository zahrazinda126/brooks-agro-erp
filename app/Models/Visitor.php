<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    protected $fillable = ['visitor_name','phone','organization','purpose','person_to_visit','visit_date',
        'checked_in_at','checked_out_at','vehicle_registration','items_carried','pass_number','notes',
        'recorded_by','checked_out_by'];

    protected function casts(): array
    {
        return ['visit_date'=>'date', 'checked_in_at'=>'datetime', 'checked_out_at'=>'datetime'];
    }

    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function checkoutRecorder(): BelongsTo { return $this->belongsTo(User::class, 'checked_out_by'); }
}
