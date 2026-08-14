<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = ['inventory_item_id', 'financial_transaction_id', 'movement_date', 'movement_type', 'location', 'quantity', 'unit_cost', 'total_cost', 'supplier_or_recipient', 'reference', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
