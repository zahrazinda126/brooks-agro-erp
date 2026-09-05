<?php

namespace App\Http\Controllers\Finance;

use App\Services\CommunicationService;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\FixedAsset;
use App\Models\Payable;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function payables(Request $request): View
    {
        abort_unless($request->user()->canAccess('payables'), 403);
        $items = Payable::query()->latest('invoice_date')->paginate(30);
        $outstanding = Payable::where('status', 'open')->get()->sum(fn (Payable $item) => (float) $item->amount - (float) $item->amount_paid);
        return view('finance.accounting.payables', compact('items', 'outstanding'));
    }

    public function storePayable(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('payables', 'create'), 403);
        $data = $request->validate(['supplier_name'=>['required','string','max:160'],'invoice_number'=>['nullable','string','max:80'],'invoice_date'=>['required','date'],'due_date'=>['nullable','date','after_or_equal:invoice_date'],'description'=>['required','string','max:1000'],'amount'=>['required','numeric','min:1']]);
        Payable::create($data + ['amount_paid'=>0,'status'=>'open','recorded_by'=>$request->user()->id]);
        return back()->with('status', 'Supplier bill recorded.');
    }

    public function budgets(Request $request): View
    {
        abort_unless($request->user()->canAccess('budget'), 403);
        $start = $request->date('week')?->startOfWeek()->toDateString()
            ?? now()->startOfWeek()->toDateString();
        $availablePeriods = Budget::query()
            ->select('period_start', 'period_end', 'status')
            ->selectRaw('count(*) as line_count, sum(planned_amount) as planned_total')
            ->groupBy('period_start', 'period_end', 'status')
            ->orderByDesc('period_start')
            ->get();
        $allItems=Budget::with('department')->whereDate('period_start',$start)->orderBy('category')->orderBy('id')->get();
        $unit=$request->query('unit','all');
        $needles=['poultry'=>['poultry'],'kituntu'=>['kituntu'],'kituntu-tomatoes'=>['kituntu tomato'],'kituntu-passion'=>['kituntu passion'],'goats'=>['goat'],'rabbits'=>['rabbit'],'dogs'=>['dog'],'staff-welfare'=>['staff welfare'],'administration'=>['administration']];
        $items=isset($needles[$unit])?$allItems->filter(fn($item)=>collect($needles[$unit])->contains(fn($needle)=>str_contains(strtolower($item->category),$needle)))->values():$allItems;
        $approval = $allItems->isEmpty() ? null : Approval::with(['submitter', 'decisionMaker', 'steps.decisionMaker'])
            ->where('approvable_type', Budget::class)
            ->whereIn('approvable_id', $allItems->pluck('id'))
            ->latest('submitted_at')->first();
        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        $canAdjustBudget = ($roleCodes->contains('finance-manager') && ($allItems->first()?->status ?? 'draft') === 'draft')
            || ($roleCodes->contains('ceo') && in_array(($allItems->first()?->status ?? 'draft'), ['draft', 'submitted'], true)
                && (($allItems->first()?->status ?? 'draft') === 'draft' || $approval?->currentStep()?->role_code === 'ceo'));

        return view('finance.accounting.budgets', [
            'items'=>$items, 'plannedTotal'=>$items->sum('planned_amount'), 'allTotal'=>$allItems->sum('planned_amount'),
            'unit'=>$unit, 'budgetStatus'=>$allItems->first()?->status ?? 'draft', 'approval'=>$approval,
            'periodStart'=>$allItems->first()?->period_start, 'periodEnd'=>$allItems->first()?->period_end,
            'selectedWeek'=>$start, 'availablePeriods'=>$availablePeriods,
            'isCurrentWeek'=>$start === now()->startOfWeek()->toDateString(),
            'canAdjustBudget'=>$canAdjustBudget,
            'departmentTotals'=>$allItems->groupBy(fn($line)=>$line->department?->name ?? 'Unassigned')->map(fn($lines)=>['lines'=>$lines->count(),'total'=>$lines->sum('planned_amount')]),
        ]);
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'create'), 403);
        $data=$request->validate(['category'=>['required','string','max:120'],'item'=>['required','string','max:160'],'unit'=>['nullable','string','max:40'],'quantity'=>['required','numeric','min:0.01'],'unit_price'=>['required','numeric','min:0'],'period_start'=>['required','date'],'period_end'=>['required','date','after_or_equal:period_start'],'notes'=>['nullable','string','max:1000']]); $data['planned_amount']=$data['quantity']*$data['unit_price'];
        abort_if(Budget::where('period_start', $data['period_start'])->where('status', '!=', 'draft')->exists(), 422, 'An approved or submitted budget cannot be changed.');
        Budget::create($data+['department_id'=>$this->financeDepartmentId(),'category'=>'Finance & Accounting','recorded_by'=>$request->user()->id]); return back()->with('status','Finance budget line recorded.');
    }

    public function updateBudget(Request $request, Budget $budget): RedirectResponse
    {
        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        $approval = Approval::with('steps')->where('approvable_type', Budget::class)
            ->whereIn('approvable_id', Budget::whereDate('period_start', $budget->period_start)->pluck('id'))
            ->latest('submitted_at')->first();
        $financeMayEdit = $roleCodes->contains('finance-manager') && $budget->status === 'draft';
        $ceoMayEdit = $roleCodes->contains('ceo') && ($budget->status === 'draft'
            || ($budget->status === 'submitted' && $approval?->currentStep()?->role_code === 'ceo'));
        abort_unless($financeMayEdit || $ceoMayEdit, 403, 'This budget is not at a stage where you may adjust it.');

        $data=$request->validate([
            'quantity'=>['required','numeric','min:0'],
            'unit_price'=>['required','numeric','min:0'],
            'adjustment_note'=>['required','string','max:1000'],
        ]);
        $old = $budget->only(['quantity','unit_price','planned_amount']);
        $new = [
            'quantity'=>$data['quantity'],
            'unit_price'=>$data['unit_price'],
            'planned_amount'=>(float)$data['quantity']*(float)$data['unit_price'],
        ];
        $budget->update($new);
        AuditLog::record('budget_line_adjusted', $budget,
            $request->user()->displayName().' adjusted '.$budget->item.'. Reason: '.$data['adjustment_note'],
            $old, $new + ['adjustment_note'=>$data['adjustment_note']], 'budget');

        return back()->with('status','Budget line adjusted and recorded in the audit trail.');
    }

    public function updateWeeklyBudget(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        $data=$request->validate(['lines'=>['required','array'],'lines.*.quantity'=>['required','numeric','min:0'],'lines.*.unit_price'=>['required','numeric','min:0']]);
        $budgets = Budget::whereIn('id', array_keys($data['lines']))->get()->keyBy('id');
        if ($budgets->count() !== count($data['lines']) || $budgets->contains(fn (Budget $budget) => $budget->status !== 'draft' || $budget->department_id !== $this->financeDepartmentId())) {
            return back()->withErrors(['budget'=>'Finance can edit only its own department draft.']);
        }
        foreach($data['lines'] as $id=>$line) { $budgets[$id]->update(['quantity'=>$line['quantity'],'unit_price'=>$line['unit_price'],'planned_amount'=>(float)$line['quantity']*(float)$line['unit_price']]); }
        return back()->with('status','Weekly budget saved.');
    }
    public function submitBudget(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        $start = $request->validate(['week'=>['nullable','date']])['week'] ?? now()->startOfWeek()->toDateString();
        $budgets = Budget::whereDate('period_start', $start)->where('status', 'draft')->get();
        $submissionNumber = $budgets->isEmpty() ? 1 : Approval::where('approvable_type', Budget::class)->whereIn('approvable_id', $budgets->pluck('id'))->count() + 1;
        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        $submittedByFinanceManager = $roleCodes->contains('finance-manager');

        \Illuminate\Support\Facades\DB::transaction(function () use ($budgets, $request, $submissionNumber, $submittedByFinanceManager) {
            foreach ($budgets as $budget) {
                $budget->update(['status' => 'submitted', 'submitted_at' => now()]);
            }

            if ($budgets->isNotEmpty()) {
                $approval = $budgets->first()->approvals()->create([
                    'module_key' => 'budget', 'department_id' => $request->user()->department_id,
                    'submitted_by' => $request->user()->id, 'submitted_at' => now(),
                    'submission_note' => 'Submission '.$submissionNumber.': '.$budgets->count().' budget lines submitted for executive review.',
                    'due_at' => now()->addDays(2), 'required_levels' => 3, 'level'=>$submittedByFinanceManager ? 2 : 1,
                ]);
                $approval->steps()->createMany([
                    ['stage'=>1, 'role_code'=>'finance-manager', 'status'=>$submittedByFinanceManager ? 'approved' : 'pending', 'decided_by'=>$submittedByFinanceManager ? $request->user()->id : null, 'decided_at'=>$submittedByFinanceManager ? now() : null, 'decision_note'=>$submittedByFinanceManager ? 'Finance Manager submitted and confirmed the weekly budget.' : null],
                    ['stage'=>2, 'role_code'=>'ceo', 'status'=>$submittedByFinanceManager ? 'pending' : 'waiting'],
                    ['stage'=>3, 'role_code'=>'director', 'status'=>'waiting'],
                ]);
                app(CommunicationService::class)->toRole(
                    $submittedByFinanceManager ? 'ceo' : 'finance-manager',
                    'Weekly budget awaiting your review',
                    'Submission '.$submissionNumber.' contains '.$budgets->count().' budget lines and is ready for your decision.',
                    $request->user()->id
                );
            }
        });

        return back()->with('status', $budgets->isNotEmpty() ? ($submittedByFinanceManager ? 'Weekly budget sent to the CEO for review.' : 'Weekly budget sent to the Finance Manager for review.') : 'There is no draft budget to submit.');
    }

    public function reviseBudget(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        $start = $request->validate(['week'=>['required','date']])['week'];
        $budgets = Budget::whereDate('period_start', $start)->where('status', 'rejected')->get();
        if ($budgets->isEmpty()) {
            return redirect()->route('finance.budgets.index', ['week'=>$start])
                ->withErrors(['budget'=>'This budget is no longer rejected and cannot be reopened for revision.']);
        }

        Budget::whereIn('id', $budgets->pluck('id'))->update(['status'=>'draft', 'submitted_at'=>null, 'funding_status'=>'pending', 'funded_amount'=>null, 'funding_note'=>null, 'funding_decided_by'=>null, 'funding_decided_at'=>null, 'updated_at'=>now()]);
        AuditLog::record('budget_revision_opened', $budgets->first(), 'Rejected weekly budget reopened as a draft for correction.', ['status'=>'rejected'], ['status'=>'draft'], 'budget');

        return redirect()->route('finance.budgets.index', ['week'=>$start])
            ->with('status', 'Budget reopened as a draft. Correct the rejected items, save, and submit it for approval again.');
    }

    public function seedWeeklyBudget(Request $request): RedirectResponse
    {
        abort(403, 'Production templates must be prepared by the Production department.');
        abort_unless($request->user()->canDo('budget', 'create'), 403); $template=$request->validate(['template'=>['required','in:kituntu,nti']])['template']; $start=now()->startOfWeek()->toDateString(); $end=now()->endOfWeek()->toDateString(); abort_if(Budget::where('period_start',$start)->where('status','!=','draft')->exists(), 422, 'An approved or submitted budget cannot be changed.');
        $items=$template==='kituntu' ? [['Kituntu Tomato Garden','Mancozeb','kgs',20,17500],['Kituntu Tomato Garden','Coppermate','kgs',6,32000],['Kituntu Tomato Garden','Mistress','kgs',6,40000],['Kituntu Tomato Garden','Green Miracle','litres',6,30000],['Kituntu Tomato Garden','Iron Force','litres',3,23000],['Kituntu Tomato Garden','Labour for cutting mulches','trips',1,1000000],['Kituntu Tomato Garden','Transport for Kalimbwe','trip',1,150000],['Kituntu Tomato Garden','Drums','pcs',10,100000],['Kituntu Tomato Garden','Cabbage seeds','sachets',1,85000],['Kituntu Passion Fruits','Enkoddo','pcs',1000,3000],['Kituntu Passion Fruits','Biocure','litres',10,23000],['Kituntu Passion Fruits','Nimbercidine','litres',10,32000],['Kituntu Passion Fruits','NPK 17','bags',5,150000],['Kituntu Passion Fruits','CAN','bags',2,150000],['Kituntu Passion Fruits','Winner','bags',1.5,185000],['Kituntu Passion Fruits','Power','bags',1,190000]] : [['NTI Goat Unit','Fuel for the machine','litres',4,8750],['NTI Goat Unit','Mineral block','blocks',4,10000],['NTI Goat Unit','Gloves','bottle',1,26000],['NTI Goat Unit','Maize stalk transportation','trips',3,50000],['NTI Goat Unit','Penstrep','bottle',1,15000],['NTI Dogs Unit','Dog porridge','kgs',50,2000],['NTI Dogs Unit','Classic Milk','box',1,38000],['NTI Dogs Unit','Bravo Puppy feeds','box',25,10000],['NTI Dogs Unit','Disinfectant','5 litre jerrycan',1,60000],['NTI Dogs Unit','Liquid soap','jerrycan',1,30000]];
        foreach($items as [$category,$item,$unit,$quantity,$price]) Budget::create(['category'=>$category,'item'=>$item,'unit'=>$unit,'quantity'=>$quantity,'unit_price'=>$price,'planned_amount'=>$quantity*$price,'period_start'=>$start,'period_end'=>$end,'recorded_by'=>$request->user()->id]); return back()->with('status','Weekly budget template loaded. Edit prices, remove unneeded lines, or add exceptions.');
    }
    public function destroyBudget(Request $request, Budget $budget): RedirectResponse { abort_unless($request->user()->canDo('budget','edit') || $request->user()->canDo('budget','delete'),403); abort_unless($budget->status === 'draft' && $budget->department_id === $this->financeDepartmentId(), 403, 'Finance cannot remove another department’s draft.'); $budget->delete(); return back()->with('status','Budget item removed.'); }

    private function financeDepartmentId(): ?int { return Department::where('code','finance')->value('id'); }

    public function assets(Request $request): View
    {
        abort_unless($request->user()->canAccess('assets'), 403);
        return view('finance.accounting.assets', [
            'items'=>FixedAsset::query()->latest('purchase_date')->paginate(30),
            'assetValue'=>FixedAsset::where('status','active')->sum('purchase_cost'),
            'categories'=>FixedAsset::CATEGORIES,
            'commonAssets'=>FixedAsset::COMMON_FARM_ASSETS,
            'categoryTotals'=>FixedAsset::where('status','active')->selectRaw('category, count(*) as asset_count, sum(purchase_cost) as total_cost')->groupBy('category')->orderBy('category')->get(),
        ]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('assets', 'create'), 403);
        $data=$request->validate(['name'=>['required','string','max:160'],'asset_tag'=>['nullable','string','max:80'],'category'=>['required',\Illuminate\Validation\Rule::in(array_keys(FixedAsset::CATEGORIES))],'purchase_date'=>['required','date'],'purchase_cost'=>['required','numeric','min:1'],'useful_life_years'=>['nullable','integer','min:1','max:100'],'location'=>['nullable','string','max:160'],'notes'=>['nullable','string','max:1000']]);
        FixedAsset::create($data+['status'=>'active','recorded_by'=>$request->user()->id]); return back()->with('status','Fixed asset recorded.');
    }
}
