<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('approvals'), 403);
        $status = $request->string('status')->toString() ?: 'pending';
        abort_unless(in_array($status, ['pending', 'sent', 'approved', 'rejected'], true), 404);

        $query = Approval::with(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker'])
            ->when($status !== 'sent', fn($q) => $q->where('status', $status))
            ->when($status === 'sent', fn($q) => $q->where('status','pending')->whereHas('steps', fn($s) => $s->where('stage',1)->where('status','approved')->where('decided_by',$request->user()->id)))
            ->latest('submitted_at');

        if ($status === 'pending') {
            $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter()->unique();
            $query->whereHas('steps', fn ($q) => $q->where('status', 'pending')->whereIn('role_code', $roleCodes));
        }

        if ($request->filled('type')) {
            $query->where('module_key', $request->string('type')->toString());
        }

        return view('approvals.index', ['approvals' => $query->paginate(20)->withQueryString(), 'status' => $status]);
    }

    public function decide(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless($request->user()->canDo('approvals', 'approve'), 403);
        $approval->load('steps');
        $step = $approval->currentStep();
        $roleCodes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        abort_unless($step && $roleCodes->contains($step->role_code), 403, 'This approval is waiting for another role.');
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

            AuditLog::record('approval_decided', $approval,
                "{$request->user()->displayName()} {$data['decision']} {$approval->label()}.",
                $old, ['decision' => $data['decision'], 'stage'=>$step->stage, 'decision_note' => $data['decision_note'] ?? null], 'approvals');
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
        abort_unless($request->user()->canAccess('approvals'), 403);
        $approval->load(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker', 'comments.user']);

        $budgetLines = collect();
        if ($approval->approvable instanceof Budget) {
            $budgetLines = Budget::where('period_start', $approval->approvable->period_start)
                ->orderBy('category')->orderBy('id')->get();
        }

        return view('approvals.show', compact('approval', 'budgetLines'));
    }

    public function comment(Request $request, Approval $approval): RedirectResponse
    {
        abort_unless($request->user()->canAccess('approvals'), 403);
        $data=$request->validate(['comment'=>['required','string','max:3000']]);
        $approval->comments()->create(['user_id'=>$request->user()->id,'comment'=>$data['comment']]);
        AuditLog::record('approval_commented',$approval,$request->user()->displayName().' commented on '.$approval->label(),null,null,'approvals');
        return back()->with('status','Comment added to the approval review.');
    }
}
