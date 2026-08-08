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
        $isProductionManager = $this->isProductionManager($user);
        if ($isCeo) $panels[] = 'executive';
        if ($isProductionManager) $panels[] = 'production-overview';

        // A worker's own tasks come before anything else on the farm.
        if ($user->canAccess('my-tasks') || $user->canAccess('my-work')) {
            $panels[] = 'my-work';
        }

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

        // Payroll data belongs to Finance. HR can maintain the staff records
        // that feed it, but does not need an empty finance summary on their
        // dashboard just because they can record salary advances.
        if (($user->canAccess('finance') || $user->canAccess('payroll'))
            && ! ($isHrManager && ! $user->canAccess('finance'))) {
            $panels[] = 'money';
        }

        if ($user->canAccess('inventory')) {
            $panels[] = 'stock';
        }

        // Nobody should land on an empty screen.
        if (! $panels) {
            $panels[] = 'welcome';
        }

        $panels[] = 'shortcuts';

        return $panels;
    }

    private function dataFor(User $user): array
    {
        $data = [];
        $isCeo = $user->role?->code === 'ceo' || $user->roles->contains('code','ceo');
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
                (object)['name'=>'Approval Queue','link'=>route('approvals.index'),'icon'=>'check-circle'],
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

    private function productionOverview(): array
    {
        $activityStart=now()->subDays(30)->toDateString();
        $units=collect([
            ['code'=>'poultry','label'=>'Poultry','icon'=>'🐔','value'=>(int)AnimalGroup::where('status','active')->sum('current_quantity'),'measure'=>'birds','activity'=>HealthEvent::where('occurred_on','>=',$activityStart)->count()],
            ['code'=>'goats','label'=>'Goats','icon'=>'🐐','value'=>LivestockAnimal::where('unit_code','goats')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','goats')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'rabbits','label'=>'Rabbits','icon'=>'🐇','value'=>LivestockAnimal::where('unit_code','rabbits')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','rabbits')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'dogs','label'=>'Dogs','icon'=>'🐕','value'=>LivestockAnimal::where('unit_code','dogs')->where('status','active')->count(),'measure'=>'animals','activity'=>FarmUnitRecord::where('unit_code','dogs')->where('record_date','>=',$activityStart)->count()],
            ['code'=>'garden','label'=>'Garden','icon'=>'🌱','value'=>FarmUnitRecord::where('unit_code','garden')->where('record_type','plot')->distinct('subject')->count('subject'),'measure'=>'plots','activity'=>FarmUnitRecord::where('unit_code','garden')->where('record_date','>=',$activityStart)->count()],
        ]);
        return ['units'=>$units,'needsAttention'=>FarmUnitRecord::where('status','needs_attention')->count(),'activity'=>$units->sum('activity')];
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
