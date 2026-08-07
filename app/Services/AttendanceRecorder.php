<?php

namespace App\Services;

use App\Models\EmployeeRecord;
use App\Models\User;

class AttendanceRecorder
{
    /**
     * A login or saved system record is evidence the employee was working
     * that day. One row per employee per day keeps repeated activity from
     * inflating attendance, while preserving manually entered attendance.
     */
    public static function markPresent(User $user, string $source): void
    {
        if (! $user->employee_id) {
            return;
        }

        EmployeeRecord::firstOrCreate(
            [
                'employee_id' => $user->employee_id,
                'type' => 'attendance',
                'recorded_on' => now()->toDateString(),
            ],
            [
                'status' => 'recorded',
                'details' => "Automatically marked present from {$source}.",
                'created_by' => $user->id,
            ]
        );
    }
}
