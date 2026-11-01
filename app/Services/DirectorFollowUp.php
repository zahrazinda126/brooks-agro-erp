<?php

namespace App\Services;

use App\Models\{Approval, ApprovedFundFollowUp, ExecutiveDirective, ExecutiveMeeting};

/** The PA's Director Follow-up board: directives, the Director's meetings, and approved money follow-up. */
class DirectorFollowUp
{
    public function data(): array
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
}
