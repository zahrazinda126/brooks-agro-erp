<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeRecord;
use Carbon\Carbon;

class WorkerPerformanceService
{
    /**
     * A worker's score is system-derived: 70% presence and 30% recorded
     * work activity. HR can see it, but cannot type or alter the score.
     */
    public static function forPeriod(Carbon $from, Carbon $to): array
    {
        $expectedDays = max(1, self::workingDays($from, $to));
        $attendance = EmployeeRecord::where('type', 'attendance')
            ->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('employee_id, count(distinct recorded_on) as days')
            ->groupBy('employee_id')->pluck('days', 'employee_id');
        $activity = AuditLog::query()
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->whereNotNull('users.employee_id')
            ->where('audit_logs.event', 'created')
            ->whereBetween('audit_logs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('users.employee_id, count(distinct date(audit_logs.created_at)) as days')
            ->groupBy('users.employee_id')->pluck('days', 'users.employee_id');

        return Employee::active()->with('department')->orderBy('last_name')->get()->map(function (Employee $employee) use ($attendance, $activity, $expectedDays) {
            $present = (int) ($attendance[$employee->id] ?? 0);
            $activityDays = (int) ($activity[$employee->id] ?? 0);
            $attendanceScore = min(100, ($present / $expectedDays) * 100);
            $activityScore = $present ? min(100, ($activityDays / $present) * 100) : 0;

            return [
                'employee' => $employee,
                'present_days' => $present,
                'activity_days' => $activityDays,
                'score' => round(($attendanceScore * .7) + ($activityScore * .3), 1),
            ];
        })->all();
    }

    public static function workingDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if (! $date->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }
}
