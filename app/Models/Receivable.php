<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receivable extends Model
{
    protected $fillable = ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'description', 'amount', 'amount_paid', 'status', 'recorded_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'amount_paid' => 'decimal:2'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
