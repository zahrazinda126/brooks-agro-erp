<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Report centre (the Brooks Farm Hub "Reports" page): every report in the system in one
 * place, by department, opened for a chosen period. It only lists reports; each report
 * still checks access itself, and a report appears here only for people allowed to open it.
 */
class ReportCentreController extends Controller
{
    public const DEPARTMENTS = [
        'whole-farm' => 'Whole farm', 'poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits',
        'dogs' => 'Dogs', 'garden' => 'Garden', 'finance' => 'Finance', 'sales' => 'Sales', 'hr' => 'HR & people',
        'management' => 'Management briefings',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $department = array_key_exists($request->query('department'), self::DEPARTMENTS) ? $request->query('department') : null;
        $periodType = in_array($request->query('period'), ['day', 'week', 'month', 'quarter', 'year'], true) ? $request->query('period') : 'day';
        $date = CarbonImmutable::parse($request->date('date')?->toDateString() ?? now()->toDateString());
        [$from, $to] = $this->range($periodType, $date);
        $dates = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

        $reports = collect(self::reportsFor($user))
            ->filter(fn (array $report) => $department === null || $report['department'] === $department)
            ->map(fn (array $report) => $report + ['url' => route($report['route'], $report['params'] + ($report['dated'] ? $this->periodParams($report['route'], $periodType, $date, $dates) : []))])
            ->groupBy('department');

        return view('reports.centre', [
            'reports' => $reports,
            'departments' => self::DEPARTMENTS,
            'department' => $department,
            'periodType' => $periodType,
            'date' => $date,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * The reports this person sees: those they may open AND that belong to their role's own
     * work, as their menu does. (Every role holds the general "reports" permission, so access
     * alone would show the Production Manager the finance, sales and HR reports.)
     * Roles that oversee the whole farm see every report they may open.
     */
    public static function reportsFor(User $user): array
    {
        $codes = $user->roles->pluck('code')->push($user->role?->code)->filter()->unique();
        $wholeFarm = $user->isSuperuser() || $codes->intersect(['system-admin', 'ceo', 'director', 'personal-assistant', 'pa'])->isNotEmpty();
        $daily = fn (array $r) => $r['route'] === 'daily-reports.index';
        $scopes = [
            'production-manager' => fn (array $r) => in_array($r['department'], ['poultry', 'goats', 'rabbits', 'dogs', 'garden'], true)
                || $r['route'] === 'operations.reports' || $daily($r),
            'hr-manager' => fn (array $r) => $r['department'] === 'hr' || $daily($r),
            'finance-manager' => fn (array $r) => in_array($r['department'], ['finance', 'sales'], true)
                || $r['route'] === 'finance.reports.kpis' || $daily($r),
            'sales-manager' => fn (array $r) => $r['department'] === 'sales' || $daily($r),
            'store-keeper' => fn (array $r) => false,
        ];
        $mine = collect($scopes)->only($codes->all());

        $reports = collect((new self)->catalogue($user))
            ->filter(fn (array $report) => $report['allowed'])
            ->filter(fn (array $report) => $wholeFarm || $mine->isEmpty() || $mine->contains(fn ($inScope) => $inScope($report)));
        // Whoever gets the Financial Reports card finds the Sales Report inside it, so it is not listed twice.
        if ($reports->contains('route', 'finance.reports.index')) {
            $reports = $reports->reject(fn (array $report) => $report['route'] === 'sales.index');
        }

        return $reports->values()->all();
    }

    /** The production reports take a day or a month (period/date/month); the others take from/to. */
    private function periodParams(string $route, string $periodType, CarbonImmutable $date, array $dates): array
    {
        if ($route !== 'operations.reports') {
            return $dates;
        }

        return $periodType === 'day'
            ? ['period' => 'day', 'date' => $date->toDateString()]
            : ['period' => 'month', 'month' => $date->format('Y-m')];
    }

    /** Start and end of the chosen period; a period still running ends today. */
    private function range(string $type, CarbonImmutable $date): array
    {
        [$from, $to] = match ($type) {
            'day' => [$date, $date],
            'week' => [$date->startOfWeek(), $date->endOfWeek()],
            'quarter' => [$date->firstOfQuarter(), $date->lastOfQuarter()],
            'year' => [$date->startOfYear(), $date->endOfYear()],
            default => [$date->startOfMonth(), $date->endOfMonth()],
        };

        return [$from, $to->greaterThan(today()) ? CarbonImmutable::today() : $to];
    }

    /** Each report with the same access rule its own controller applies. */
    private function catalogue(User $user): array
    {
        $codes = $user->roles->pluck('code')->push($user->role?->code)->filter();
        $allReports = $user->canViewAllReports();
        $finance = $allReports || $user->canAccess('finance') || $codes->contains('ceo');
        $poultry = $allReports || $user->canAccess('production') || $user->canAccess('batches') || $user->canAccess('egg-inventory');
        $production = $user->canAccess('production') || $allReports;
        $sales = $allReports || $codes->intersect(['ceo', 'director', 'finance-manager'])->isNotEmpty();
        $director = $codes->contains('director') || $user->isSuperuser();
        $dailyDepartment = $codes->intersect(['ceo', 'director', 'personal-assistant', 'pa', 'production-manager',
            'finance-manager', 'hr-manager', 'sales-manager'])->isNotEmpty();

        $r = fn (string $department, string $title, string $description, string $route, array $params, bool $allowed, bool $dated = true) =>
            compact('department', 'title', 'description', 'route', 'params', 'allowed', 'dated');

        // Goats, rabbits and dogs have the same reports as poultry: the unit report's four sections.
        $units = [];
        $sections = [
            'production' => ['Production', 'Births, milk and other production by group.'],
            'feed' => ['Feeds', 'Feed given by group.'],
            'health' => ['Health & Treatment', 'Vaccination, deworming, spraying and treatments, with the sick bay.'],
            'mortality' => ['Deaths', 'Deaths with their causes.'],
        ];
        foreach (['goats' => 'Goat', 'rabbits' => 'Rabbit', 'dogs' => 'Dog'] as $unit => $name) {
            foreach ($sections as $section => [$label, $about]) {
                $units[] = $r($unit, "$name $label Report", $about, 'operations.units.report',
                    ['unit' => $unit, 'section' => $section], $allReports || $user->canAccessFarmUnit($unit));
            }
        }
        $units[] = $r('garden', 'Garden Report', 'Plots, planting, field activities, inputs, irrigation, harvest, crop losses and produce sales.',
            'operations.units.report', ['unit' => 'garden'], $allReports || $user->canAccessFarmUnit('garden'));

        return [
            // One daily report with every unit summarised (choose Day; any other period opens the month).
            $r('whole-farm', 'Farm Daily Report', 'All units summarised in one report: poultry, goats, rabbits, dogs and garden, with production, feeds, health and deaths.', 'operations.reports', ['view' => 'overall'], $production),
            $r('whole-farm', 'Livestock Report', 'Goats, rabbits and dogs together.', 'operations.reports', ['view' => 'livestock'], $production),
            $r('whole-farm', 'Animal Health Report', 'Treatments, vaccinations and deaths across the units.', 'operations.reports', ['view' => 'health'], $production),
            $r('whole-farm', 'Farm Inputs Report', 'Feeds and other inputs used by the units.', 'operations.reports', ['view' => 'inputs'], $production),
            $r('whole-farm', 'Daily Department Reports', 'Unit reports reviewed by the CEO, combined for each department.', 'daily-reports.index', [], $dailyDepartment, false),
            $r('whole-farm', 'KPI Dashboard', 'Key performance figures for the period.', 'finance.reports.kpis', [], $finance || $user->canAccess('kpi-dashboards')),

            $r('poultry', 'Poultry Egg Production', 'Approved eggs by house and batch, by grade, with tray equivalents.', 'poultry.report.index', ['view' => 'eggs'], $poultry),
            $r('poultry', 'Poultry Feed Report', 'Feed served by house and batch.', 'poultry.report.index', ['view' => 'feed'], $poultry),
            $r('poultry', 'Poultry Mortality Report', 'Deaths and culls with their causes.', 'poultry.report.index', ['view' => 'mortality'], $poultry),
            $r('poultry', 'Poultry Health Report', 'Treatments and health events by flock.', 'poultry.report.index', ['view' => 'health'], $poultry),
            $r('poultry', 'Poultry Vaccination Records', 'Vaccinations given against the schedule.', 'vaccination-schedule.index', ['view' => 'records'], $user->canAccess('vaccination') || $production || $director || $codes->contains('ceo'), false),

            ...$units,

            // Finance has its own Reports Centre (designed 18 Oct) listing every statement and analysis, so it is one card here.
            $r('finance', 'Financial Reports', 'The finance Reports Centre: profit & loss, balance sheet, cash flow, trial balance, three-statement model, sales and revenue, expenditure and unit costs.', 'finance.reports.index', [], $finance),
            // Someone allowed a statement but not the finance Reports Centre still finds that statement here.
            $r('finance', 'Trial Balance', 'Debit and credit balance of every account.', 'finance.reports.statement', ['report' => 'trial-balance'], ! $finance && $user->canAccess('trial-balance')),
            $r('finance', 'Profit & Loss', 'Income less costs for the period.', 'finance.reports.statement', ['report' => 'profit-loss'], ! $finance && $user->canAccess('profit-loss')),
            $r('finance', 'Balance Sheet', 'What the farm owns and owes.', 'finance.reports.statement', ['report' => 'balance-sheet'], ! $finance && $user->canAccess('balance-sheet')),
            $r('finance', 'Cash Flow', 'Money in and out by activity.', 'finance.reports.statement', ['report' => 'cash-flow'], ! $finance && $user->canAccess('cash-flow')),

            // Sales and Revenue Analysis were moved out of Reports into the Sales module (18 Oct), so only the report is listed.
            // The finance Reports Centre (Financial Reports card) lists the Sales Report, so here it is only for people who cannot open that page.
            $r('sales', 'Sales Report', 'Sales by product, customer and day.', 'sales.index', ['screen' => 'reports'], $sales),

            $r('hr', 'HR Reports', 'Staff, attendance, leave, payroll and welfare.', 'hr.reports.index', [], $allReports),
            $r('hr', 'HR KPI Dashboard', 'People figures for the period.', 'hr.kpis.index', [], $allReports || $user->canAccess('kpi-dashboards')),

            $r('management', "CEO's Daily Reports", "The CEO's daily reports to the Director.", 'ceo.reports.index', [], $codes->contains('ceo') || $director, false),
            $r('management', "PA's Daily Reports", "The Personal Assistant's daily reports to the Director.", 'pa.reports.index', [], $user->isPersonalAssistant() || $director, false),
        ];
    }
}
