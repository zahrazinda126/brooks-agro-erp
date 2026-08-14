<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    protected $fillable = ['transaction_date', 'type', 'transaction_category', 'account_group', 'entry_account', 'sub_account', 'department_id', 'cost_centre', 'account', 'counterparty', 'amount', 'quantity', 'unit_price', 'payment_method', 'reference', 'description', 'recorded_by'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'amount' => 'decimal:2', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
