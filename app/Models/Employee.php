<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (Employee $employee) {
            if (! $employee->wasChanged('staff_no') || ! $employee->staff_no) return;
            $user = User::where('employee_id', $employee->id)->first();
            if (! $user || $user->username === $employee->staff_no) return;
            $before = $user->username;
            $user->save(); // the User hook sets the username to the new staff number
            AuditLog::record('login_renamed_to_staff_number', $user, "Login '{$before}' renamed to '{$user->username}' with the staff number.", ['username' => $before], ['username' => $user->username], 'hr');
        });
    }

    protected $fillable = [
        'staff_no', 'first_name', 'last_name', 'other_names',
        'phone', 'alt_phone', 'email', 'national_id', 'gender', 'on_payroll',
        'date_of_birth', 'address', 'next_of_kin_name', 'next_of_kin_phone',
        'department_id', 'role_id', 'job_title', 'employment_type',
        'hire_date', 'expected_end_date', 'placement_from', 'end_date', 'status',
        'base_salary', 'pay_frequency', 'payment_method',
        'bank_name', 'bank_account', 'mobile_money_number',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date'     => 'date',
            'expected_end_date' => 'date',
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
