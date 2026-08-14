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
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        // Workers have no dashboard. Send them straight to their work.
        if ($user->isDataEntryOnly()) {
            return redirect()->route('daily.index');
        }

        return view('dashboard', [
            'greeting' => $this->greeting(),
            'panels'   => $this->panelsFor($user),
            'data'     => $this->dataFor($user),
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
        if ($isFinanceManager) return ['finance-dashboard'];
        if ($isPersonalAssistant) return ['pa-dashboard'];
        if ($isHrManager) return ['hr-dashboard'];
        if ($isCeo) $panels[] = 'executive';
        if ($isProductionManager) $panels[] = 'production-overview';

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

    private function dataFor(User $user): array
    {
        $data = [];
        $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code','ceo');
        $isFinanceManager = $this->isFinanceManager($user);
        $isPersonalAssistant = $user->role?->code === 'personal-assistant' || $user->roles->contains('code','personal-assistant');
        if ($isFinanceManager) $data['financeDashboard'] = $this->financeDashboard();
        if ($isPersonalAssistant) $data['paDashboard'] = $this->paDashboard();
        if ($user->role?->code === 'hr-manager' || $user->roles->contains('code', 'hr-manager')) {
            $data['hrDashboard'] = $this->hrDashboard();
        }
        if ($isCeo) {
            $month=FinancialTransaction::whereBetween('transaction_date',[now()->startOfMonth(),now()->endOfMonth()])->get();
            $data['executive']=['income'=>(float)$month->where('type','money_received')->sum('amount'),'spending'=>(float)$month->where('type','money_paid')->sum('amount'),'pending'=>Approval::where('status','pending')->whereHas('steps',fn($q)=>$q->where('status','pending')->where('role_code','ceo'))->count(),'sent'=>Approval::where('status','pending')->whereHas('steps',fn($q)=>$q->where('role_code','ceo')->where('status','approved')->where('decided_by',$user->id))->count(),'overdue'=>Approval::where('status','pending')->where('due_at','<',now())->count(),'purchaseExposure'=>(float)Purchase::whereIn('status',['submitted','finance_reviewed','ceo_approved'])->sum('total_cost'),'withdrawals'=>HealthEvent::where('withdrawal_ends_eggs','>=',now()->toDateString())->count()];
        }
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
            $stockItems=InventoryItem::query()->select('inventory_items.*')->selectSub(fn($q)=>$q->from('inventory_movements')->whereColumn('inventory_item_id','inventory_items.id')->selectRaw($balanceSql),'balance')->get();
            $data['stock']=['items'=>$stockItems->count(),'low'=>$stockItems->filter(fn($item)=>(float)$item->balance<=(float)$item->reorder_level)->count(),'feedLines'=>DB::table('feed_stock_movements')->select('unit_code','feed_name')->groupBy('unit_code','feed_name')->get()->count()];
        }

        $data['shortcuts'] = $this->shortcuts($user);

        return $data;
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
        return ['units'=>$units,'needsAttention'=>FarmUnitRecord::where('status','needs_attention')->count(),'activity'=>$units->sum('activity'),
            'feedPlan'=>$feedPlan, 'dailyFeedKg'=>(float)$feedPlan->sum('requiredKg'),
            'recordedFeedKg'=>(float)$feedPlan->sum('recordedKg'), 'feedShortageKg'=>(float)$feedPlan->sum('shortageKg'),
            'totalBirds'=>(int)$feedPlan->sum('birds')];
    }

    private function financeDashboard(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $month = FinancialTransaction::whereBetween('transaction_date', [$monthStart, $monthEnd])->get();
        $income = (float) $month->where('type', 'money_received')->sum('amount');
        $expenses = (float) $month->where('type', 'money_paid')->sum('amount');
        $monthly = collect(range(5, 0))->map(function ($offset) {
            $date = now()->startOfMonth()->subMonths($offset);
            $rows = FinancialTransaction::whereBetween('transaction_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->get();
            return ['label'=>$date->format('M'),'income'=>(float)$rows->where('type','money_received')->sum('amount'),'expenses'=>(float)$rows->where('type','money_paid')->sum('amount')];
        });
        $expenseGroups = $month->where('type','money_paid')->groupBy(fn($row)=>$row->counterparty ?: $row->description ?: 'Other')->map(fn($rows,$name)=>['name'=>$name,'amount'=>(float)$rows->sum('amount')])->sortByDesc('amount')->take(5)->values();
        $budgeted = (float) Budget::whereBetween('period_start', [$monthStart, $monthEnd])->sum('planned_amount');

        return [
            'income'=>$income, 'expenses'=>$expenses, 'net'=>$income-$expenses,
            'receivables'=>(float) Receivable::where('status','open')->selectRaw('coalesce(sum(amount-amount_paid),0) total')->value('total'),
            'payables'=>(float) Payable::where('status','open')->selectRaw('coalesce(sum(amount-amount_paid),0) total')->value('total'),
            'budgeted'=>$budgeted, 'monthly'=>$monthly, 'expenseGroups'=>$expenseGroups,
            'pending'=>Approval::where('status','pending')->count(),
            'recent'=>FinancialTransaction::latest('transaction_date')->latest('id')->limit(7)->get(),
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
