<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableLine extends Model
{
    protected $fillable = ['payable_id', 'item_name', 'quantity', 'unit', 'breed_id', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }
}
