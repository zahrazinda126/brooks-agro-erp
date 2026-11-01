<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRecord;
use App\Services\BrandedReportExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class HrReportController extends Controller
{
    private const REPORTS = [
        'workforce' => 'Workforce Register',
        'attendance' => 'Attendance Register',
        'contracts' => 'Contract Expiry Report',
        'welfare' => 'Welfare Support Report',
        'advances' => 'Salary Advances Report',
        'discipline' => 'Disciplinary & Penalties Report',
        'training' => 'Training Report',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->canViewAllReports() && ! $request->user()->seesOnlyProductionReports(), 403);

        [$start, $end] = $this->period($request);
        $report = $request->query('report', 'workforce');
        abort_unless(isset(self::REPORTS[$report]), 404);

        return view('hr.reports.index', [
            'period' => $start->format('j M Y').' – '.$end->format('j M Y'),
            'metrics' => $this->metrics($start, $end),
            'reports' => self::REPORTS,
            'selectedReport' => $report,
            'headers' => $this->reportData($report, $start, $end)['headers'],
            'rows' => $this->reportData($report, $start, $end)['rows'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canViewAllReports() && ! $request->user()->seesOnlyProductionReports(), 403);
        [$start, $end] = $this->period($request);
        $report = $request->query('report', 'workforce');
        abort_unless(isset(self::REPORTS[$report]), 404);
        $data = $this->reportData($report, $start, $end);
        $filename = str($report.'-'.$start->format('Y-m-d').'-'.$end->format('Y-m-d'))->slug().'.xls';

        return app(BrandedReportExport::class)->download(
            self::REPORTS[$report],
            'Reporting period: '.$start->format('d M Y').' to '.$end->format('d M Y'),
            $data['headers'], $data['rows'], $filename, $request->user()->displayName()
        );
    }

    public function kpis(Request $request): View
    {
        abort_unless(! $request->user()->seesOnlyProductionReports() && ($request->user()->canViewAllReports() || $request->user()->canAccess('kpi-dashboards')), 403);

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $metrics = $this->metrics($start, $end);
        $active = max(1, $metrics['active_staff']);

        return view('hr.reports.kpis', [
            'period' => now()->format('F Y'),
            'metrics' => $metrics,
            'attendanceRate' => round(($metrics['attendance'] / $active) * 100, 1),
        ]);
    }

    private function metrics($start, $end): array
    {
        return [
            'active_staff' => Employee::active()->count(),
            'attendance' => EmployeeRecord::where('type', 'attendance')
                ->whereBetween('recorded_on', [$start, $end])->distinct('employee_id')->count('employee_id'),
            'leave' => EmployeeRecord::where('type', 'leave')
                ->whereBetween('recorded_on', [$start, $end])->count(),
            'training' => EmployeeRecord::where('type', 'training')
                ->whereBetween('recorded_on', [$start, $end])->count(),
            'welfare_amount' => EmployeeRecord::where('type', 'welfare')
                ->whereBetween('recorded_on', [$start, $end])->sum('amount'),
            'pending_penalties' => EmployeeRecord::where('type', 'discipline')->where('status', 'pending')->count(),
            'contracts_expiring' => EmployeeRecord::where('type', 'contracts')
                ->whereBetween('ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
        ];
    }

    private function period(Request $request): array
    {
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        abort_if($to->lt($from), 422, 'The report end date must be on or after the start date.');

        return [$from, $to];
    }

    private function reportData(string $report, $start, $end): array
    {
        if ($report === 'workforce') {
            return ['headers' => ['Staff No.', 'Employee', 'Department', 'Job title', 'Employment type', 'Status'], 'rows' => Employee::with('department')->orderBy('last_name')->get()->map(fn ($e) => [$e->staff_no, $e->fullName(), $e->department?->name, $e->job_title, $e->employment_type, $e->status])->all()];
        }

        $records = EmployeeRecord::with('employee.department')->where('type', $report)
            ->whereBetween('recorded_on', [$start->toDateString(), $end->toDateString()])
            ->latest('recorded_on')->get();
        $headers = $report === 'attendance'
            ? ['Date', 'Staff No.', 'Employee', 'Attendance source']
            : ['Date', 'Staff No.', 'Employee', 'Category / title', 'Amount (UGX)', 'Status', 'Details'];
        $rows = $records->map(function ($record) use ($report) {
            if ($report === 'attendance') {
                return [$record->recorded_on->format('Y-m-d'), $record->employee->staff_no, $record->employee->fullName(), $record->details];
            }
            return [$record->recorded_on->format('Y-m-d'), $record->employee->staff_no, $record->employee->fullName(), $record->title ?: $record->category, $record->amount, $record->status, $record->details];
        })->all();

        return compact('headers', 'rows');
    }
}
