<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggCollectionLine extends Model
{
    protected $fillable = ['egg_collection_id', 'egg_grade_id',
                           'trays', 'loose_eggs', 'total_eggs'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(EggCollection::class, 'egg_collection_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(EggGrade::class, 'egg_grade_id');
    }
}
