<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\AuditLog;
use App\Models\Purchase;
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
        abort_unless(in_array($status, ['pending', 'approved', 'rejected'], true), 404);

        $query = Approval::with(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker'])
            ->where('status', $status)->latest('submitted_at');

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
                $approval->approvable->update(['status'=>$data['decision']==='rejected'?'rejected':($isFinalApproval?'approved':'ceo_approved')]);
            }

            AuditLog::record('approval_decided', $approval,
                "{$request->user()->displayName()} {$data['decision']} {$approval->label()}.",
                $old, ['decision' => $data['decision'], 'stage'=>$step->stage, 'decision_note' => $data['decision_note'] ?? null], 'approvals');
        });

        $approval->refresh();
        $message = $data['decision'] === 'rejected'
            ? 'Request rejected.'
            : ($approval->status === 'pending' ? 'Stage approved and sent to the Director for final approval.' : 'Final approval recorded.');

        return back()->with('status', $message);
    }

    public function show(Request $request, Approval $approval): View
    {
        abort_unless($request->user()->canAccess('approvals'), 403);
        $approval->load(['approvable', 'submitter', 'decisionMaker', 'steps.decisionMaker']);

        $budgetLines = collect();
        if ($approval->approvable instanceof Budget) {
            $budgetLines = Budget::where('period_start', $approval->approvable->period_start)
                ->orderBy('category')->orderBy('id')->get();
        }

        return view('approvals.show', compact('approval', 'budgetLines'));
    }
}
