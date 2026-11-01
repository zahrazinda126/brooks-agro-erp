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
use App\Models\BatchOrder;
use App\Models\BatchOrderPayment;
use App\Models\Breed;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function payableDetails(Request $request, Payable $payable): JsonResponse
    {
        abort_unless($request->user()->canAccess('payables'), 403);

        return response()->json([
            'item_name' => $payable->item_name,
            'quantity' => $payable->quantity,
            'unit' => $payable->unit,
            'lines' => $payable->lines()->with('breed:id,name')->get(['item_name','quantity','unit','breed_id','amount']),
        ]);
    }

    public function payables(Request $request): View
    {
        abort_unless($request->user()->canAccess('payables'), 403);
        $items = Payable::with(['lines.breed', 'payments' => fn ($query) => $query->with('recorder')->oldest('paid_date')->oldest('id')])
            ->when($request->filled('from'), fn($q)=>$q->whereDate('invoice_date','>=',$request->input('from')))
            ->when($request->filled('to'), fn($q)=>$q->whereDate('invoice_date','<=',$request->input('to')))
            ->latest('invoice_date')->paginate(30)->withQueryString();
        $outstanding = Payable::where('status', 'open')->get()->sum(fn (Payable $item) => (float) $item->amount - (float) $item->amount_paid);
        $suppliers = collect(config('finance_entry.suppliers', []))
            ->merge(Payable::query()->whereNotNull('supplier_name')->pluck('supplier_name'))
            ->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name))->sort()->values();
        $supplyItems = collect(config('finance_entry.purchase_sub_accounts', []))->flatten()
            ->merge(Payable::query()->whereNotNull('item_name')->pluck('item_name'))
            ->merge(\App\Models\PayableLine::query()->pluck('item_name'))
            ->merge(Payable::query()->pluck('description'))
            ->map(fn ($item) => trim((string) $item))->filter()->unique()->sort()->values();
        $batchOrders=BatchOrder::with(['payments','batch'])->latest('ordered_on')->get();
        $poultryBreeds = Breed::poultry()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        // One job per page: bills, bill form, batch orders, order form.
        // Two pages: bills and batch orders; each form opens as a pop-up over its list.
        // Old links (?tab=record / ?tab=order) open the matching pop-up; a failed save reopens it.
        $tab = in_array($request->query('tab'), ['orders', 'order'], true) || old('_dialog') === 'order' ? 'orders' : 'bills';
        $openDialog = old('_dialog') ?: ['record' => 'bill', 'order' => 'order'][$request->query('tab')] ?? null;
        $billableDeliveries = $request->user()->canDo('payables', 'create') ? \App\Support\FeedBillMatch::billable() : collect();
        $feedNames = \App\Http\Controllers\Operations\FeedStockController::feedNames();
        return view('finance.accounting.payables', compact('tab', 'openDialog', 'items', 'outstanding', 'suppliers','supplyItems','batchOrders', 'poultryBreeds', 'billableDeliveries', 'feedNames'));
    }

    public function storePayable(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('payables', 'create'), 403);
        $data = $request->validate(['supplier_name'=>['required','string','max:160'],'invoice_number'=>['nullable','string','max:80'],'invoice_date'=>['required','date'],'due_date'=>['nullable','date','after_or_equal:invoice_date'],'description'=>['nullable','string','max:1000'],'lines'=>['required','array','min:1'],'lines.*.item_name'=>['required','string','max:160'],'lines.*.quantity'=>['required','numeric','min:0.01'],'lines.*.unit'=>['required','string','max:40'],'lines.*.breed_id'=>['nullable','integer','exists:breeds,id'],'lines.*.amount'=>['required','numeric','min:0.01'],'construction_project_id'=>['nullable',\Illuminate\Validation\Rule::exists('construction_projects','id')->where('status','active')]]);
        $lines=collect($data['lines']);$total=(float)$lines->sum(fn($line)=>(float)$line['amount']);$first=$lines->first();
        // Notes are optional; the bill then describes itself from its items.
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: $lines->map(fn ($line) => $line['item_name'].' '.rtrim(rtrim(number_format((float) $line['quantity'], 2, '.', ''), '0'), '.').' '.$line['unit'])->implode(', ');
        // A feed bill names the deliveries it charges for; each feed of a delivery can be billed only once.
        $deliveryIds = $this->checkedFeedDeliveries($request, $lines);
        $payable = DB::transaction(function()use($data,$lines,$total,$first,$request,$deliveryIds){$payable=Payable::create(collect($data)->except('lines')->all()+['item_name'=>$first['item_name'],'quantity'=>$first['quantity'],'unit'=>$first['unit'],'amount'=>$total,'amount_paid'=>0,'status'=>'open','recorded_by'=>$request->user()->id]);$payable->lines()->createMany($lines->all());
            foreach ($deliveryIds as $id) DB::table('payable_feed_delivery')->insert(['payable_id'=>$payable->id,'feed_delivery_verification_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            return $payable;});
        $payable->load('lines');
        if (\App\Support\FeedBillMatch::needsCeo($payable)) {
            app(CommunicationService::class)->toRole('ceo', 'Feed bill charges more than the farm received', $payable->supplier_name.' bill '.($payable->invoice_number ?: '#'.$payable->id).' charges for more feed than the farm weighed. It is held until you approve the difference.', $request->user()->id, route('finance.payables.index', [], false));
            return redirect()->route('finance.payables.index')->with('status', 'Supplier bill recorded. It charges for more feed than the farm received, so it is held for the CEO before it can be paid.');
        }
        return redirect()->route('finance.payables.index')->with('status', 'Supplier bill recorded.');

        $data = $request->validate(['supplier_name'=>['required','string','max:160'],'invoice_number'=>['nullable','string','max:80'],'invoice_date'=>['required','date'],'due_date'=>['nullable','date','after_or_equal:invoice_date'],'item_name'=>['required','string','max:160'],'quantity'=>['required','numeric','min:0.01'],'unit'=>['required','string','max:40'],'description'=>['nullable','string','max:1000'],'amount'=>['required','numeric','min:1']]);
        $data['description'] = trim((string) $data['description']) ?: $data['item_name'].' · '.number_format((float) $data['quantity'], 2).' '.$data['unit'];
        Payable::create($data + ['amount_paid'=>0,'status'=>'open','recorded_by'=>$request->user()->id]);
        return redirect()->route('finance.payables.index')->with('status', 'Supplier bill recorded.');
    }

    /** Validates the deliveries chosen for a bill: required when the bill has feed, from the same supplier, and no feed billed twice. */
    private function checkedFeedDeliveries(Request $request, \Illuminate\Support\Collection $lines): array
    {
        $feedLines = $lines->filter(fn ($line) => \App\Support\FeedBillMatch::isFeedItem($line['item_name']));
        $ids = collect($request->input('feed_delivery_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($feedLines->isEmpty()) return [];
        $fail = fn (string $message) => throw \Illuminate\Validation\ValidationException::withMessages(['feed_delivery_ids' => $message]);
        if ($ids->isEmpty()) $fail('This bill is for feed: tick the delivery or deliveries it charges for.');
        $deliveries = DB::table('feed_delivery_verifications')->whereIn('id', $ids)->get()->keyBy('id');
        if ($deliveries->count() !== $ids->count()) $fail('One of the chosen deliveries no longer exists.');
        $supplier = mb_strtolower(trim((string) $request->input('supplier_name')));
        $billFeeds = $feedLines->map(fn ($line) => mb_strtolower(trim($line['item_name'])))->unique();
        foreach ($deliveries as $delivery) {
            $label = ($delivery->reference ?: 'Delivery').' of '.\Carbon\Carbon::parse($delivery->delivery_date)->format('d M Y');
            if (mb_strtolower(trim((string) $delivery->supplier)) !== $supplier) $fail("{$label} came from {$delivery->supplier}, not this supplier.");
            $already = DB::table('payable_feed_delivery')->join('payable_lines', 'payable_lines.payable_id', '=', 'payable_feed_delivery.payable_id')
                ->where('payable_feed_delivery.feed_delivery_verification_id', $delivery->id)->pluck('payable_lines.item_name')->map(fn ($n) => mb_strtolower($n));
            $twice = $billFeeds->intersect($already);
            if ($twice->isNotEmpty()) $fail("{$label}: ".ucwords($twice->implode(', ')).' is already on another bill.');
        }
        return $ids->all();
    }

    /** The CEO accepts a feed bill that charges for more than the farm weighed, so Finance can pay it. */
    public function approveFeedVariance(Request $request, Payable $payable): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperuser() || $user->role?->code === 'ceo' || $user->roles->contains('code', 'ceo'), 403, 'Only the CEO approves a feed bill difference.');
        $data = $request->validate(['variance_note' => ['required', 'string', 'max:1000']]);
        $payable->update(['variance_approved_by' => $user->id, 'variance_approved_at' => now(), 'variance_note' => $data['variance_note']]);
        AuditLog::record('feed_bill_variance_approved', $payable, 'CEO approved the difference between billed and received feed.', null, ['note' => $data['variance_note']], 'payables');
        if ($payable->recorded_by) app(CommunicationService::class)->toUser($payable->recorded_by, 'Feed bill difference approved', 'The CEO approved bill '.($payable->invoice_number ?: '#'.$payable->id).'. It can now be paid.', 'workflow', $user->id, route('finance.payables.index', [], false));
        return back()->with('status', 'Difference approved. Finance can now pay this bill.');
    }

    public function storeBatchOrder(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('payables','create'),403);
        $data=$request->validate(['batch_type'=>['required','in:poultry,goats,rabbits,dogs,sheep,other_livestock'],'supplier_name'=>['required','string','max:160'],'breed'=>['nullable','string','max:120'],'quantity_ordered'=>['required','integer','min:1'],'order_total'=>['required','numeric','min:1'],'ordered_on'=>['required','date','before_or_equal:today'],'expected_on'=>['nullable','date','after_or_equal:ordered_on'],'notes'=>['nullable','string','max:2000']]);
        $next=(int)BatchOrder::max('id')+1;
        BatchOrder::create($data+['order_number'=>'BORD-'.now()->format('Y').'-'.str_pad((string)$next,5,'0',STR_PAD_LEFT),'status'=>'ordered','recorded_by'=>$request->user()->id]);
        return redirect()->route('finance.payables.index', ['tab' => 'orders'])->with('status','Pre-arrival batch order recorded. Payments can now be added in stages.');
    }

    public function payBatchOrder(Request $request, BatchOrder $batchOrder): RedirectResponse
    {
        abort_unless($request->user()->canDo('transactions','create'),403);
        $batchOrder->load('payments');
        $data=$request->validate(['paid_on'=>['required','date','before_or_equal:today'],'amount'=>['required','numeric','min:1','max:'.$batchOrder->balance()],'payment_method'=>['required','in:cash,bank,agent,mtn_merchant,airtel_merchant,mobile_money,cheque'],'farm_account'=>['required','string','max:120'],'reference'=>['nullable','string','max:100']]);
        DB::transaction(function()use($data,$batchOrder,$request){
            BatchOrderPayment::create($data+['batch_order_id'=>$batchOrder->id,'recorded_by'=>$request->user()->id]);
            FinancialTransaction::create(['transaction_date'=>$data['paid_on'],'type'=>'money_paid','transaction_category'=>'batch_prepayment','account_group'=>'Current Assets','entry_account'=>'Supplier Advances','account'=>$data['farm_account'],'counterparty'=>$batchOrder->supplier_name,'amount'=>$data['amount'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference']?:$batchOrder->order_number,'description'=>'Pre-arrival payment for '.$batchOrder->order_number,'recorded_by'=>$request->user()->id]);
            $batchOrder->load('payments');
            $batchOrder->update(['status'=>$batchOrder->balance()<=0?'paid_before_arrival':'partly_paid']);
        });
        return back()->with('status','Batch-order payment recorded. The remaining balance is retained.');
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
        // The CEO and Director receive the budget from Finance: a week still in draft is not shown to them.
        $viewerRoles = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        $awaitingFinance = $viewerRoles->intersect(['ceo', 'director'])->isNotEmpty() && ! $viewerRoles->contains('finance-manager')
            && ($allItems->first()?->status ?? 'draft') === 'draft';
        $notSentToFinance = collect();
        if ($awaitingFinance) {
            // Departments with lines this week that have not yet sent their draft to Finance.
            $sent = \Illuminate\Support\Facades\DB::table('weekly_budget_submissions')->whereDate('week_start', $start)->pluck('department_id');
            $notSentToFinance = $allItems->pluck('department')->filter()->unique('id')
                ->reject(fn ($department) => $sent->contains($department->id) || $department->code === 'finance')
                ->pluck('name')->sort()->values();
            $allItems = collect();
            $availablePeriods = $availablePeriods->where('status', '!=', 'draft')->values();
        }
        $unit=$request->query('unit','all');
        $needles=['poultry'=>['poultry'],'kituntu'=>['kituntu'],'kituntu-tomatoes'=>['kituntu tomato'],'kituntu-passion'=>['kituntu passion'],'goats'=>['goat'],'rabbits'=>['rabbit'],'dogs'=>['dog'],'staff-welfare'=>['staff welfare'],'administration'=>['administration']];
        $items=isset($needles[$unit])?$allItems->filter(fn($item)=>collect($needles[$unit])->contains(fn($needle)=>str_contains(strtolower($item->category),$needle)))->values():$allItems;
        $approval = $allItems->isEmpty() ? null : Approval::with(['submitter', 'decisionMaker', 'steps.decisionMaker'])
            ->where('approvable_type', Budget::class)
            ->whereIn('approvable_id', $allItems->pluck('id'))
            ->latest('submitted_at')->first();
        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        // Finance edits its draft; the CEO only adjusts once Finance has submitted it and it is at the CEO stage.
        $canAdjustBudget = ($roleCodes->contains('finance-manager') && ($allItems->first()?->status ?? 'draft') === 'draft')
            || ($roleCodes->contains('ceo') && ($allItems->first()?->status ?? 'draft') === 'submitted' && $approval?->currentStep()?->role_code === 'ceo');

        return view('finance.accounting.budgets', [
            'items'=>$items, 'plannedTotal'=>$items->sum('planned_amount'), 'allTotal'=>$allItems->sum('planned_amount'),
            'unit'=>$unit, 'budgetStatus'=>$allItems->first()?->status ?? 'draft', 'approval'=>$approval,
            'periodStart'=>$allItems->first()?->period_start, 'periodEnd'=>$allItems->first()?->period_end,
            'selectedWeek'=>$start, 'availablePeriods'=>$availablePeriods,
            'isCurrentWeek'=>$start === now()->startOfWeek()->toDateString(),
            'canAdjustBudget'=>$canAdjustBudget,
            'isCeo'=>$roleCodes->contains('ceo'), 'isFinance'=>$roleCodes->contains('finance-manager'), 'awaitingFinance'=>$awaitingFinance, 'notSentToFinance'=>$notSentToFinance,
            'departmentTotals'=>$allItems->groupBy(fn($line)=>$line->department?->name ?? 'Unassigned')->map(fn($lines)=>['lines'=>$lines->count(),'total'=>$lines->sum('planned_amount'),'department_id'=>$lines->first()->department_id]),
            // Which departments have submitted their draft for this week (and whether it changed since).
            'submissions'=>\Illuminate\Support\Facades\DB::table('weekly_budget_submissions')->whereDate('week_start',$start)->get()->keyBy('department_id'),
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
        $ceoMayEdit = $roleCodes->contains('ceo') && $budget->status === 'submitted' && $approval?->currentStep()?->role_code === 'ceo';
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
        // Only Finance submits the combined weekly budget; the CEO receives it for review.
        abort_unless($request->user()->roles->pluck('code')->push($request->user()->role?->code)->contains('finance-manager'), 403, 'Finance submits the weekly budget.');
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
        $f = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'max:30'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        // One filter row narrows both the list and the category totals.
        $filtered = fn () => FixedAsset::query()
            ->when($f['q'] ?? null, fn ($q, $term) => $q->where(fn ($x) => $x->whereRaw('lower(name) like ?', ['%'.mb_strtolower($term).'%'])->orWhereRaw('lower(asset_tag) like ?', ['%'.mb_strtolower($term).'%'])->orWhereRaw('lower(location) like ?', ['%'.mb_strtolower($term).'%'])))
            ->when($f['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('purchase_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('purchase_date', '<=', $d));
        return view('finance.accounting.assets', [
            'items'=>$filtered()->latest('purchase_date')->paginate(30)->withQueryString(),
            'categories'=>FixedAsset::CATEGORIES,
            'statuses'=>FixedAsset::query()->distinct()->orderBy('status')->pluck('status'),
            'filters'=>$f,
            'categoryTotals'=>$filtered()->selectRaw('category, count(*) as asset_count, sum(purchase_cost) as total_cost')->groupBy('category')->orderBy('category')->get(),
        ]);
    }

    /** Registering an asset has its own page, with the farm asset catalogue to pick from. */
    public function createAsset(Request $request): View
    {
        abort_unless($request->user()->canDo('assets', 'create'), 403);
        return view('finance.accounting.assets-create', ['categories'=>FixedAsset::CATEGORIES, 'commonAssets'=>FixedAsset::COMMON_FARM_ASSETS]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('assets', 'create'), 403);
        $data=$request->validate(['name'=>['required','string','max:160'],'asset_tag'=>['nullable','string','max:80'],'category'=>['required',\Illuminate\Validation\Rule::in(array_keys(FixedAsset::CATEGORIES))],'purchase_date'=>['required','date'],'purchase_cost'=>['required','numeric','min:1'],'useful_life_years'=>['nullable','integer','min:1','max:100'],'location'=>['nullable','string','max:160'],'notes'=>['nullable','string','max:1000']]);
        FixedAsset::create($data+['status'=>'active','recorded_by'=>$request->user()->id]); return redirect()->route('finance.assets.index')->with('status','Fixed asset recorded.');
    }
}
