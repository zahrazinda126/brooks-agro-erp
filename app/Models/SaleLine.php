<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLine extends Model
{
    protected $fillable = ['sale_id','product_id','category','description','unit','quantity','unit_price','line_total'];

    protected function casts(): array
    {
        return ['quantity'=>'decimal:2','unit_price'=>'decimal:2','line_total'=>'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class, 'product_id');
    }
}
