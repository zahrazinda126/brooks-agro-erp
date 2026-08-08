<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EmployeeRecord extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'category', 'title', 'provider', 'recorded_on', 'ends_on', 'probation_ends_on',
        'notice_period_days', 'signed_on', 'status', 'amount', 'score',
        'is_payroll_deduction', 'payroll_deduction_status', 'details',
        'created_by', 'approved_by', 'approved_at', 'payroll_run_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'ends_on' => 'date',
            'probation_ends_on' => 'date',
            'signed_on' => 'date',
            'amount' => 'decimal:2',
            'score' => 'decimal:2',
            'is_payroll_deduction' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
