<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'department_id', 'category', 'item', 'unit', 'quantity', 'unit_price',
        'period_start', 'period_end', 'planned_amount', 'notes', 'status',
        'submitted_at', 'recorded_by', 'production_budget_line_id',
        'source_budget_id', 'carryover_type',
        'funding_status', 'funded_amount', 'funding_note',
        'funding_decided_by', 'funding_decided_at',
    ];

    protected function casts(): array
    {
        return ['period_start'=>'date','period_end'=>'date','submitted_at'=>'datetime','funding_decided_at'=>'datetime','quantity'=>'decimal:2','unit_price'=>'decimal:2','planned_amount'=>'decimal:2','funded_amount'=>'decimal:2'];
    }

    public function approvals(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(Approval::class, 'approvable'); }
    public function fundingDecisions(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(BudgetFundingDecision::class); }
    public function fundingDecisionMaker(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class,'funding_decided_by'); }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Department::class); }
    public function sourceBudget(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(self::class, 'source_budget_id'); }
}
