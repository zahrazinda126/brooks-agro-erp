<?php

namespace App\Http\Controllers;

use App\Models\{Approval, ApprovedFundFollowUp, Communication, ExecutiveDirective, ExecutiveMeeting, User};
use App\Services\CommunicationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExecutiveFollowUpController extends Controller
{
    private const RECIPIENT_GROUPS = [
        'all_staff' => 'Everyone',
        'management' => 'Management',
        'finance' => 'Finance team',
        'hr' => 'Human Resources team',
        'production' => 'Production team',
        'sales' => 'Sales team',
    ];
    private function roleCodes(Request $request) { return $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter(); }
    private function isPa(Request $request): bool { return $this->roleCodes($request)->intersect(User::PA_ROLES)->isNotEmpty(); }
    private function isDirector(Request $request): bool { return $this->roleCodes($request)->contains('director'); }
    private function isCeo(Request $request): bool { return $this->roleCodes($request)->contains('ceo'); }
    private function authorizePa(Request $request): void { abort_unless($this->isPa($request), 403); }

    public function index(Request $request): View
    {
        $isDirector = $this->isDirector($request); $isPa = $this->isPa($request); $isCeo=$this->isCeo($request);
        $query = ExecutiveDirective::with(['director','tracker','assignee','forwarder','rescheduler'])->latest('issued_on')->latest('id');
        if (! $isDirector && ! $isPa && ! $isCeo) {
            $query->where(fn ($visible) => $visible
                ->where('assigned_to', $request->user()->id)
                ->orWhereIn('recipient_scope', Communication::audiencesFor($request->user())));
        }
        return view('directives.index', [
            'directives'=>$query->get(), 'isDirector'=>$isDirector, 'isPa'=>$isPa,
            'users'=>User::where('is_active',true)->whereKeyNot($request->user()->id)->orderBy('name')->get(),
            'recipientGroups'=>self::RECIPIENT_GROUPS,
            'paUsers'=>User::where('is_active',true)->where(fn($q)=>$q->whereHas('roles',fn($r)=>$r->whereIn('code',User::PA_ROLES))->orWhereHas('role',fn($r)=>$r->whereIn('code',User::PA_ROLES)))->get(),
        ]);
    }

    public function storeDirective(Request $request): RedirectResponse
    {
        abort_unless($this->isPa($request) || $this->isDirector($request), 403);
        $data=$request->validate(['title'=>['required','string','max:180'],'directive'=>['required','string','max:3000'],'responsible_team'=>['required','string','max:100'],'issued_on'=>['required','date'],'due_on'=>['nullable','date','after_or_equal:issued_on'],'tracked_by'=>['nullable','exists:users,id']]);
        $director=$this->isDirector($request)?$request->user():User::whereHas('roles',fn($q)=>$q->where('code','director'))->orWhereHas('role',fn($q)=>$q->where('code','director'))->first();
        $defaultPa=User::where('is_active',true)->where(fn($q)=>$q->whereHas('roles',fn($r)=>$r->whereIn('code',User::PA_ROLES))->orWhereHas('role',fn($r)=>$r->whereIn('code',User::PA_ROLES)))->first();
        $trackerId=$data['tracked_by']??($this->isPa($request)?$request->user()->id:$defaultPa?->id);
        $directive=ExecutiveDirective::create($data+['status'=>'awaiting_forwarding','progress'=>0,'directed_by'=>$director?->id??$request->user()->id,'tracked_by'=>$trackerId??$request->user()->id]);
        if($trackerId && $trackerId!==$request->user()->id) app(CommunicationService::class)->toUser($trackerId,'New directive to forward: '.$directive->title,$directive->directive,'directive',$request->user()->id,route('directives.index',absolute:false));
        app(CommunicationService::class)->toRole('ceo','Directive received for routing: '.$directive->title,'The directive is with the PA and the CEO will be notified again when it is forwarded.',$request->user()->id,route('directives.index',absolute:false));
        return back()->with('status','Directive issued and sent to the PA for forwarding.');
    }

    public function forwardDirective(Request $request, ExecutiveDirective $directive): RedirectResponse
    {
        $this->authorizePa($request);
        abort_unless($directive->tracked_by===$request->user()->id,403,'This directive is assigned to another PA.');
        $data=$request->validate(['recipient'=>['required','string','max:80'],'responsible_team'=>['required','string','max:100'],'scheduled_for'=>['required','date']]);
        $recipient=(string)$data['recipient'];$assignedTo=null;$scope=null;$recipientLabel=null;
        if(str_starts_with($recipient,'group:')){
            $scope=substr($recipient,6);
            if(!isset(self::RECIPIENT_GROUPS[$scope]))return back()->withErrors(['recipient'=>'Choose a valid recipient group.'])->withInput();
            $recipientLabel=self::RECIPIENT_GROUPS[$scope];
        }elseif(str_starts_with($recipient,'user:') && ctype_digit(substr($recipient,5))){
            $person=User::where('is_active',true)->find((int)substr($recipient,5));
            if(!$person)return back()->withErrors(['recipient'=>'Choose an active recipient.'])->withInput();
            $assignedTo=$person->id;$recipientLabel=$person->displayName();
        }else return back()->withErrors(['recipient'=>'Choose a person or group.'])->withInput();

        $directive->update(['assigned_to'=>$assignedTo,'recipient_scope'=>$scope,'responsible_team'=>$data['responsible_team'],'scheduled_for'=>$data['scheduled_for'],'forwarded_by'=>$request->user()->id,'forwarded_at'=>now(),'status'=>'pending']);
        $subject='Director directive: '.$directive->title;
        $body=$directive->directive.' Due: '.($directive->due_on?->format('d M Y')??'not specified').'.';
        $service=app(CommunicationService::class);
        $recipientCount=$scope
            ? $service->toAudience($scope,$subject,$body,$request->user()->id,route('directives.index',absolute:false))
            : (int)(bool)$service->toUser($assignedTo,$subject,$body,'directive',$request->user()->id,route('directives.index',absolute:false));
        $service->toUser($directive->directed_by,'Directive delivered: '.$directive->title,'The PA forwarded this directive to '.$recipientLabel.' ('.$recipientCount.' '.str('recipient')->plural($recipientCount).').','workflow',$request->user()->id,route('directives.index',absolute:false));
        $service->toRole('ceo','PA forwarded directive: '.$directive->title,'The directive was forwarded to '.$recipientLabel.' by '.$request->user()->displayName().'.','workflow',$request->user()->id,route('directives.index',absolute:false));
        return back()->with('status','Directive forwarded to '.$recipientLabel.' and '.$recipientCount.' '.str('recipient')->plural($recipientCount).' notified.');
    }

    public function acknowledgeDirective(Request $request, ExecutiveDirective $directive): RedirectResponse
    {
        abort_unless($directive->assigned_to===$request->user()->id,403);
        $directive->update(['acknowledged_at'=>now()]);
        app(CommunicationService::class)->toUser($directive->directed_by,'Directive acknowledged: '.$directive->title,$request->user()->displayName().' confirmed receipt.','workflow',$request->user()->id,route('directives.index',absolute:false));
        app(CommunicationService::class)->toUser($directive->tracked_by,'Directive acknowledged: '.$directive->title,$request->user()->displayName().' confirmed receipt.','workflow',$request->user()->id,route('directives.index',absolute:false));
        return back()->with('status','Receipt acknowledged.');
    }

    public function updateDirective(Request $request, ExecutiveDirective $directive): RedirectResponse
    {
        $this->authorizePa($request);
        abort_unless($directive->tracked_by===$request->user()->id, 403, 'This directive is assigned to another PA.');
        $data=$request->validate(['status'=>['required',Rule::in(['not_started','pending','rescheduled','in_progress','blocked','done','completed','cancelled'])],'latest_feedback'=>['required','string','max:3000'],'scheduled_for'=>['nullable','date'],'reschedule_reason'=>['nullable','string','max:1000']]);
        $scheduleChanged=$data['scheduled_for'] && (!$directive->scheduled_for || ! $directive->scheduled_for->equalTo(\Carbon\Carbon::parse($data['scheduled_for'])));
        if($data['status']==='rescheduled' && !$scheduleChanged) return back()->withErrors(['scheduled_for'=>'Choose a new date and time when marking a directive as rescheduled.'])->withInput();
        if(in_array($data['status'],['pending','rescheduled','blocked'],true) && blank($data['reschedule_reason']??null)) {
            $message=match($data['status']){
                'pending'=>'Explain why the directive is still pending.',
                'blocked'=>'Explain what is blocking the directive and what is needed to continue.',
                default=>'Explain why the directive is being rescheduled.',
            };
            return back()->withErrors(['reschedule_reason'=>$message])->withInput();
        }
        $status=match($data['status']){'completed'=>'done','not_started'=>'pending',default=>$data['status']};
        $changes=$data+['status'=>$status,'progress'=>$status==='done'?100:0,'feedback_at'=>now(),'completed_at'=>$status==='done'?now():null];
        if($scheduleChanged){$changes['rescheduled_by']=$request->user()->id;$changes['rescheduled_at']=now();}
        $directive->update($changes);
        foreach(array_unique(array_filter([$directive->directed_by,$directive->tracked_by,$directive->assigned_to])) as $recipient) app(CommunicationService::class)->toUser($recipient,($scheduleChanged?'Directive rescheduled: ':'Directive status updated: ').$directive->title,$request->user()->displayName().' marked it '.str_replace('_',' ',$status).'. '.(in_array($status,['pending','rescheduled','blocked'],true)?'Reason: '.$data['reschedule_reason'].'. ':'').($scheduleChanged?'New schedule: '.$directive->scheduled_for->format('d M Y, H:i').'. ':'').$data['latest_feedback'],'workflow',$request->user()->id,route('directives.index',absolute:false));
        return back()->with('status','Progress and feedback sent.');
    }

    public function storeMeeting(Request $request):RedirectResponse { $this->authorizePa($request); $d=$request->validate(['title'=>['required','string','max:180'],'starts_at'=>['required','date'],'ends_at'=>['nullable','date','after:starts_at'],'venue'=>['nullable','string','max:180'],'participants'=>['nullable','string','max:2000'],'agenda'=>['nullable','string','max:3000'],'host'=>['nullable','string','max:180'],'reason'=>['nullable','string','max:60'],'contact'=>['nullable','string','max:60']]); $director=User::whereHas('roles',fn($q)=>$q->where('code','director'))->orWhereHas('role',fn($q)=>$q->where('code','director'))->first(); ExecutiveMeeting::create($d+['scheduled_for'=>$director?->id??$request->user()->id,'scheduled_by'=>$request->user()->id,'status'=>'scheduled']); if($director)app(CommunicationService::class)->toUser($director,'Meeting scheduled: '.$d['title'],'Scheduled for '.date('d M Y, H:i',strtotime($d['starts_at'])).($d['venue']?' at '.$d['venue']:'.'),'workflow',$request->user()->id); return back()->with('status','Meeting added to the Director\'s schedule.'); }
    public function updateMeeting(Request $request,ExecutiveMeeting $meeting):RedirectResponse { $this->authorizePa($request); $d=$request->validate(['status'=>['required',Rule::in(['scheduled','completed','postponed','cancelled'])],'outcome'=>['nullable','string','max:3000']]); $meeting->update($d); return back()->with('status','Meeting status updated.'); }
    public function storeFundFollowUp(Request $request):RedirectResponse { $this->authorizePa($request); $d=$request->validate(['approval_id'=>['required','exists:approvals,id','unique:approved_fund_follow_ups,approval_id'],'intended_purpose'=>['required','string','max:3000'],'follow_up_due_on'=>['nullable','date']]); $approval=Approval::findOrFail($d['approval_id']); abort_unless($approval->status==='approved',422,'Only fully approved requests can be followed up.'); ApprovedFundFollowUp::create($d+['approved_amount'=>(float)str_replace(',','',$approval->amount()??0),'tracked_by'=>$request->user()->id]); return back()->with('status','Approved funds added to implementation follow-up.'); }
    public function updateFundFollowUp(Request $request,ApprovedFundFollowUp $followUp):RedirectResponse { $this->authorizePa($request); $d=$request->validate(['status'=>['required',Rule::in(['awaiting_implementation','in_use','partly_accounted','verified','concern'])],'amount_accounted_for'=>['required','numeric','min:0','max:'.$followUp->approved_amount],'implementation_result'=>['required','string','max:3000'],'evidence_reference'=>['nullable','string','max:1000']]); $followUp->update($d); return back()->with('status','Approved-money follow-up updated.'); }
}
