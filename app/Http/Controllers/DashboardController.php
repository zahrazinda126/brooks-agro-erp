<?php

namespace App\Http\Controllers;

use App\Models\AnimalGroup;
use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use App\Models\Approval;
use App\Models\FinancialTransaction;
use App\Models\Purchase;
use App\Models\HealthEvent;
use App\Models\LivestockAnimal;
use App\Models\FarmUnitRecord;
use App\Models\InventoryItem;
use App\Models\Budget;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\ApprovedFundFollowUp;
use App\Models\ExecutiveDirective;
use App\Models\ExecutiveMeeting;
use App\Models\PayrollRun;
use App\Models\EmployeeRecord;
use App\Models\FeedConsumption;
use App\Models\EggCollection;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalesPayment;
use App\Models\DailySalesBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\VaccinationReminderService;

/*
 | The dashboard is assembled from what a person can ACCESS, not from a
 | check on their job title.
 |
 | Hardcoding "if HR Manager show staff" breaks the moment someone holds
 | two roles — which the system now allows. Asking canAccess() instead
 | means a supervisor who also keeps the store gets both panels, with no
 | special case anywhere.
 |
 | Panels only appear when they have something real to say. A farm with no
 | batches yet gets a line telling them to open one, not an empty chart.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $salesFrom = $request->query('sales_from', now()->toDateString());
        $salesTo = $request->query('sales_to', now()->toDateString());
        abort_unless(is_string($salesFrom) && is_string($salesTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $salesFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $salesTo), 422, 'Choose valid sales dates.');
        abort_if($salesFrom > $salesTo, 422, 'The From date must not be after the To date.');

        app(VaccinationReminderService::class)->sendDueReminders($user);

        // Workers have no dashboard. Send them straight to their work.
        if ($user->isDataEntryOnly()) {
            return redirect()->route('daily.index');
        }

        // Role dashboard (KPIs, trend, ring, top 5), limited to what this person may see.
        $analytics = app(\App\Services\AnalyticsDashboard::class);
        $period = $analytics->period($request);

        return view('dashboard', [
            'dash'     => $analytics->for($user, $period),
            'period'   => $period,
            'greeting' => $this->greeting(),
            'panels'   => $this->panelsFor($user),
            'data'     => $this->dataFor($user, $salesFrom, $salesTo),
        ]);
    }

    /**
     * Which panels this person sees, in order of what matters to them.
     *
     * Workers get their own work first; managers get the state of the
     * farm first. Everything is gated on access, so a person with several
     * roles simply gets more panels.
     */
    private function panelsFor(User $user): array
    {
        $panels = [];
        $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code','ceo');
        $isHrManager = $user->role?->code === 'hr-manager'
            || $user->roles->contains('code', 'hr-manager');
        $isFinanceManager = $this->isFinanceManager($user);
        $isDirector = $user->role?->code === 'director'
            || $user->roles->contains('code', 'director');
        $isProductionManager = $this->isProductionManager($user);
        $isPersonalAssistant = $user->isPersonalAssistant();
        $isSalesManager = $user->role?->code === 'sales-manager' || $user->roles->contains('code','sales-manager');
        if ($isCeo) return ['executive'];
        // The Director sees Farm performance on Financial & Executive Reports; the dashboard shows the analytics only.
        if ($isDirector) return [];
        // The sales sheet is on Sales → Daily Sales; the dashboard shows the analytics only.
        if ($isSalesManager) return [];
        if ($isFinanceManager) return ['finance-dashboard'];
        // Director Follow-up has its own page; the dashboard shows the analytics only.
        if ($isPersonalAssistant) return [];
        if ($isHrManager) return ['hr-dashboard'];
        if ($isProductionManager) return ['production-overview'];

        if ($user->canAccess('batches') && ! $isProductionManager) {
            $panels[] = 'flocks';
        }

        if ($user->canDo('approvals', 'approve') || $user->canAccess('approvals')) {
            $panels[] = 'approvals';
        }

        if ($user->canAccess('hr')) {
            $panels[] = 'staff';
        }

        if ($isHrManager && $user->canAccess('kpi-dashboards')) {
            $panels[] = 'hr-kpis';
        }

        if ($user->canAccess('inventory')) {
            $panels[] = 'stock';
        }

        // Nobody should land on an empty screen.
        if (! $panels) {
            $panels[] = 'welcome';
        }

        // Finance already has its purpose-built sidebar. Generic shortcuts can
        // expose unrelated operational screens and do not belong here.
        if (! $isFinanceManager && ! $isCeo && ! $isDirector && ! $isProductionManager) {
            $panels[] = 'shortcuts';
        }

        return $panels;
    }

    private function dataFor(User $user, string $salesFrom, string $salesTo): array
    {
        $data = [];
        $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code','ceo');
        $isDirector = $user->role?->code === 'director' || $user->roles->contains('code','director');
        $isSalesManager = $user->role?->code === 'sales-manager' || $user->roles->contains('code','sales-manager');
        $isFinanceManager = $this->isFinanceManager($user);
        $isPersonalAssistant = $user->isPersonalAssistant();
        if ($isFinanceManager) $data['financeDashboard'] = $this->financeDashboard();
        if ($user->role?->code === 'hr-manager' || $user->roles->contains('code', 'hr-manager')) {
            $data['hrDashboard'] = $this->hrDashboard();
        }
        if ($isCeo || $isDirector) $data['executive'] = $this->executiveDashboard($user);
        if ($this->isProductionManager($user)) $data['productionOverview']=$this->productionOverview();

        if ($user->canAccess('approvals')) $data['approvals']=['pending'=>Approval::where('status','pending')->count(),'overdue'=>Approval::where('status','pending')->where('due_at','<',now())->count()];

        if ($user->canAccess('batches')) {
            $data['flocks'] = $this->flockData($user);
        }

        if ($user->canAccess('hr')) {
            $data['staff'] = $this->staffData();
        }

        if ($user->canAccess('inventory')) {
            $balanceSql="coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end),0)";
            $stockItems=InventoryItem::query()->select('inventory_items.*')->selectSub(fn($q)=>$q->from('inventory_movements')->whereColumn('inventory_item_id','inventory_items.id')->where('receipt_status','confirmed')->selectRaw($balanceSql),'balance')->get();
            $data['stock']=['items'=>$stockItems->count(),'low'=>$stockItems->filter(fn($item)=>(float)$item->balance<=(float)$item->reorder_level)->count(),'feedLines'=>DB::table('feed_stock_movements')->select('unit_code','feed_name')->groupBy('unit_code','feed_name')->get()->count()];
        }

        $data['shortcuts'] = $this->shortcuts($user);

        return $data;
    }

    /** Farm performance figures, also shown to the CEO and Director on Financial & Executive Reports. */
    public function executiveSummary(User $user): array
    {
        return $this->executiveDashboard($user);
    }

    private function executiveDashboard(User $user): array
    {
        $months = collect(range(5, 0))->map(function (int $offset) {
            $date = now()->copy()->subMonths($offset);
            $rows = FinancialTransaction::whereBetween('transaction_date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->get();

            return [
                'label' => $date->format('M'),
                'income' => (float) $rows->filter(fn ($row) => ($row->type === 'money_received' && $row->transaction_category !== 'customer_prepayment') || $row->type === 'revenue_recognized')->sum('amount'),
                'spending' => (float) $rows->where('type', 'money_paid')->sum('amount'),
            ];
        });
        $month = $months->last();
        $spendingByArea = FinancialTransaction::where('type', 'money_paid')
            ->whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw("coalesce(nullif(cost_centre, ''), nullif(account, ''), 'Other') as name, sum(amount) as amount")
            ->groupByRaw("coalesce(nullif(cost_centre, ''), nullif(account, ''), 'Other')")
            ->orderByDesc('amount')->limit(5)->get()
            ->map(fn ($row) => ['name' => $row->name, 'amount' => (float) $row->amount]);
        $livestock = LivestockAnimal::where('status', 'active')->selectRaw('unit_code, count(*) as total')->groupBy('unit_code')->pluck('total', 'unit_code');
        $feedBalance = (float) DB::table('feed_stock_movements')->selectRaw("coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity_kg else -quantity_kg end),0) as balance")->value('balance');
        $approvalRole = ($user->role?->code === 'ceo' || $user->roles->contains('code', 'ceo')) ? 'ceo' : 'director';
        $executiveApprovalQuery = Approval::with(['approvable', 'submitter', 'steps'])
            ->where('status', 'pending')
            ->whereHas('steps', fn ($query) => $query->where('role_code', $approvalRole)->where('status', 'pending'));
        $executiveApprovalCount = (clone $executiveApprovalQuery)->count();
        $executiveOverdueCount = (clone $executiveApprovalQuery)->where('due_at', '<', now())->count();
        $executivePendingApprovals = $executiveApprovalQuery->oldest('submitted_at')->limit(5)->get();

        return [
            'income' => $month['income'], 'spending' => $month['spending'], 'net' => $month['income'] - $month['spending'],
            'monthly' => $months, 'spendingByArea' => $spendingByArea,
            'pending' => $executiveApprovalCount,
            'overdue' => $executiveOverdueCount,
            'pendingApprovals' => $executivePendingApprovals,
            'approvalRole' => $approvalRole,
            'purchaseExposure' => (float) Purchase::whereIn('status', ['submitted','finance_reviewed','ceo_approved'])->sum('total_cost'),
            'receivables' => (float) Receivable::selectRaw('coalesce(sum(amount - amount_paid),0) as balance')->value('balance'),
            'payables' => (float) Payable::selectRaw('coalesce(sum(amount - amount_paid),0) as balance')->value('balance'),
            'birds' => (int) AnimalGroup::where('status', 'active')->sum('current_quantity'),
            'livestock' => $livestock, 'feedBalance' => $feedBalance,
            'eggsToday' => (int) EggCollection::whereDate('collected_on', today())->sum('total_eggs'),
            'eggsMonth' => (int) EggCollection::whereBetween('collected_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_eggs'),
            'withdrawals' => HealthEvent::where('withdrawal_ends_eggs', '>=', now()->toDateString())->count(),
        ];
    }

    /*
     | Flocks: the numbers a supervisor actually acts on.
     |
     | "Needs attention" is the useful one — flocks overdue to lay or
     | losing birds faster than their age accounts for. Both judgements
     | live on the model, so this reads them rather than repeating the
     | rules.
     */
    private function flockData(User $user): array
    {
        $batches = AnimalGroup::with('breed')
            ->visibleTo($user)
            ->where('status', 'active')
            ->get();

        $attention = $batches->filter(
            fn ($b) => $b->layingIsOverdue() || $b->mortalityIsHigh()
        );

        // Flocks about to start laying — worth preparing for.
        $comingIntoLay = $batches->filter(function ($b) {
            $weeks = $b->weeksToLay();

            return $weeks !== null && $weeks > 0 && $weeks <= 3;
        });

        return [
            'count'      => $batches->count(),
            'birds'      => $batches->sum('current_quantity'),
            'laying'     => $batches->filter(fn ($b) => $b->shouldBeLaying())->count(),
            'attention'  => $attention,
            'comingSoon' => $comingIntoLay,
        ];
    }

    private function staffData(): array
    {
        return [
            'active'      => Employee::where('status', 'active')->count(),
            'withLogin'   => Employee::whereHas('user')->count(),
            'neverSignedIn' => Employee::whereHas('user', function ($q) {
                $q->whereNull('last_login_at')->where('is_active', true);
            })->count(),
        ];
    }

    private function hrDashboard(): array
    {
        $active = Employee::where('status', 'active');
        $latestPayroll = PayrollRun::withCount('lines')->latest('period_end')->first();

        return [
            'active' => (clone $active)->count(),
            'permanent' => (clone $active)->where('employment_type', 'permanent')->count(),
            'casual' => (clone $active)->where('employment_type', 'casual')->count(),
            'withoutLogin' => (clone $active)->whereDoesntHave('user')->count(),
            'endingSoon' => (clone $active)->whereNotNull('expected_end_date')
                ->whereBetween('expected_end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'pendingDeductions' => EmployeeRecord::where('is_payroll_deduction', true)
                ->where('payroll_deduction_status', 'pending')->count(),
            'payrollPending' => PayrollRun::whereIn('status', ['submitted','finance_reviewed','ceo_reviewed'])->count(),
            'monthlyPayroll' => (float) PayrollRun::whereMonth('period_end', now()->month)
                ->whereYear('period_end', now()->year)->sum('net_total'),
            'latestPayroll' => $latestPayroll,
            'recentStaff' => Employee::with(['department','role'])->latest()->limit(6)->get(),
        ];
    }

    /**
     * The three or four screens this person opens most, so the common
     * case is one tap rather than a trip through the menu.
     */
   private function shortcuts(User $user): \Illuminate\Support\Collection
    {
        if ($this->isProductionManager($user)) {
            return collect([
                (object)['name'=>'Overall Production Report','link'=>route('operations.reports'),'icon'=>'chart'],
                (object)['name'=>'Livestock Overview','link'=>route('operations.reports',['view'=>'livestock']),'icon'=>'paw'],
                (object)['name'=>'Health & Losses','link'=>route('operations.reports',['view'=>'health']),'icon'=>'vet'],
                (object)['name'=>'Feeding & Inputs','link'=>route('operations.reports',['view'=>'inputs']),'icon'=>'grain'],
                (object)['name'=>'Production & Harvest','link'=>route('operations.reports',['view'=>'production']),'icon'=>'factory'],
            ]);
        }
        $wanted = [
            'egg-entry', 'mortality-entry', 'feed-consumption',
            'batches', 'my-tasks', 'approvals', 'hr', 'payroll',
            'inventory', 'reports',
        ];

        $isHrManager = $user->role?->code === 'hr-manager'
            || $user->roles->contains('code', 'hr-manager');

        if ($isHrManager) {
            $wanted = array_values(array_diff($wanted, ['hr', 'payroll', 'reports']));
        }

        $allowed = array_filter($wanted, fn ($k) => $user->canAccess($k));

        return Module::whereIn('key', array_slice($allowed, 0, 6))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function greeting(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };
    }

    private function isProductionManager(User $user): bool
    {
        return $user->role?->code === 'production-manager'
            || $user->roles->contains('code','production-manager')
            || (($user->role?->code === 'department-manager' || $user->roles->contains('code','department-manager'))
                && ($user->department?->code === 'production' || $user->departments->contains('code','production')));
    }

    private function isFinanceManager(User $user): bool
    {
        return $user->role?->code === 'finance-manager'
            || $user->roles->contains('code', 'finance-manager')
            || $user->department?->code === 'finance'
            || $user->departments->contains('code', 'finance')
            || $user->department?->code === 'div-finance'
            || $user->departments->contains('code', 'div-finance');
    }

    private function productionOverview(): array
    {
        $eggRecords = EggCollection::whereBetween('collected_on', [now()->startOfMonth()->subMonths(11)->toDateString(), today()->toDateString()])
            ->whereIn('status', ['approved', 'submitted', 'pending'])
            ->selectRaw('collected_on, sum(total_eggs) as total_eggs, sum(damaged_eggs) as damaged_eggs')
            ->groupBy('collected_on')->get();
        $monthly = collect(range(11, 0))->map(function (int $offset) use ($eggRecords) {
            $date = now()->startOfMonth()->subMonths($offset);
            $rows = $eggRecords->filter(fn ($row) => $row->collected_on->format('Y-m') === $date->format('Y-m'));
            return ['label' => $date->format('M'), 'period' => $date->format('M Y'),
                'eggs' => (int) $rows->sum('total_eggs'), 'damaged' => (int) $rows->sum('damaged_eggs')];
        });
        $activityStart=now()->subDays(30)->toDateString();
        $poultryHouses = AnimalGroup::where('status', 'active')->where('current_quantity', '>', 0)
            ->orderByRaw("coalesce(house, code)")->get();
        $todayFeed = FeedConsumption::whereDate('used_on', now()->toDateString())
            ->whereIn('status', ['approved', 'submitted', 'pending'])
            ->selectRaw('animal_group_id, coalesce(sum(quantity_kg), 0) as quantity_kg')
            ->groupBy('animal_group_id')->pluck('quantity_kg', 'animal_group_id');
        $feedPlan = $poultryHouses->map(function (AnimalGroup $house) use ($todayFeed) {
            $required = round($house->current_quantity * config('farm.feed_kg_per_bird_per_day'), 2);
            $recorded = round((float) ($todayFeed[$house->id] ?? 0), 2);
            return ['batch'=>$house, 'house'=>$house->house ?: $house->code, 'birds'=>(int)$house->current_quantity,
                'requiredKg'=>$required, 'recordedKg'=>$recorded, 'shortageKg'=>max(0, round($required-$recorded, 2)),
                'percent'=>$required > 0 ? min(100, round($recorded/$required*100)) : 100];
        });
        $units=collect([
            ['code'=>'poultry','label'=>'Poultry','icon'=>'🐔','value'=>(int)AnimalGroup::where('status','active')->sum('current_quantity'),'measure'=>'birds','activity'=>HealthEvent::where('occurred_on','>=',$activityStart)->count()],
            ['code'=>'goats','label'=>'Goats','icon'=>'🐐','value'=>(int)DB::table('livestock_groups')->where('unit_code','goats')->where('status','active')->sum('current_quantity'),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','goats')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'rabbits','label'=>'Rabbits','icon'=>'🐇','value'=>(int)DB::table('livestock_groups')->where('unit_code','rabbits')->where('status','active')->sum('current_quantity'),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','rabbits')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'dogs','label'=>'Dogs','icon'=>'🐕','value'=>(int)DB::table('livestock_groups')->where('unit_code','dogs')->where('status','active')->sum('current_quantity'),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','dogs')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'garden','label'=>'Garden','icon'=>'🌱','value'=>FarmUnitRecord::where('unit_code','garden')->where('record_type','plot')->distinct('subject')->count('subject'),'measure'=>'plots','activity'=>FarmUnitRecord::where('unit_code','garden')->where('record_date','>=',$activityStart)->count()],
        ]);
        $monthStart = now()->startOfMonth()->toDateString();
        $previousStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $previousEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $livestockOutput = DB::table('livestock_production_records as production')
            ->join('livestock_groups as groups', 'groups.id', '=', 'production.livestock_group_id')
            ->where('production.verification_status', 'approved')
            ->whereBetween('production.recorded_on', [$previousStart, today()->toDateString()])
            ->selectRaw("groups.unit_code, production.measure_unit, sum(case when production.recorded_on >= ? then production.quantity else 0 end) as current_total, sum(case when production.recorded_on < ? then production.quantity else 0 end) as previous_total", [$monthStart, $monthStart])
            ->groupBy('groups.unit_code', 'production.measure_unit')->get();
        $productionByUnit = collect([[
            'label' => 'Poultry', 'metric' => 'Eggs', 'measure' => 'eggs',
            'current' => (float) EggCollection::where('status', 'approved')->whereBetween('collected_on', [$monthStart, today()->toDateString()])->sum('total_eggs'),
            'previous' => (float) EggCollection::where('status', 'approved')->whereBetween('collected_on', [$previousStart, $previousEnd])->sum('total_eggs'),
        ]])->merge($livestockOutput->map(fn ($row) => ['label' => ucfirst($row->unit_code).' ('.$row->measure_unit.')',
            'metric' => ucfirst($row->measure_unit), 'measure' => $row->measure_unit,
            'current' => (float) $row->current_total, 'previous' => (float) $row->previous_total]))->values();
        $productionChartMax = max(1, $productionByUnit->max(fn ($row) => max($row['current'], $row['previous'])));

        $recent = collect();
        EggCollection::with(['batch', 'recorder'])->whereIn('status', ['approved', 'submitted', 'pending'])
            ->latest('collected_on')->latest('id')->limit(6)->get()->each(function ($row) use ($recent) {
                $recent->push(['date' => $row->collected_on, 'activity' => 'Eggs collected', 'livestock' => 'Poultry',
                    'unit' => $row->batch?->house ?: ($row->batch?->code ?: 'Poultry'), 'quantity' => number_format($row->total_eggs).' eggs',
                    'recordedBy' => $row->recorder?->name ?? '—']);
            });
        FeedConsumption::with(['batch', 'recorder'])->whereIn('status', ['approved', 'submitted', 'pending'])
            ->latest('used_on')->latest('id')->limit(6)->get()->each(function ($row) use ($recent) {
                $recent->push(['date' => $row->used_on, 'activity' => 'Feed recorded', 'livestock' => 'Poultry',
                    'unit' => $row->batch?->house ?: ($row->batch?->code ?: 'Poultry'), 'quantity' => number_format((float) $row->quantity_kg, 1).' kg',
                    'recordedBy' => $row->recorder?->name ?? '—']);
            });
        $recentLivestock = DB::table('livestock_production_records as production')
            ->join('livestock_groups as groups', 'groups.id', '=', 'production.livestock_group_id')
            ->leftJoin('users', 'users.id', '=', 'production.recorded_by')
            ->where('production.verification_status', 'approved')->latest('production.recorded_on')->latest('production.id')->limit(8)
            ->get(['production.recorded_on as date', 'production.production_type', 'production.quantity', 'production.measure_unit', 'groups.unit_code', 'groups.group_code', 'users.name as recorded_by']);
        foreach ($recentLivestock as $row) $recent->push(['date' => $row->date, 'activity' => ucfirst($row->production_type).' recorded',
            'livestock' => ucfirst($row->unit_code), 'unit' => $row->group_code,
            'quantity' => number_format((float) $row->quantity, 1).' '.$row->measure_unit, 'recordedBy' => $row->recorded_by ?: '—']);
        $recent = $recent->sortByDesc(fn ($row) => (string) $row['date'])->take(6)->values();
        $topUnits = collect();
        EggCollection::with('batch')->where('status', 'approved')->whereBetween('collected_on', [$monthStart, today()->toDateString()])
            ->selectRaw('animal_group_id, sum(total_eggs) as quantity')->groupBy('animal_group_id')->orderByDesc('quantity')->limit(5)->get()
            ->each(function ($row) use ($topUnits) { $topUnits->push(['unit' => $row->batch?->house ?: ($row->batch?->code ?: 'Poultry'), 'livestock' => 'Poultry', 'metric' => 'Eggs collected', 'value' => number_format($row->quantity).' eggs', 'sortValue' => (float) $row->quantity]); });
        DB::table('livestock_production_records as production')
            ->join('livestock_groups as groups', 'groups.id', '=', 'production.livestock_group_id')
            ->where('production.verification_status', 'approved')->whereBetween('production.recorded_on', [$monthStart, today()->toDateString()])
            ->selectRaw('groups.group_code, groups.unit_code, production.production_type, production.measure_unit, sum(production.quantity) as quantity')
            ->groupBy('groups.group_code', 'groups.unit_code', 'production.production_type', 'production.measure_unit')
            ->orderByDesc('quantity')->limit(5)->get()
            ->each(function ($row) use ($topUnits) { $topUnits->push(['unit' => $row->group_code, 'livestock' => ucfirst($row->unit_code), 'metric' => ucfirst($row->production_type), 'value' => number_format((float) $row->quantity, 1).' '.$row->measure_unit, 'sortValue' => (float) $row->quantity]); });
        $topUnits = $topUnits->sortByDesc('sortValue')->take(5)->map(function ($row) { unset($row['sortValue']); return $row; })->values();
        $attention = FarmUnitRecord::where('status', 'needs_attention')->latest('record_date')->limit(5)->get();
        return ['monthly'=>$monthly, 'eggsToday'=>(int)$eggRecords->filter(fn ($row) => $row->collected_on->isToday())->sum('total_eggs'),
            'eggsMonth'=>$monthly->last()['eggs'], 'damagedMonth'=>$monthly->last()['damaged'],
            'units'=>$units,'needsAttention'=>FarmUnitRecord::where('status','needs_attention')->count(),'activity'=>$units->sum('activity'),
            'feedPlan'=>$feedPlan, 'dailyFeedKg'=>(float)$feedPlan->sum('requiredKg'),
            'recordedFeedKg'=>(float)$feedPlan->sum('recordedKg'), 'feedShortageKg'=>(float)$feedPlan->sum('shortageKg'),
            'totalBirds'=>(int)$feedPlan->sum('birds'), 'productionByUnit'=>$productionByUnit, 'productionChartMax'=>$productionChartMax,
            'topUnits'=>$topUnits, 'recent'=>$recent, 'attentionRecords'=>$attention];
    }

    /**
     * Finance dashboard: income, expenditure and net cash for the chosen period (with the change against the
     * period before), the income vs expenditure trend, the top spending categories and recent transactions.
     * Income is money received from operations (sales and other income); owners' capital and transfers between
     * the farm's own accounts are not income. Expenditure is all money paid out.
     */
    private function financeDashboard(): array
    {
        $r = request();
        $f = [
            'period' => in_array($r->query('period'), ['this_year', 'last_year', 'this_quarter', 'this_month', 'last_12'], true) ? $r->query('period') : 'this_year',
            'view' => in_array($r->query('view'), ['monthly', 'weekly', 'daily'], true) ? $r->query('view') : 'monthly',
            'day' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('day')) ? $r->query('day') : null,
            'quarter' => in_array((int) $r->query('quarter'), [1, 2, 3, 4], true) ? (int) $r->query('quarter') : null,
            'month' => ((int) $r->query('month') >= 1 && (int) $r->query('month') <= 12) ? (int) $r->query('month') : null,
            'tq' => trim((string) $r->query('tq')),
            'ttype' => in_array($r->query('ttype'), ['income', 'expense'], true) ? $r->query('ttype') : null,
        ];
        [$start, $end] = match ($f['period']) {
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_12' => [now()->startOfMonth()->subMonths(11), now()->endOfMonth()],
            default => [now()->startOfYear(), now()->endOfYear()],
        };
        $year = $end->year;
        if ($f['quarter']) { $start = \Carbon\Carbon::create($year, ($f['quarter'] - 1) * 3 + 1, 1)->startOfDay(); $end = $start->copy()->endOfQuarter(); }
        if ($f['month']) { $start = \Carbon\Carbon::create($year, $f['month'], 1)->startOfDay(); $end = $start->copy()->endOfMonth(); }
        if ($f['day']) { $start = \Carbon\Carbon::parse($f['day'])->startOfDay(); $end = $start->copy()->endOfDay(); }

        $isIncome = fn ($q) => $q->where(fn ($x) => $x->where('type', 'revenue_recognized')->orWhere(fn ($y) => $y->where('type', 'money_received')
            ->where(fn ($z) => $z->whereNull('transaction_category')->orWhereNotIn('transaction_category', ['capital_and_equity', 'customer_prepayment', 'banking']))));
        $ledger = fn () => FinancialTransaction::whereNull('reversed_at')->whereNull('reversal_of_id');
        $sum = function ($from, $to) use ($ledger, $isIncome) {
            $income = (float) $isIncome($ledger()->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]))->sum('amount');
            $spent = (float) $ledger()->where('type', 'money_paid')->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
            return ['income' => $income, 'expenditure' => $spent, 'net' => $income - $spent];
        };
        $now = $sum($start, $end);
        $days = (int) $start->diffInDays($end) + 1;
        $prev = $sum($start->copy()->subDays($days), $start->copy()->subDay());
        $change = fn ($a, $b) => $b != 0.0 ? ($a - $b) / abs($b) * 100 : null;

        // Trend buckets: months, weeks or days in the range.
        $buckets = [];
        $cursor = $f['view'] === 'monthly' ? $start->copy()->startOfMonth() : ($f['view'] === 'weekly' ? $start->copy()->startOfWeek() : $start->copy()->startOfDay());
        while ($cursor->lte($end) && count($buckets) < 62) {
            $to = $f['view'] === 'monthly' ? $cursor->copy()->endOfMonth() : ($f['view'] === 'weekly' ? $cursor->copy()->endOfWeek() : $cursor->copy()->endOfDay());
            $label = $f['view'] === 'monthly' ? $cursor->format('M') : ($f['view'] === 'weekly' ? $cursor->format('d M') : $cursor->format('d M'));
            $buckets[] = ['label' => $label, 'from' => $cursor->copy()->max($start), 'to' => $to->copy()->min($end), 'future' => $cursor->isFuture()];
            $cursor = $f['view'] === 'monthly' ? $cursor->addMonthNoOverflow()->startOfMonth() : ($f['view'] === 'weekly' ? $cursor->addWeek() : $cursor->addDay());
        }
        $trend = collect($buckets)->map(fn ($b) => ['label' => $b['label'], 'future' => $b['future']] + ($b['future'] ? ['income' => 0.0, 'expenditure' => 0.0, 'net' => 0.0] : $sum($b['from'], $b['to'])))->values();

        $paidInRange = fn () => $ledger()->where('type', 'money_paid')->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);
        // Advances on orders (e.g. chicks paid before arrival) are money out but not a spending category.
        $categories = $paidInRange()->where(fn ($q) => $q->whereNull('transaction_category')->orWhere('transaction_category', '!=', 'batch_prepayment'))
            ->selectRaw("coalesce(nullif(entry_account, ''), 'Other') as name, sum(amount) as amount")->groupBy('name')->orderByDesc('amount')->limit(5)->get()
            ->map(fn ($c) => ['name' => $c->name, 'amount' => (float) $c->amount, 'share' => $now['expenditure'] > 0 ? (float) $c->amount / $now['expenditure'] * 100 : 0.0]);

        $transactions = $ledger()->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['money_received', 'money_paid', 'revenue_recognized'])
            ->when($f['ttype'] === 'income', fn ($q) => $isIncome($q))
            ->when($f['ttype'] === 'expense', fn ($q) => $q->where('type', 'money_paid'))
            ->when($f['tq'] !== '', fn ($q) => $q->where(fn ($x) => $x->where('description', 'ilike', '%'.$f['tq'].'%')->orWhere('reference', 'ilike', '%'.$f['tq'].'%')
                ->orWhere('counterparty', 'ilike', '%'.$f['tq'].'%')->orWhere('entry_account', 'ilike', '%'.$f['tq'].'%')))
            ->orderByDesc('transaction_date')->orderByDesc('id')->paginate(5, ['*'], 'tpage')->withQueryString();

        return [
            'filters' => $f, 'start' => $start, 'end' => $end,
            'income' => $now['income'], 'expenditure' => $now['expenditure'], 'net' => $now['net'],
            'incomeChange' => $change($now['income'], $prev['income']), 'expenditureChange' => $change($now['expenditure'], $prev['expenditure']), 'netChange' => $change($now['net'], $prev['net']),
            'trend' => $trend, 'categories' => $categories, 'transactions' => $transactions,
        ];
    }

    public function placeholder(Request $request, string $key): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canAccess($key), 403, 'You do not have access to this screen.');

        $module = Module::where('key', $key)->firstOrFail();

        // Old /m/{key} links for modules now served by a real screen.
        if ($module->isBuilt()) return redirect()->to($module->url());

        return view('module-placeholder', ['module' => $module]);
    }
}
