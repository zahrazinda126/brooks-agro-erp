<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySalesBalance extends Model
{
    protected $fillable = [
        'business_date', 'sales_person_id', 'opening_balance', 'cash_received',
        'amount_transferred', 'destination', 'reference', 'receipt_path', 'notes', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_balance' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'amount_transferred' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
