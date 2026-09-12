<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PayrollRun;
use App\Models\Sale;
use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('approvals') || ApprovalDelegation::current()->where('delegate_id',$request->user()->id)->exists(), 403);
        $status = $request->string('status')->toString() ?: 'pending';
        abort_unless(in_array($status, ['pending', 'sent', 'approved', 'rejected'], true), 404);

        $query = Approval::with(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker'])
            ->when($status !== 'sent', fn($q) => $q->where('status', $status))
            ->when($status === 'sent', fn($q) => $q->where('status','pending')->whereHas('steps', fn($s) => $s->where('stage',1)->where('status','approved')->where('decided_by',$request->user()->id)))
            ->latest('submitted_at');

        if ($status === 'pending') {
            $roleCodes = $this->effectiveRoleCodes($request);
            if ($roleCodes->contains('director')) {
                // The Director can track every submitted request that is routed to
                // their stage, even while an earlier approval level is outstanding.
                // The detail page still prevents a decision until the Director's
                // own step becomes the current pending step.
                $query->whereHas('steps', fn ($q) => $q
                    ->where('role_code', 'director')
                    ->whereIn('status', ['waiting', 'pending']));
            } else {
                $query->whereHas('steps', fn ($q) => $q
                    ->where('status', 'pending')
                    ->whereIn('role_code', $roleCodes));
            }
        }

        if ($request->filled('type')) {
            $query->where('module_key', $request->string('type')->toString());
        }

        $ownApprovalRoles=$request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->intersect(['finance-manager','ceo','director'])->unique();
        return view('approvals.index', ['approvals' => $query->paginate(20)->withQueryString(), 'status' => $status,
            'ownApprovalRoles'=>$ownApprovalRoles,
            'delegations'=>ApprovalDelegation::with('delegate')->where('delegator_id',$request->user()->id)->latest()->get(),
            'delegateUsers'=>User::where('is_active',true)->whereKeyNot($request->user()->id)->orderBy('name')->get(),
        ]);
    }

    public function decide(Request $request, Approval $approval): RedirectResponse
    {
        $approval->load('steps');
        $step = $approval->currentStep();
        $roleCodes = $this->effectiveRoleCodes($request);
        abort_unless($step && $roleCodes->contains($step->role_code), 403, 'This approval is waiting for another role or an active delegate.');
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['decision'] === 'rejected' && blank($data['decision_note'] ?? null)) {
            return back()->withErrors(['decision_note' => 'Explain what must be corrected before rejecting the budget.'])->withInput();
        }
        abort_unless($approval->status === 'pending', 422, 'This request has already been decided.');

        DB::transaction(function () use ($approval, $data, $request) {
            $old = ['status' => $approval->status, 'level' => $approval->level];
            $step = $approval->currentStep();

            if ($data['decision'] === 'approved' && $approval->approvable instanceof Budget && in_array($step->role_code, ['ceo', 'director'], true)) {
                $pendingLines = Budget::whereDate('period_start', $approval->approvable->period_start)
                    ->where('funding_status', 'pending')->get();
                foreach ($pendingLines as $line) {
                    $line->update([
                        'funding_status'=>'funded', 'funded_amount'=>$line->planned_amount,
                        'funding_note'=>'Funded by default with stage approval.',
                        'funding_decided_by'=>$request->user()->id, 'funding_decided_at'=>now(),
                    ]);
                    $line->fundingDecisions()->create([
                        'approval_id'=>$approval->id, 'approval_step_id'=>$step->id,
                        'decision'=>'funded', 'funded_amount'=>$line->planned_amount,
                        'note'=>'Funded by default with stage approval.',
                        'decided_by'=>$request->user()->id, 'decided_at'=>now(),
                    ]);
                }
            }
            $step->update(['status'=>$data['decision'], 'decision_note'=>$data['decision_note'] ?? null,
                'decided_by'=>$request->user()->id, 'decided_at'=>now()]);

            $next = $approval->steps->firstWhere('stage', $step->stage + 1);
            $isFinalApproval = $data['decision'] === 'approved' && ! $next;
            if ($data['decision'] === 'rejected') {
                $approval->steps()->where('status', 'waiting')->update(['status'=>'cancelled']);
                $approval->update(['status'=>'rejected', 'decided_by'=>$request->user()->id,
                    'decided_at'=>now(), 'decision_note'=>$data['decision_note'] ?? null]);
            } elseif ($isFinalApproval) {
                $approval->update(['status'=>'approved', 'level'=>$step->stage, 'decided_by'=>$request->user()->id,
                    'decided_at'=>now(), 'decision_note'=>$data['decision_note'] ?? null]);
            } else {
                $next->update(['status'=>'pending']);
                $approval->update(['status'=>'pending', 'level'=>$next->stage, 'due_at'=>now()->addDays(2)]);
            }

            if ($approval->approvable instanceof Budget) {
                Budget::where('period_start', $approval->approvable->period_start)
                    ->update(['status' => $data['decision'] === 'rejected' ? 'rejected' : ($isFinalApproval ? 'approved' : 'submitted')]);
            }
            if ($approval->approvable instanceof Purchase) {
                $purchaseStatus = match (true) {
                    $data['decision'] === 'rejected' => 'rejected',
                    $isFinalApproval => 'approved',
                    $step->role_code === 'finance-manager' => 'finance_reviewed',
                    default => 'ceo_approved',
                };
                $approval->approvable->update(['status'=>$purchaseStatus]);
            }
            if ($approval->approvable instanceof PayrollRun) {
                $payrollStatus = match (true) {
                    $data['decision'] === 'rejected' => 'rejected',
                    $isFinalApproval => 'approved',
                    $step->role_code === 'finance-manager' => 'finance_reviewed',
                    default => 'ceo_reviewed',
                };
                $approval->approvable->update(['status'=>$payrollStatus]);
            }
            if ($approval->approvable instanceof Sale) {
                $approval->approvable->update(['approval_status'=>$data['decision']==='rejected' ? 'rejected' : ($isFinalApproval ? 'approved' : 'pending')]);
            }

            AuditLog::record('approval_decided', $approval,
                "{$request->user()->displayName()} {$data['decision']} {$approval->label()}.",
                $old, ['decision' => $data['decision'], 'stage'=>$step->stage, 'decision_note' => $data['decision_note'] ?? null], 'approvals');

            $communications = app(CommunicationService::class);
            if ($data['decision'] === 'rejected' || $isFinalApproval) {
                $communications->toUser(
                    $approval->submitted_by,
                    $data['decision'] === 'rejected' ? 'Request returned for correction' : 'Request fully approved',
                    $approval->label().($data['decision'] === 'rejected'
                        ? ' was rejected by '.$request->user()->displayName().'. Reason: '.($data['decision_note'] ?? 'No reason supplied.')
                        : ' completed all approval stages.'),
                    'workflow', $request->user()->id
                );
            } elseif ($next) {
                $communications->toRole(
                    $next->role_code, 'Approval awaiting your decision',
                    $approval->label().' was approved by '.$request->user()->displayName().' and has reached your stage.',
                    $request->user()->id
                );
            }
        });

        $approval->refresh();
        $nextStep = $approval->currentStep();
        $nextRole = $nextStep ? match ($nextStep->role_code) {
            'finance-manager' => 'Finance Manager',
            'ceo' => 'CEO',
            'director' => 'Director',
            default => ucfirst(str_replace('-', ' ', $nextStep->role_code)),
        } : null;
        $message = $data['decision'] === 'rejected'
            ? 'Request rejected.'
            : ($nextRole ? "Stage approved and sent to the {$nextRole}." : 'Final approval recorded.');

        return back()->with('status', $message);
    }

    public function show(Request $request, Approval $approval): View
    {
        abort_unless($request->user()->canAccess('approvals') || ApprovalDelegation::current()->where('delegate_id',$request->user()->id)->exists(), 403);
        $approval->load(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker', 'comments.user']);

        $budgetLines = collect();
        if ($approval->approvable instanceof Budget) {
            $budgetLines = Budget::with(['fundingDecisionMaker', 'fundingDecisions.decisionMaker', 'fundingDecisions.step'])
                ->where('period_start', $approval->approvable->period_start)
                ->orderBy('category')->orderBy('id')->get();
        }

        $roleCodes = $this->effectiveRoleCodes($request);
        $currentStep = $approval->currentStep();
        $canActAtThisStage = $approval->status === 'pending'
            && $currentStep && $roleCodes->contains($currentStep->role_code);
        $canManageFunding = $request->user()->canDo('approvals', 'approve') && (
            ($approval->status === 'pending' && $currentStep && in_array($currentStep->role_code, ['ceo','director'], true) && $roleCodes->contains($currentStep->role_code))
            || ($approval->status === 'approved' && $roleCodes->contains('director'))
        );
        $fundingSummary = [
            'requested'=>(float) $budgetLines->sum('planned_amount'),
            'funded'=>(float) $budgetLines->sum(fn (Budget $line) => (float) ($line->funded_amount ?? 0)),
            'unfunded'=>$budgetLines->where('funding_status','unfunded')->count(),
            'cancelled'=>$budgetLines->where('funding_status','cancelled')->count(),
            'pending'=>$budgetLines->where('funding_status','pending')->count(),
        ];
        $budgetSections = $budgetLines->groupBy('category')->map(function ($lines, $category) {
            return [
                'category'=>$category,
                'items'=>$lines->count(),
                'requested'=>(float) $lines->sum('planned_amount'),
                'funded'=>(float) $lines->sum(fn (Budget $line) => (float) ($line->funded_amount ?? 0)),
                'unfunded'=>$lines->where('funding_status','unfunded')->count(),
                'cancelled'=>$lines->where('funding_status','cancelled')->count(),
            ];
        })->values();

        return view('approvals.show', compact('approval', 'budgetLines', 'budgetSections', 'currentStep', 'canActAtThisStage', 'canManageFunding', 'fundingSummary'));
    }

    public function updateBudgetFunding(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless($request->user()->canDo('approvals', 'approve'), 403);
        $approval->load(['approvable', 'steps']);
        abort_unless($approval->approvable instanceof Budget, 404);

        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        $step = $approval->currentStep();
        $canActDuringReview = $approval->status === 'pending' && $step
            && in_array($step->role_code, ['ceo', 'director'], true)
            && $roleCodes->contains($step->role_code);
        $canReleaseAfterApproval = $approval->status === 'approved' && $roleCodes->contains('director');
        abort_unless($canActDuringReview || $canReleaseAfterApproval, 403, 'Line funding decisions are waiting for the CEO or Director.');

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.decision' => ['required', 'in:funded,partial,unfunded,cancelled'],
            'lines.*.funded_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        $periodLines = Budget::whereDate('period_start', $approval->approvable->period_start)
            ->whereIn('id', array_keys($data['lines']))->get()->keyBy('id');
        abort_unless($periodLines->count() === count($data['lines']), 422, 'One or more budget lines do not belong to this approval.');

        foreach ($data['lines'] as $id => $lineData) {
            $requested = (float) $periodLines[$id]->planned_amount;
            $amount = match ($lineData['decision']) {
                'funded' => $requested,
                'unfunded', 'cancelled' => 0,
                default => (float) ($lineData['funded_amount'] ?? 0),
            };
            if ($lineData['decision'] === 'partial' && ($amount <= 0 || $amount >= $requested)) {
                return back()->withErrors(["lines.{$id}.funded_amount"=>'A partial amount must be greater than zero and less than the requested total.'])->withInput();
            }
            if (in_array($lineData['decision'], ['unfunded', 'cancelled'], true) && blank($lineData['note'] ?? null)) {
                return back()->withErrors(["lines.{$id}.note"=>'Give a reason for an unfunded or cancelled item.'])->withInput();
            }
        }

        DB::transaction(function () use ($data, $periodLines, $approval, $step, $request) {
            foreach ($data['lines'] as $id => $lineData) {
                $budget = $periodLines[$id];
                $amount = match ($lineData['decision']) {
                    'funded' => (float) $budget->planned_amount,
                    'unfunded', 'cancelled' => 0,
                    default => (float) $lineData['funded_amount'],
                };
                $old = $budget->only(['funding_status', 'funded_amount', 'funding_note']);
                $budget->update([
                    'funding_status'=>$lineData['decision'], 'funded_amount'=>$amount,
                    'funding_note'=>$lineData['note'] ?? null, 'funding_decided_by'=>$request->user()->id,
                    'funding_decided_at'=>now(),
                ]);
                $budget->fundingDecisions()->create([
                    'approval_id'=>$approval->id, 'approval_step_id'=>$step?->id,
                    'decision'=>$lineData['decision'], 'funded_amount'=>$amount,
                    'note'=>$lineData['note'] ?? null, 'decided_by'=>$request->user()->id, 'decided_at'=>now(),
                ]);
                AuditLog::record('budget_line_funding_decided', $budget,
                    $request->user()->displayName().' marked '.$budget->item.' as '.$lineData['decision'].'.',
                    $old, ['funding_status'=>$lineData['decision'], 'funded_amount'=>$amount, 'funding_note'=>$lineData['note'] ?? null], 'budget');
            }
        });

        return back()->with('status', 'Budget line funding decisions saved.');
    }

    public function comment(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless($request->user()->canAccess('approvals'), 403);
        $data=$request->validate(['comment'=>['required','string','max:3000']]);
        $approval->comments()->create(['user_id'=>$request->user()->id,'comment'=>$data['comment']]);
        AuditLog::record('approval_commented',$approval,$request->user()->displayName().' commented on '.$approval->label(),null,null,'approvals');
        return back()->with('status','Comment added to the approval review.');
    }

    public function storeDelegation(Request $request): RedirectResponse
    {
        $ownRoles=$request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
        $data=$request->validate(['role_code'=>['required','in:finance-manager,ceo,director'],'delegate_id'=>['required','exists:users,id','different:delegator_id'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after_or_equal:starts_on'],'reason'=>['nullable','string','max:1000']]);
        abort_unless($ownRoles->contains($data['role_code']),403,'You can delegate only your own approval role.');
        ApprovalDelegation::where('delegator_id',$request->user()->id)->where('role_code',$data['role_code'])->where('is_active',true)->update(['is_active'=>false]);
        ApprovalDelegation::create($data+['delegator_id'=>$request->user()->id,'is_active'=>true]);
        app(CommunicationService::class)->toUser((int)$data['delegate_id'],'Approval authority delegated to you','You may approve as '.$data['role_code'].' from '.$data['starts_on'].' to '.$data['ends_on'].'.','workflow',$request->user()->id,route('approvals.index',absolute:false));
        return back()->with('status','Approval authority delegated for the selected period.');
    }

    public function revokeDelegation(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        abort_unless($delegation->delegator_id===$request->user()->id,403);
        $delegation->update(['is_active'=>false]);
        return back()->with('status','Approval delegation revoked.');
    }

    private function effectiveRoleCodes(Request $request)
    {
        $direct=$request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        $delegated=ApprovalDelegation::current()->where('delegate_id',$request->user()->id)->pluck('role_code');
        return $direct->merge($delegated)->unique();
    }
}
