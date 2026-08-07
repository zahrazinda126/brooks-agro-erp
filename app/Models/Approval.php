<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Approval extends Model
{
    protected $fillable = [
        'approvable_type', 'approvable_id', 'module_key', 'department_id',
        'submitted_by', 'submitted_at', 'submission_note', 'decided_by',
        'decided_at', 'decision_note', 'status', 'level', 'required_levels', 'due_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'decided_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function approvable(): MorphTo { return $this->morphTo(); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
    public function steps(): HasMany { return $this->hasMany(ApprovalStep::class)->orderBy('stage'); }
    public function currentStep(): ?ApprovalStep { return $this->steps->firstWhere('status', 'pending'); }

    public function label(): string
    {
        if ($this->approvable instanceof Budget) {
            return 'Weekly budget for '.$this->approvable->period_start->format('d M Y');
        }
        if ($this->approvable instanceof Purchase) return 'Purchase: '.$this->approvable->item.' from '.$this->approvable->supplier_name;

        return ucfirst(str_replace('-', ' ', $this->module_key ?: 'approval'))." #{$this->approvable_id}";
    }

    public function amount(): ?string
    {
        if ($this->approvable instanceof Purchase) return number_format((float)$this->approvable->total_cost,2);
        if (! $this->approvable instanceof Budget) return null;

        return number_format((float) Budget::where('period_start', $this->approvable->period_start)
            ->sum('planned_amount'), 2);
    }
}
