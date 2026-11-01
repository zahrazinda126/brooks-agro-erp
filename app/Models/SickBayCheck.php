<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One check on animals in a sick bay: how they are doing and how many left. */
class SickBayCheck extends Model
{
    public const PROGRESS = ['improving' => 'Improving', 'same' => 'No change', 'worse' => 'Getting worse'];

    protected $fillable = ['sick_bay_admission_id', 'checked_on', 'recovered', 'died', 'culled', 'still_sick', 'progress', 'treatment', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['checked_on' => 'date'];
    }

    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
