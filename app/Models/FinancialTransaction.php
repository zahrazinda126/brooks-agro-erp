<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FinancialTransaction extends Model { protected $fillable=['transaction_date','type','account','counterparty','amount','payment_method','reference','description','recorded_by']; protected function casts(): array { return ['transaction_date'=>'date','amount'=>'decimal:2']; } public function recorder(): BelongsTo { return $this->belongsTo(User::class,'recorded_by'); } }
