<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'staff_no', 'first_name', 'last_name', 'other_names',
        'phone', 'alt_phone', 'email', 'national_id', 'gender',
        'date_of_birth', 'address', 'next_of_kin_name', 'next_of_kin_phone',
        'department_id', 'role_id', 'job_title', 'employment_type',
        'hire_date', 'end_date', 'status',
        'base_salary', 'pay_frequency', 'payment_method',
        'bank_name', 'bank_account', 'mobile_money_number',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date'     => 'date',
            'end_date'      => 'date',
            'base_salary'   => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** The login account, if one has been issued. Many staff have none. */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function hasAccount(): bool
    {
        return $this->user()->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}