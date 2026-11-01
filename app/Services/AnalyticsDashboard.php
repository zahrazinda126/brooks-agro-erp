<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The analytics dashboard shown at the top of each manager's dashboard:
 * filters (period, month) · three KPI tiles compared with last year · a monthly trend
 * (this year vs last year, with a % strip) · a status ring chart · two Top-5 bar charts.
 *
 * Each role gets its own content, and every widget is checked against what that person
 * is allowed to see — a widget they may not see is simply left out.
 */
class AnalyticsDashboard
{
    /* ===================== periods ===================== */

    /** The period chosen in the filters: rolling 12 months (default) or a calendar year; optionally one month. */
    public function period(Request $request): array
    {
        $year = $request->query('dash_year');
        $year = is_string($year) && preg_match('/^\d{4}$/', $year) ? (int) $year : null;
        $end = $year ? Carbon::create($year, 12, 31)->endOfDay() : now()->endOfMonth();
        if ($end->isFuture() && $year) $end = now()->endOfMonth();
        $start = $year ? Carbon::create($year, 1, 1) : now()->startOfMonth()->subMonths(11);
        $months = collect();
        for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) $months->push($m->copy());

        $month = $request->query('dash_month');
        $month = is_string($month) && $months->contains(fn ($m) => $m->format('Y-m') === $month) ? $month : null;

        // Quarters in the period: Q1 Jan–Mar, Q2 Apr–Jun, Q3 Jul–Sep, Q4 Oct–Dec ("2026-Q3").
        $quarters = $months->map(fn (Carbon $m) => $m->year.'-Q'.$m->quarter)->unique()->values();
        $quarter = $request->query('dash_quarter');
        $quarter = ! $month && is_string($quarter) && $quarters->contains($quarter) ? $quarter : null;
        $quarterStart = $quarter ? Carbon::create((int) substr($quarter, 0, 4), ((int) substr($quarter, -1) - 1) * 3 + 1, 1) : null;

        $kpiStart = $month ? Carbon::parse($month.'-01') : ($quarterStart ? $quarterStart->copy() : $start->copy()->startOfMonth());
        $kpiEnd = $month ? Carbon::parse($month.'-01')->endOfMonth() : ($quarterStart ? $quarterStart->copy()->addMonths(2)->endOfMonth() : $end->copy());

        return [
            'year' => $year, 'month' => $month, 'months' => $months, 'quarter' => $quarter,
            'quarters' => $quarters->map(fn ($q) => ['key' => $q, 'label' => self::quarterLabel($q)])->all(),
            'start' => $start->copy()->startOfMonth(), 'end' => $end,
            'kpiStart' => $kpiStart, 'kpiEnd' => $kpiEnd,
            'prevStart' => $kpiStart->copy()->subYear(), 'prevEnd' => $kpiEnd->copy()->subYear(),
            'label' => $month ? Carbon::parse($month.'-01')->format('F Y') : ($quarter ? self::quarterLabel($quarter) : ($year ? 'Year '.$year : 'Rolling 12 months to '.min($end, now())->format('d M Y'))),
            'years' => collect(range(now()->year, now()->year - 3)),
        ];
    }

    /** "Q3 2026 (Jul–Sep)". */
    public static function quarterLabel(string $key): string
    {
        $q = (int) substr($key, -1);
        return 'Q'.$q.' '.substr($key, 0, 4).' ('.['Jan–Mar', 'Apr–Jun', 'Jul–Sep', 'Oct–Dec'][$q - 1].')';
    }

    /* ===================== dashboards per role ===================== */

    /** The dashboard for this person, or null when their role has none. */
    public function for(User $user, array $p): ?array
    {
        $codes = $user->roles->pluck('code')->push($user->role?->code)->filter();
        if ($user->isPersonalAssistant()) $codes->push('personal-assistant');
        $role = collect(['ceo', 'director', 'finance-manager', 'sales-manager', 'production-manager', 'hr-manager', 'personal-assistant'])
            ->first(fn ($r) => $codes->contains($r));
        if (! $role) return null;

        $can = [
            'money' => $user->canViewAllReports() || $user->canAccess('transactions') || $user->canAccess('finance') || in_array($role, ['ceo', 'director', 'finance-manager'], true),
            'sales' => $user->canViewAllReports() || $user->canAccess('sales') || in_array($role, ['ceo', 'director', 'sales-manager'], true),
            'production' => $user->canViewAllReports() || $user->canAccess('batches') || $user->canAccess('production') || $role === 'production-manager',
            'people' => $user->canAccess('hr') || $user->canAccess('payroll') || $role === 'hr-manager',
            'approvals' => $user->canAccess('approvals') || in_array($role, ['ceo', 'director', 'personal-assistant'], true),
        ];

        $dash = match ($role) {
            'ceo' => [
                'title' => 'Business performance',
                'kpis' => [$this->kpiSales($p), $this->kpiProfit($p), $this->kpiNewCustomers($p)],
                'trend' => $this->trendSales($p, 'Profit margin %', fn ($m) => $this->marginPct($m)),
                'ring' => $this->ringApprovals($p, 'Requests & approvals'),
                'topA' => $this->topSalesByProduct($p),
                'topB' => $this->topCustomers($p),
                'needs' => ['kpis' => ['sales', 'money', 'sales'], 'trend' => 'sales', 'ring' => 'approvals', 'topA' => 'sales', 'topB' => 'sales'],
            ],
            'director' => [
                'title' => 'Oversight',
                'kpis' => [$this->kpiSales($p), $this->kpiSpending($p), $this->kpiProfit($p)],
                'trend' => $this->trendSpending($p),
                'ring' => $this->ringApprovals($p, 'Final approvals'),
                'topA' => $this->topSpending($p),
                'topB' => $this->topSalesByProduct($p),
                'needs' => ['kpis' => ['sales', 'money', 'money'], 'trend' => 'money', 'ring' => 'approvals', 'topA' => 'money', 'topB' => 'sales'],
            ],
            'finance-manager' => [
                'title' => 'Money in and out',
                'kpis' => [$this->kpiMoneyIn($p), $this->kpiMoneyOut($p), $this->kpiCashPosition()],
                'trend' => $this->trendMoneyIn($p),
                'ring' => $this->ringBudgetFunding($p),
                'topA' => $this->topSpending($p),
                'topB' => $this->topSuppliers($p),
                'needs' => ['kpis' => ['money', 'money', 'money'], 'trend' => 'money', 'ring' => 'money', 'topA' => 'money', 'topB' => 'money'],
            ],
            'sales-manager' => [
                'title' => 'Sales',
                'kpis' => [$this->kpiSales($p), $this->kpiSalesCount($p), $this->kpiNewCustomers($p)],
                'trend' => $this->trendSales($p, 'Change vs last year %', fn ($m) => $m['last'] > 0 ? round(($m['this'] - $m['last']) / $m['last'] * 100, 1) : null),
                'ring' => $this->ringPaymentMethods($p),
                'topA' => $this->topSalesByProduct($p),
                'topB' => $this->topCustomers($p),
                'needs' => ['kpis' => ['sales', 'sales', 'sales'], 'trend' => 'sales', 'ring' => 'sales', 'topA' => 'sales', 'topB' => 'sales'],
            ],
            'production-manager' => [
                'title' => 'Production',
                'kpis' => [$this->kpiEggs($p), $this->kpiLayingRate($p), $this->kpiDeaths($p)],
                'trend' => $this->trendEggs($p),
                'ring' => $this->ringDeathCauses($p),
                'topA' => $this->topHousesByEggs($p),
                'topB' => $this->topFeeds($p),
                'needs' => ['kpis' => ['production', 'production', 'production'], 'trend' => 'production', 'ring' => 'production', 'topA' => 'production', 'topB' => 'production'],
            ],
            'hr-manager' => [
                'title' => 'People',
                'kpis' => [$this->kpiStaff(), $this->kpiPayroll($p), $this->kpiNewHires($p)],
                'trend' => $this->trendPayroll($p),
                'ring' => $this->ringStaffTypes(),
                'topA' => $this->topDepartmentsByStaff(),
                'topB' => $this->topDepartmentsByPayroll($p),
                'needs' => ['kpis' => ['people', 'people', 'people'], 'trend' => 'people', 'ring' => 'people', 'topA' => 'people', 'topB' => 'people'],
            ],
            'personal-assistant' => [
                // Built from the Director Follow-up board: directives and approved-money follow-up.
                'title' => 'Director follow-up',
                'kpis' => [$this->kpiOpenDirectives(), $this->kpiFundsTracked($p), $this->kpiFundsAccounted($p)],
                'trend' => $this->trendFundsTracked($p),
                'ring' => $this->ringDirectives($p),
                'topA' => $this->topDirectiveTeams(),
                'topB' => $this->topFundsAwaiting(),
                'needs' => ['kpis' => ['approvals', 'approvals', 'approvals'], 'trend' => 'approvals', 'ring' => 'approvals', 'topA' => 'approvals', 'topB' => 'approvals'],
            ],
        };

        // Leave out anything this person may not see.
        $needs = $dash['needs'];
        $dash['kpis'] = collect($dash['kpis'])->filter(fn ($k, $i) => $can[$needs['kpis'][$i]])->values()->all();
        foreach (['trend', 'ring', 'topA', 'topB'] as $w) if (! $can[$needs[$w]]) $dash[$w] = null;
        unset($dash['needs']);
        $dash['role'] = $role;

        return $dash;
    }

    /* ===================== building blocks ===================== */

    private function salesQuery()
    {
        return DB::table('sales')->where('status', '!=', 'void')->where('approval_status', 'approved')->where('is_staff_consumption', false);
    }

    /** Real money movements: not transfers, not reversed, not reversal entries. */
    private function money(string $type)
    {
        return DB::table('financial_transactions')->where('type', $type)->whereNull('reversed_at')->whereNull('reversal_of_id');
    }

    private function between($q, string $column, Carbon $from, Carbon $to)
    {
        return $q->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString());
    }

    /** Sum per month (Y-m => value) over the whole period and the same months a year earlier. */
    private function monthly(callable $query, string $dateColumn, string $sumExpression, array $p): array
    {
        $collect = function (Carbon $from, Carbon $to) use ($query, $dateColumn, $sumExpression) {
            return $this->between($query(), $dateColumn, $from, $to)->get([$dateColumn.' as d', DB::raw($sumExpression.' as v')])
                ->groupBy(fn ($r) => substr((string) $r->d, 0, 7))->map(fn ($rows) => (float) $rows->sum('v'));
        };
        $this_ = $collect($p['start'], $p['end']);
        $last = $collect($p['start']->copy()->subYear(), $p['end']->copy()->subYear());

        return $p['months']->map(fn (Carbon $m) => [
            'key' => $m->format('Y-m'), 'label' => $m->format('M'),
            'this' => (float) ($this_[$m->format('Y-m')] ?? 0), 'last' => (float) ($last[$m->copy()->subYear()->format('Y-m')] ?? 0),
        ])->all();
    }

    private function kpi(string $label, float $now, ?float $before, string $format = 'money', bool $upIsGood = true, ?string $info = null, ?string $note = null): array
    {
        $change = $before !== null && $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
        return ['label' => $label, 'value' => $now, 'format' => $format, 'change' => $change, 'good' => $change === null ? null : (($change >= 0) === $upIsGood), 'info' => $info, 'note' => $note];
    }

    private function sumIn($q, string $col, mixed $sum, Carbon $from, Carbon $to): float
    {
        return (float) $this->between($q, $col, $from, $to)->sum($sum);
    }

    /* ===================== KPIs ===================== */

    private function kpiSales(array $p): array
    {
        return $this->kpi('Sales', $this->sumIn($this->salesQuery(), 'sale_date', 'total', $p['kpiStart'], $p['kpiEnd']),
            $this->sumIn($this->salesQuery(), 'sale_date', 'total', $p['prevStart'], $p['prevEnd']), 'money', true, 'Approved sales (excluding farm use), compared with the same period last year.');
    }

    private function spending(Carbon $from, Carbon $to): float
    {
        return (float) $this->between($this->money('money_paid'), 'transaction_date', $from, $to)->whereIn('transaction_category', ['purchase', 'staff_payment'])->sum('amount');
    }

    private function kpiSpending(array $p): array
    {
        return $this->kpi('Spending', $this->spending($p['kpiStart'], $p['kpiEnd']), $this->spending($p['prevStart'], $p['prevEnd']), 'money', false, 'Money paid out for purchases, expenses and staff.');
    }

    private function kpiProfit(array $p): array
    {
        $profit = fn ($a, $b) => $this->sumIn($this->salesQuery(), 'sale_date', 'total', $a, $b) - $this->spending($a, $b);
        return $this->kpi('Profit', $profit($p['kpiStart'], $p['kpiEnd']), $profit($p['prevStart'], $p['prevEnd']), 'money', true, 'Approved sales minus money paid out for purchases, expenses and staff.');
    }

    private function kpiNewCustomers(array $p): array
    {
        $count = fn ($a, $b) => (float) $this->between(DB::table('sales_customers'), 'created_at', $a, $b)->count();
        return $this->kpi('New customers', $count($p['kpiStart'], $p['kpiEnd']), $count($p['prevStart'], $p['prevEnd']), 'count', true, 'Customers added to the customer list.');
    }

    private function kpiSalesCount(array $p): array
    {
        $count = fn ($a, $b) => (float) $this->between($this->salesQuery(), 'sale_date', $a, $b)->count();
        return $this->kpi('Number of sales', $count($p['kpiStart'], $p['kpiEnd']), $count($p['prevStart'], $p['prevEnd']), 'count');
    }

    private function kpiMoneyIn(array $p): array
    {
        $in = fn ($a, $b) => (float) $this->between($this->money('money_received'), 'transaction_date', $a, $b)->sum('amount');
        return $this->kpi('Money in', $in($p['kpiStart'], $p['kpiEnd']), $in($p['prevStart'], $p['prevEnd']), 'money', true, 'All money received into farm accounts.');
    }

    private function kpiMoneyOut(array $p): array
    {
        $out = fn ($a, $b) => (float) $this->between($this->money('money_paid'), 'transaction_date', $a, $b)->sum('amount');
        return $this->kpi('Money out', $out($p['kpiStart'], $p['kpiEnd']), $out($p['prevStart'], $p['prevEnd']), 'money', false, 'All money paid out of farm accounts.');
    }

    private function kpiCashPosition(): array
    {
        $balance = (float) $this->money('money_received')->sum('amount') - (float) $this->money('money_paid')->sum('amount');
        return $this->kpi('Cash & bank position', $balance, null, 'money', true, 'All money received minus all money paid out, to date.');
    }

    private function kpiEggs(array $p): array
    {
        $eggs = fn ($a, $b) => $this->sumIn(DB::table('egg_collections')->where('status', 'approved'), 'collected_on', 'total_eggs', $a, $b);
        return $this->kpi('Eggs collected', $eggs($p['kpiStart'], $p['kpiEnd']), $eggs($p['prevStart'], $p['prevEnd']), 'count', true, 'Approved egg collections.');
    }

    private function kpiLayingRate(array $p): array
    {
        $rate = fn ($a, $b) => $this->layingRate($a, $b);
        return $this->kpi('Laying rate', (float) ($rate($p['kpiStart'], $p['kpiEnd']) ?? 0), $rate($p['prevStart'], $p['prevEnd']), 'pct', true, 'Eggs collected per bird per day.');
    }

    private function kpiDeaths(array $p): array
    {
        $deaths = fn ($a, $b) => $this->sumIn(DB::table('mortality_records'), 'occurred_on', DB::raw('quantity_died + quantity_culled'), $a, $b)
            + $this->sumIn(DB::table('livestock_mortality_records'), 'occurred_on', 'quantity', $a, $b);
        return $this->kpi('Deaths', $deaths($p['kpiStart'], $p['kpiEnd']), $deaths($p['prevStart'], $p['prevEnd']), 'count', false, 'Birds and animals that died or were culled.');
    }

    private function kpiStaff(): array
    {
        return $this->kpi('Active staff', (float) DB::table('employees')->whereNull('deleted_at')->where('status', 'active')->count(), null, 'count', true, 'Everyone currently employed, on payroll or not.');
    }

    private function payroll(Carbon $from, Carbon $to): float
    {
        return (float) $this->between(DB::table('payroll_lines')->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_lines.payroll_run_id'), 'payroll_runs.period_end', $from, $to)->sum('payroll_lines.gross_pay');
    }

    private function kpiPayroll(array $p): array
    {
        return $this->kpi('Payroll cost', $this->payroll($p['kpiStart'], $p['kpiEnd']), $this->payroll($p['prevStart'], $p['prevEnd']), 'money', false, 'Gross pay in payroll runs.');
    }

    private function kpiNewHires(array $p): array
    {
        $hires = fn ($a, $b) => (float) $this->between(DB::table('employees')->whereNull('deleted_at'), 'hire_date', $a, $b)->count();
        return $this->kpi('New hires', $hires($p['kpiStart'], $p['kpiEnd']), $hires($p['prevStart'], $p['prevEnd']), 'count');
    }

    private function kpiApprovedMoney(array $p): array
    {
        $approved = fn ($a, $b) => (float) $this->between(DB::table('budgets')->whereIn('funding_status', ['funded', 'partly_funded']), 'period_start', $a, $b)->sum('funded_amount');
        return $this->kpi('Approved budget money', $approved($p['kpiStart'], $p['kpiEnd']), $approved($p['prevStart'], $p['prevEnd']), 'money', true, 'Budget lines funded in the period.');
    }

    private function kpiConstructionSpend(array $p): array
    {
        $spent = fn ($a, $b) => (float) $this->between($this->money('money_paid')->whereNotNull('construction_project_id'), 'transaction_date', $a, $b)->sum('amount');
        return $this->kpi('Construction spending', $spent($p['kpiStart'], $p['kpiEnd']), $spent($p['prevStart'], $p['prevEnd']), 'money', false, 'Payments Finance linked to construction projects.');
    }

    private const CLOSED_DIRECTIVES = ['done', 'completed', 'cancelled'];

    private function kpiOpenDirectives(): array
    {
        $open = DB::table('executive_directives')->whereNotIn('status', self::CLOSED_DIRECTIVES);
        $overdue = (clone $open)->whereNotNull('due_on')->whereDate('due_on', '<', today())->count();
        return $this->kpi('Open directives', (float) $open->count(), null, 'count', false, 'Directives not yet completed or cancelled.', $overdue.' overdue');
    }

    private function fundsTracked(Carbon $from, Carbon $to): float
    {
        return (float) $this->between(DB::table('approved_fund_follow_ups'), 'created_at', $from, $to)->sum('approved_amount');
    }

    private function kpiFundsTracked(array $p): array
    {
        $awaiting = $this->between(DB::table('approved_fund_follow_ups'), 'created_at', $p['kpiStart'], $p['kpiEnd'])->where('status', '!=', 'verified')->count();
        return $this->kpi('Approved funds tracked', $this->fundsTracked($p['kpiStart'], $p['kpiEnd']), $this->fundsTracked($p['prevStart'], $p['prevEnd']), 'money', true,
            'Approved money being followed up to confirm it was used as intended.', $awaiting.' awaiting verification');
    }

    private function kpiFundsAccounted(array $p): array
    {
        $rows = $this->between(DB::table('approved_fund_follow_ups'), 'created_at', $p['kpiStart'], $p['kpiEnd']);
        $approved = (float) (clone $rows)->sum('approved_amount');
        $accounted = (float) (clone $rows)->sum('amount_accounted_for');
        return $this->kpi('Funds accounted for', $approved > 0 ? round($accounted / $approved * 100, 1) : 0, null, 'pct', true,
            'Share of the approved money with evidence of how it was used.', 'UGX '.number_format($accounted).' accounted');
    }

    /* ===================== trends ===================== */

    private function trendSales(array $p, string $pctLabel, callable $pct): array
    {
        $months = $this->monthly(fn () => $this->salesQuery(), 'sale_date', 'total', $p);
        $spend = collect($this->monthly(fn () => $this->money('money_paid')->whereIn('transaction_category', ['purchase', 'staff_payment']), 'transaction_date', 'amount', $p))->keyBy('key');
        foreach ($months as &$m) { $m['spend'] = $spend[$m['key']]['this'] ?? 0; $m['pct'] = $pct($m); }
        return ['title' => 'Sales monthly trend', 'format' => 'money', 'months' => $months, 'pctLabel' => $pctLabel, 'info' => 'Approved sales each month, this year against the same month last year.'];
    }

    private function marginPct(array $m): ?float
    {
        return $m['this'] > 0 ? round(($m['this'] - $m['spend']) / $m['this'] * 100, 1) : null;
    }

    private function trendSpending(array $p): array
    {
        $months = $this->monthly(fn () => $this->money('money_paid')->whereIn('transaction_category', ['purchase', 'staff_payment']), 'transaction_date', 'amount', $p);
        $sales = collect($this->monthly(fn () => $this->salesQuery(), 'sale_date', 'total', $p))->keyBy('key');
        foreach ($months as &$m) { $s = $sales[$m['key']]['this'] ?? 0; $m['pct'] = $s > 0 ? round($m['this'] / $s * 100, 1) : null; }
        return ['title' => 'Spending monthly trend', 'format' => 'money', 'months' => $months, 'pctLabel' => 'Spending as % of sales', 'info' => 'Money paid out each month, this year against last year.'];
    }

    private function trendMoneyIn(array $p): array
    {
        $months = $this->monthly(fn () => $this->money('money_received'), 'transaction_date', 'amount', $p);
        $out = collect($this->monthly(fn () => $this->money('money_paid'), 'transaction_date', 'amount', $p))->keyBy('key');
        foreach ($months as &$m) { $o = $out[$m['key']]['this'] ?? 0; $m['pct'] = $m['this'] > 0 ? round($o / $m['this'] * 100, 1) : null; }
        return ['title' => 'Money in, monthly', 'format' => 'money', 'months' => $months, 'pctLabel' => 'Money out as % of money in', 'info' => 'Money received each month, this year against last year.'];
    }

    private function trendEggs(array $p): array
    {
        $months = $this->monthly(fn () => DB::table('egg_collections')->where('status', 'approved'), 'collected_on', 'total_eggs', $p);
        foreach ($months as &$m) { $start = Carbon::parse($m['key'].'-01'); $m['pct'] = $this->layingRate($start, $start->copy()->endOfMonth()); }
        return ['title' => 'Eggs collected, monthly', 'format' => 'count', 'months' => $months, 'pctLabel' => 'Laying rate %', 'info' => 'Approved egg collections each month, this year against last year.'];
    }

    private function trendPayroll(array $p): array
    {
        $months = $p['months']->map(fn (Carbon $m) => ['key' => $m->format('Y-m'), 'label' => $m->format('M'),
            'this' => $this->payroll($m->copy()->startOfMonth(), $m->copy()->endOfMonth()),
            'last' => $this->payroll($m->copy()->subYear()->startOfMonth(), $m->copy()->subYear()->endOfMonth()), 'pct' => null])->all();
        return ['title' => 'Payroll cost, monthly', 'format' => 'money', 'months' => $months, 'pctLabel' => null, 'info' => 'Gross pay in payroll runs, this year against last year.'];
    }

    /** Eggs per bird per day (%), using the birds in stock at the end of each day of the period. */
    private function layingRate(Carbon $from, Carbon $to): ?float
    {
        $eggs = $this->sumIn(DB::table('egg_collections')->where('status', 'approved'), 'collected_on', 'total_eggs', $from, $to);
        if ($eggs <= 0) return null;
        $stock = app(SellableStock::class);
        $birdDays = 0;
        for ($d = $from->copy(); $d->lte($to) && ! $d->isFuture(); $d->addDays(7)) {
            $birds = (float) $stock->onHand($d->copy(), ['unit' => 'poultry'])->where('measure', 'birds')->sum('quantity');
            $birdDays += $birds * min(7, $d->diffInDays($to) + 1);
        }
        return $birdDays > 0 ? round($eggs / $birdDays * 100, 1) : null;
    }

    private function trendFundsTracked(array $p): array
    {
        $months = $this->monthly(fn () => DB::table('approved_fund_follow_ups'), 'created_at', 'approved_amount', $p);
        $accounted = collect($this->monthly(fn () => DB::table('approved_fund_follow_ups'), 'created_at', 'amount_accounted_for', $p))->keyBy('key');
        foreach ($months as &$m) { $a = $accounted[$m['key']]['this'] ?? 0; $m['pct'] = $m['this'] > 0 ? round($a / $m['this'] * 100, 1) : null; }
        return ['title' => 'Approved funds tracked, monthly', 'format' => 'money', 'months' => $months, 'pctLabel' => 'Accounted for %', 'info' => 'Approved money put under follow-up each month, this year against last year.'];
    }

    /* ===================== rings ===================== */

    private function ring(string $title, Collection $parts, array $colors, ?string $info = null): array
    {
        $total = (float) $parts->sum();
        return ['title' => $title, 'total' => $total, 'info' => $info, 'parts' => $parts->map(fn ($v, $label) => ['label' => $label, 'value' => (float) $v,
            'share' => $total > 0 ? round($v / $total * 100) : 0, 'color' => $colors[$label] ?? 'var(--chart-other)'])->values()->all()];
    }

    private function ringApprovals(array $p, string $title): array
    {
        $rows = $this->between(DB::table('approvals'), 'submitted_at', $p['kpiStart'], $p['kpiEnd'])->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $labels = ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
        $parts = collect($labels)->mapWithKeys(fn ($l, $k) => [$l => (float) ($rows[$k] ?? 0)])->filter();
        return $this->ring($title, $parts, ['Approved' => 'var(--status-good, #3c9258)', 'Pending' => 'var(--status-warning, #e6a45d)', 'Rejected' => 'var(--status-critical, #b3261e)', 'Cancelled' => 'var(--chart-other)'], 'Requests submitted in the period, by decision.');
    }

    private function ringBudgetFunding(array $p): array
    {
        $rows = $this->between(DB::table('budgets'), 'period_start', $p['kpiStart'], $p['kpiEnd'])->select('funding_status', DB::raw('sum(planned_amount) as v'))->groupBy('funding_status')->pluck('v', 'funding_status');
        $labels = ['funded' => 'Funded', 'partly_funded' => 'Partly funded', 'pending' => 'Waiting', 'unfunded' => 'Not funded'];
        $parts = collect($labels)->mapWithKeys(fn ($l, $k) => [$l => (float) ($rows[$k] ?? 0)])->filter();
        return $this->ring('Budget funding', $parts, ['Funded' => 'var(--chart-4)', 'Partly funded' => 'var(--chart-3)', 'Waiting' => 'var(--chart-2)', 'Not funded' => 'var(--chart-1)'], 'Budget requested in the period (UGX), by funding decision.');
    }

    private function ringPaymentMethods(array $p): array
    {
        $rows = $this->between($this->salesQuery(), 'sale_date', $p['kpiStart'], $p['kpiEnd'])->select('payment_method', DB::raw('sum(total) as v'))->groupBy('payment_method')->pluck('v', 'payment_method');
        $parts = $rows->mapWithKeys(fn ($v, $k) => [ucwords(str_replace('_', ' ', (string) ($k ?: 'Other'))) => (float) $v])->sortDesc();
        $palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)'];
        return $this->ring('Sales by payment method', $parts, $parts->keys()->mapWithKeys(fn ($k, $i) => [$k => $palette[$i] ?? 'var(--chart-other)'])->all(), 'Sales value (UGX) by how customers paid.');
    }

    private function ringDeathCauses(array $p): array
    {
        $poultry = $this->between(DB::table('mortality_records')->leftJoin('mortality_causes', 'mortality_causes.id', '=', 'mortality_records.mortality_cause_id'), 'occurred_on', $p['kpiStart'], $p['kpiEnd'])
            ->select(DB::raw("coalesce(mortality_causes.name, 'Not yet examined') as cause"), DB::raw('sum(quantity_died + quantity_culled) as n'))->groupBy('cause')->pluck('n', 'cause');
        $animals = $this->between(DB::table('livestock_mortality_records'), 'occurred_on', $p['kpiStart'], $p['kpiEnd'])
            ->select(DB::raw("coalesce(established_cause, 'Not yet examined') as cause"), DB::raw('sum(quantity) as n'))->groupBy('cause')->pluck('n', 'cause');
        $all = $poultry->mergeRecursive($animals)->map(fn ($v) => (float) collect((array) $v)->sum())->sortDesc();
        $top = $all->take(3); if ($all->count() > 3) $top['Other causes'] = $all->slice(3)->sum();
        $palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)'];
        return $this->ring('Deaths by cause', $top, $top->keys()->mapWithKeys(fn ($k, $i) => [$k => $k === 'Other causes' ? 'var(--chart-other)' : ($palette[$i] ?? 'var(--chart-other)')])->all(), 'Birds and animals lost, by the cause found at examination.');
    }

    private function ringStaffTypes(): array
    {
        $rows = DB::table('employees')->whereNull('deleted_at')->where('status', 'active')->select('employment_type', DB::raw('count(*) as n'))->groupBy('employment_type')->pluck('n', 'employment_type')->sortDesc();
        $parts = $rows->mapWithKeys(fn ($v, $k) => [ucfirst((string) ($k ?: 'Other')) => (float) $v]);
        $top = $parts->take(3); if ($parts->count() > 3) $top['Other'] = $parts->slice(3)->sum();
        $palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)'];
        return $this->ring('Staff by type', $top, $top->keys()->mapWithKeys(fn ($k, $i) => [$k => $k === 'Other' ? 'var(--chart-other)' : ($palette[$i] ?? 'var(--chart-other)')])->all(), 'Active staff by employment type.');
    }

    private function ringDirectives(array $p): array
    {
        $rows = $this->between(DB::table('executive_directives'), 'issued_on', $p['kpiStart'], $p['kpiEnd'])->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $group = ['done' => 'Completed', 'completed' => 'Completed', 'in_progress' => 'In progress', 'not_started' => 'Not started', 'pending' => 'Not started', 'rescheduled' => 'Not started', 'blocked' => 'Blocked', 'cancelled' => 'Cancelled'];
        $parts = collect(['Completed' => 0, 'In progress' => 0, 'Not started' => 0, 'Blocked' => 0, 'Cancelled' => 0]);
        foreach ($rows as $status => $n) { $label = $group[$status] ?? 'Not started'; $parts[$label] += (float) $n; }
        return $this->ring('Directives by progress', $parts->filter(), ['Completed' => 'var(--chart-4)', 'In progress' => 'var(--chart-3)', 'Not started' => 'var(--chart-2)', 'Blocked' => 'var(--chart-1)', 'Cancelled' => 'var(--chart-other)'],
            'Directives issued in the period, by progress.');
    }

    /* ===================== top 5 ===================== */

    private function top(string $title, Collection $values, string $format, string $color, ?string $info = null): array
    {
        $rows = $values->filter(fn ($v) => $v > 0)->sortDesc()->take(5);
        return ['title' => $title, 'format' => $format, 'color' => $color, 'info' => $info, 'max' => (float) ($rows->max() ?: 0),
            'rows' => $rows->map(fn ($v, $label) => ['label' => (string) $label, 'value' => (float) $v])->values()->all()];
    }

    private function topSalesByProduct(array $p): array
    {
        $rows = $this->between(DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sales.status', '!=', 'void')
            ->where('sales.approval_status', 'approved')->where('sales.is_staff_consumption', false), 'sales.sale_date', $p['kpiStart'], $p['kpiEnd'])
            ->select(DB::raw("coalesce(nullif(sale_lines.category, ''), 'other') as c"), DB::raw('sum(line_total) as v'))->groupBy('c')->pluck('v', 'c');
        return $this->top('Top 5 sales by product type', $rows->mapWithKeys(fn ($v, $k) => [ucfirst($k) => (float) $v]), 'money', 'var(--chart-2)', 'Sales value (UGX) by product type.');
    }

    private function topCustomers(array $p): array
    {
        $rows = $this->between($this->salesQuery(), 'sale_date', $p['kpiStart'], $p['kpiEnd'])->select('customer_name', DB::raw('sum(total) as v'))->groupBy('customer_name')->pluck('v', 'customer_name');
        return $this->top('Top 5 customers', $rows->map(fn ($v) => (float) $v), 'money', 'var(--chart-4)', 'Customers who bought the most (UGX).');
    }

    private function topSpending(array $p): array
    {
        $rows = $this->between($this->money('money_paid')->whereIn('transaction_category', ['purchase', 'staff_payment']), 'transaction_date', $p['kpiStart'], $p['kpiEnd'])
            ->select('entry_account', DB::raw('sum(amount) as v'))->groupBy('entry_account')->pluck('v', 'entry_account');
        return $this->top('Top 5 spending', $rows->map(fn ($v) => (float) $v), 'money', 'var(--chart-2)', 'Where money was spent (UGX), by account.');
    }

    private function topSuppliers(array $p): array
    {
        $rows = $this->between($this->money('money_paid')->where('transaction_category', 'purchase')->whereNotNull('counterparty'), 'transaction_date', $p['kpiStart'], $p['kpiEnd'])
            ->select('counterparty', DB::raw('sum(amount) as v'))->groupBy('counterparty')->pluck('v', 'counterparty');
        return $this->top('Top 5 suppliers', $rows->map(fn ($v) => (float) $v), 'money', 'var(--chart-4)', 'Suppliers paid the most (UGX).');
    }

    private function topHousesByEggs(array $p): array
    {
        $rows = $this->between(DB::table('egg_collections')->join('poultry_houses', 'poultry_houses.id', '=', 'egg_collections.poultry_house_id')->where('egg_collections.status', 'approved'), 'collected_on', $p['kpiStart'], $p['kpiEnd'])
            ->select('poultry_houses.house_number', DB::raw('sum(total_eggs) as v'))->groupBy('poultry_houses.house_number')->pluck('v', 'house_number');
        return $this->top('Top 5 houses by eggs', $rows->map(fn ($v) => (float) $v), 'count', 'var(--chart-2)', 'Approved eggs collected per house.');
    }

    private function topFeeds(array $p): array
    {
        $poultry = $this->between(DB::table('feed_consumptions')->where('status', 'approved'), 'used_on', $p['kpiStart'], $p['kpiEnd'])
            ->select(DB::raw("coalesce(feed_type, 'Feed') as f"), DB::raw('sum(quantity_kg) as v'))->groupBy('f')->pluck('v', 'f');
        $animals = $this->between(DB::table('livestock_feed_records')->whereIn('action', ['fed', 'issued']), 'recorded_on', $p['kpiStart'], $p['kpiEnd'])
            ->select('feed_name as f', DB::raw('sum(quantity_kg) as v'))->groupBy('feed_name')->pluck('v', 'f');
        return $this->top('Top 5 feeds used (kg)', $poultry->mergeRecursive($animals)->map(fn ($v) => (float) collect((array) $v)->sum()), 'count', 'var(--chart-4)', 'Feed used across all units, in kg.');
    }

    private function topDepartmentsByStaff(): array
    {
        $rows = DB::table('employees')->leftJoin('departments', 'departments.id', '=', 'employees.department_id')->whereNull('employees.deleted_at')->where('employees.status', 'active')
            ->select(DB::raw("coalesce(departments.name, 'No department') as d"), DB::raw('count(*) as v'))->groupBy('d')->pluck('v', 'd');
        return $this->top('Top 5 departments by staff', $rows->map(fn ($v) => (float) $v), 'count', 'var(--chart-2)', 'Active staff per department.');
    }

    private function topDepartmentsByPayroll(array $p): array
    {
        $rows = $this->between(DB::table('payroll_lines')->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_lines.payroll_run_id')->join('employees', 'employees.id', '=', 'payroll_lines.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id'), 'payroll_runs.period_end', $p['kpiStart'], $p['kpiEnd'])
            ->select(DB::raw("coalesce(departments.name, 'No department') as d"), DB::raw('sum(payroll_lines.gross_pay) as v'))->groupBy('d')->pluck('v', 'd');
        return $this->top('Top 5 departments by payroll', $rows->map(fn ($v) => (float) $v), 'money', 'var(--chart-4)', 'Gross pay per department (UGX).');
    }

    private function topDirectiveTeams(): array
    {
        $rows = DB::table('executive_directives')->whereNotIn('status', self::CLOSED_DIRECTIVES)
            ->select(DB::raw("coalesce(nullif(responsible_team, ''), 'Not assigned') as t"), DB::raw('count(*) as v'))->groupBy('t')->pluck('v', 't');
        return $this->top('Open directives by team', $rows->map(fn ($v) => (float) $v), 'count', 'var(--chart-2)', 'Directives still open, by the team responsible.');
    }

    private function topFundsAwaiting(): array
    {
        $rows = DB::table('approved_fund_follow_ups')->where('status', '!=', 'verified')->get(['intended_purpose', 'approved_amount', 'amount_accounted_for'])
            ->mapWithKeys(fn ($r, $i) => [\Illuminate\Support\Str::limit((string) ($r->intended_purpose ?: 'Approved funds'), 28).str_repeat("\u{200B}", $i) => max(0, (float) $r->approved_amount - (float) $r->amount_accounted_for)]);
        return $this->top('Funds awaiting accountability', $rows, 'money', 'var(--chart-4)', 'Approved money not yet accounted for (UGX).');
    }

    private function topConstruction(array $p): array
    {
        $rows = $this->between($this->money('money_paid')->join('construction_projects', 'construction_projects.id', '=', 'financial_transactions.construction_project_id'), 'transaction_date', $p['kpiStart'], $p['kpiEnd'])
            ->select('construction_projects.name', DB::raw('sum(amount) as v'))->groupBy('construction_projects.name')->pluck('v', 'name');
        return $this->top('Top 5 construction projects (spent)', $rows->map(fn ($v) => (float) $v), 'money', 'var(--chart-2)', 'Money paid per construction project (UGX).');
    }
}
