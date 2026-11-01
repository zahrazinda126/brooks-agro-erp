<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestLine extends Model
{
    protected $fillable = ['inventory_request_id', 'inventory_item_id', 'quantity_requested', 'quantity_issued', 'quantity_received'];

    protected function casts(): array
    {
        return ['quantity_requested' => 'decimal:2', 'quantity_issued' => 'decimal:2', 'quantity_received' => 'decimal:2'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class, 'inventory_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
