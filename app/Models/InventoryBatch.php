<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A delivery of one item with its own expiry date (first to expire goes out first). */
class InventoryBatch extends Model
{
    /** "Expiring soon" means within this many days (workbook rule). */
    public const EXPIRING_SOON_DAYS = 30;

    protected $fillable = ['inventory_item_id', 'inventory_store_id', 'batch_code', 'expiry_date', 'received_on', 'quantity_received', 'created_by'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'received_on' => 'date', 'quantity_received' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(InventoryStore::class, 'inventory_store_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lt(today());
    }

    public function isExpiringSoon(): bool
    {
        return ! $this->isExpired() && $this->expiry_date->lte(today()->addDays(self::EXPIRING_SOON_DAYS));
    }
}
