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

        return view('dashboard', [
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
        $isPersonalAssistant = $user->role?->code === 'personal-assistant' || $user->roles->contains('code','personal-assistant');
        $isSalesManager = $user->role?->code === 'sales-manager' || $user->roles->contains('code','sales-manager');
        if ($isCeo || $isDirector) return ['executive'];
        if ($isSalesManager) return ['sales-dashboard'];
        if ($isFinanceManager) return ['finance-dashboard'];
        if ($isPersonalAssistant) return ['pa-dashboard'];
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
        $isPersonalAssistant = $user->role?->code === 'personal-assistant' || $user->roles->contains('code','personal-assistant');
        if ($isFinanceManager) $data['financeDashboard'] = $this->financeDashboard();
        if ($isPersonalAssistant) $data['paDashboard'] = $this->paDashboard();
        if ($user->role?->code === 'hr-manager' || $user->roles->contains('code', 'hr-manager')) {
            $data['hrDashboard'] = $this->hrDashboard();
        }
        if ($isCeo || $isDirector) $data['executive'] = $this->executiveDashboard($user);
        if ($isSalesManager) $data['salesDashboard'] = $this->salesDashboard($user, $salesFrom, $salesTo);
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

    private function salesDashboard(User $user, string $salesFrom, string $salesTo): array
    {
        $todaySales = Sale::with(['payments', 'customer'])->where('sold_by', $user->id)->whereBetween('sale_date', [$salesFrom, $salesTo])->where('status', '!=', 'void')->orderBy('sale_date')->orderBy('created_at')->get();
        $todayPayments = SalesPayment::whereBetween('paid_on', [$salesFrom, $salesTo])->whereHas('sale', fn ($query) => $query->where('sold_by', $user->id)->where('status', '!=', 'void'))->get();
        $priorCash = (float) SalesPayment::whereDate('paid_on', '<', $salesFrom)->where('payment_method', 'cash')->whereHas('sale', fn ($query) => $query->where('sold_by', $user->id)->where('status', '!=', 'void'))->sum('amount');
        $priorTransfers = (float) DailySalesBalance::where('sales_person_id', $user->id)->whereDate('business_date', '<', $salesFrom)->sum('amount_transferred');
        $openingBalance = max(0, $priorCash - $priorTransfers);
        $rangeTransfers = (float) DailySalesBalance::where('sales_person_id', $user->id)->whereBetween('business_date', [$salesFrom, $salesTo])->sum('amount_transferred');
        return [
            'dailySales'=>$todaySales,
            'dailyPayments'=>$todayPayments,
            'salesFrom'=>$salesFrom,
            'salesTo'=>$salesTo,
            'openingBalance'=>$openingBalance,
            'cashAvailable'=>max(0, $openingBalance + (float)$todayPayments->where('payment_method', 'cash')->sum('amount') - $rangeTransfers),
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

    private function paDashboard(): array
    {
        $directives = ExecutiveDirective::latest('issued_on')->get();
        $meetings = ExecutiveMeeting::orderBy('starts_at')->get();
        $funds = ApprovedFundFollowUp::with('approval.approvable')->latest()->get();
        $trackedApprovalIds = $funds->pluck('approval_id');
        $approvedForTracking = Approval::with('approvable')->where('status','approved')
            ->whereIn('module_key',['budget','purchases'])->whereNotIn('id',$trackedApprovalIds)->latest('decided_at')->limit(20)->get();

        return [
            'directives'=>$directives, 'meetings'=>$meetings, 'funds'=>$funds, 'approvedForTracking'=>$approvedForTracking,
            'openDirectives'=>$directives->whereNotIn('status',['completed','cancelled'])->count(),
            'overdueDirectives'=>$directives->filter(fn($d)=>$d->due_on && $d->due_on->isPast() && !in_array($d->status,['completed','cancelled'],true))->count(),
            'todayMeetings'=>$meetings->filter(fn($m)=>$m->starts_at->isToday() && $m->status==='scheduled')->count(),
            'upcomingMeetings'=>$meetings->filter(fn($m)=>$m->starts_at->isFuture() && $m->status==='scheduled')->take(5),
            'fundsPending'=>$funds->whereNotIn('status',['verified'])->count(),
            'approvedAmount'=>(float)$funds->sum('approved_amount'),
            'accountedAmount'=>(float)$funds->sum('amount_accounted_for'),
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
            $required = round($house->current_quantity * 0.125, 2);
            $recorded = round((float) ($todayFeed[$house->id] ?? 0), 2);
            return ['batch'=>$house, 'house'=>$house->house ?: $house->code, 'birds'=>(int)$house->current_quantity,
                'requiredKg'=>$required, 'recordedKg'=>$recorded, 'shortageKg'=>max(0, round($required-$recorded, 2)),
                'percent'=>$required > 0 ? min(100, round($recorded/$required*100)) : 100];
        });
        $units=collect([
            ['code'=>'poultry','label'=>'Poultry','icon'=>'🐔','value'=>(int)AnimalGroup::where('status','active')->sum('current_quantity'),'measure'=>'birds','activity'=>HealthEvent::where('occurred_on','>=',$activityStart)->count()],
            ['code'=>'goats','label'=>'Goats','icon'=>'🐐','value'=>LivestockAnimal::where('unit_code','goats')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','goats')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'rabbits','label'=>'Rabbits','icon'=>'🐇','value'=>LivestockAnimal::where('unit_code','rabbits')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','rabbits')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'dogs','label'=>'Dogs','icon'=>'🐕','value'=>LivestockAnimal::where('unit_code','dogs')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','dogs')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'garden','label'=>'Garden','icon'=>'🌱','value'=>FarmUnitRecord::where('unit_code','garden')->where('record_type','plot')->distinct('subject')->count('subject'),'measure'=>'plots','activity'=>FarmUnitRecord::where('unit_code','garden')->where('record_date','>=',$activityStart)->count()],
        ]);
        return ['monthly'=>$monthly, 'eggsToday'=>(int)$eggRecords->filter(fn ($row) => $row->collected_on->isToday())->sum('total_eggs'),
            'eggsMonth'=>$monthly->last()['eggs'], 'damagedMonth'=>$monthly->last()['damaged'],
            'units'=>$units,'needsAttention'=>FarmUnitRecord::where('status','needs_attention')->count(),'activity'=>$units->sum('activity'),
            'feedPlan'=>$feedPlan, 'dailyFeedKg'=>(float)$feedPlan->sum('requiredKg'),
            'recordedFeedKg'=>(float)$feedPlan->sum('recordedKg'), 'feedShortageKg'=>(float)$feedPlan->sum('shortageKg'),
            'totalBirds'=>(int)$feedPlan->sum('birds')];
    }

    private function financeDashboard(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $month = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])->get();
        $income = (float) $month->filter(fn ($row) => ($row->type === 'money_received' && $row->transaction_category !== 'customer_prepayment') || $row->type === 'revenue_recognized')->sum('amount');
        $expenses = (float) $month->where('type', 'money_paid')->sum('amount');
        $monthly = collect(range(11, 0))->map(function ($offset) {
            $date = now()->startOfMonth()->subMonths($offset);
            $rows = FinancialTransaction::whereBetween('transaction_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->get();
            return ['label'=>$date->format('M'),'income'=>(float)$rows->filter(fn ($row) => ($row->type === 'money_received' && $row->transaction_category !== 'customer_prepayment') || $row->type === 'revenue_recognized')->sum('amount'),'expenses'=>(float)$rows->where('type','money_paid')->sum('amount')];
        });
        $expenseGroups = $month->where('type','money_paid')->groupBy(fn($row)=>$row->counterparty ?: $row->description ?: 'Other')->map(fn($rows,$name)=>['name'=>$name,'amount'=>(float)$rows->sum('amount')])->sortByDesc('amount')->take(5)->values();
        $budgeted = (float) Budget::whereBetween('period_start', [$monthStart, $monthEnd])->sum('planned_amount');
        $yearStart = now()->startOfMonth()->subMonths(11)->toDateString();
        $yearSales = Sale::with('lines')->whereBetween('sale_date', [$yearStart, $monthEnd])
            ->where('approval_status', 'approved')->where('is_staff_consumption', false)->where('status', '!=', 'void')->get();
        $topCustomers = $yearSales->groupBy('customer_name')->map(fn ($sales, $name) => ['name'=>$name, 'amount'=>(float)$sales->sum('total')])->sortByDesc('amount')->take(5)->values();
        $topProducts = $yearSales->flatMap->lines->groupBy('description')->map(fn ($lines, $name) => ['name'=>$name, 'amount'=>(float)$lines->sum('line_total')])->sortByDesc('amount')->take(5)->values();
        $receivables = (float) Receivable::where('status','open')->selectRaw('coalesce(sum(amount-amount_paid),0) total')->value('total');
        $payables = (float) Payable::where('status','open')->selectRaw('coalesce(sum(amount-amount_paid),0) total')->value('total');
        $ratios = [
            ['name'=>'Net margin', 'value'=>$income > 0 ? ($income-$expenses)/$income*100 : 0, 'suffix'=>'%'],
            ['name'=>'Expense ratio', 'value'=>$income > 0 ? $expenses/$income*100 : 0, 'suffix'=>'%'],
            ['name'=>'Receivables / income', 'value'=>$income > 0 ? $receivables/$income*100 : 0, 'suffix'=>'%'],
            ['name'=>'Payables / spending', 'value'=>$expenses > 0 ? $payables/$expenses*100 : 0, 'suffix'=>'%'],
            ['name'=>'Budget utilisation', 'value'=>$budgeted > 0 ? $expenses/$budgeted*100 : 0, 'suffix'=>'%'],
        ];

        $salesBalances = Sale::with('seller')->whereDate('sale_date', today())->where('status', '!=', 'void')->get()
            ->groupBy('sold_by')->map(function ($sales, $salesPersonId) {
                $payments = SalesPayment::whereDate('paid_on', today())->whereHas('sale', fn ($query) => $query->where('sold_by', $salesPersonId)->where('status', '!=', 'void'))->get();
                $priorCash = (float) SalesPayment::whereDate('paid_on', '<', today())->where('payment_method', 'cash')->whereHas('sale', fn ($query) => $query->where('sold_by', $salesPersonId)->where('status', '!=', 'void'))->sum('amount');
                $priorTransfers = (float) DailySalesBalance::where('sales_person_id', $salesPersonId)->whereDate('business_date', '<', today())->sum('amount_transferred');
                $opening = max(0, $priorCash - $priorTransfers);
                return [
                    'salesPerson'=>$sales->first()->seller,
                    'sales'=>(float)$sales->sum('total'),
                    'received'=>(float)$payments->sum('amount'),
                    'cashDue'=>$opening + (float)$payments->where('payment_method', 'cash')->sum('amount'),
                    'closed'=>DailySalesBalance::where('sales_person_id', $salesPersonId)->whereDate('business_date', today())->exists(),
                ];
            })->values();
        $pendingApprovalQuery = Approval::with(['approvable', 'submitter', 'steps'])
            ->where('status', 'pending')
            ->whereHas('steps', fn ($query) => $query->where('role_code', 'finance-manager')->where('status', 'pending'));
        $pendingApprovalCount = (clone $pendingApprovalQuery)->count();
        $pendingApprovals = $pendingApprovalQuery
            ->oldest('submitted_at')
            ->limit(5)
            ->get();

        return [
            'income'=>$income, 'expenses'=>$expenses, 'net'=>$income-$expenses,
            'receivables'=>$receivables,
            'payables'=>$payables,
            'budgeted'=>$budgeted, 'monthly'=>$monthly, 'expenseGroups'=>$expenseGroups,
            'topCustomers'=>$topCustomers, 'topProducts'=>$topProducts, 'ratios'=>$ratios,
            'pending'=>$pendingApprovalCount,
            'pendingApprovals'=>$pendingApprovals,
            'salesBalances'=>$salesBalances,
        ];
    }

    /**
     * Stand-in for modules that are seeded but not yet built, so the
     * sidebar never produces a broken link.
     */
    public function placeholder(Request $request, string $key): View
    {
        $user = $request->user();

        abort_unless($user->canAccess($key), 403, 'You do not have access to this screen.');

        $module = Module::where('key', $key)->firstOrFail();

        return view('module-placeholder', ['module' => $module]);
    }
}
