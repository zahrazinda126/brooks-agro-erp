<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetFundingDecision extends Model
{
    protected $fillable = ['budget_id', 'approval_id', 'approval_step_id', 'decision', 'funded_amount', 'note', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['funded_amount'=>'decimal:2', 'decided_at'=>'datetime'];
    }

    public function budget(): BelongsTo { return $this->belongsTo(Budget::class); }
    public function approval(): BelongsTo { return $this->belongsTo(Approval::class); }
    public function step(): BelongsTo { return $this->belongsTo(ApprovalStep::class, 'approval_step_id'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
