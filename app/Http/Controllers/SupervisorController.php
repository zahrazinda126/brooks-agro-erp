<?php

namespace App\Http\Controllers;

use App\Models\{AnimalGroup,AuditLog,PoultryHouse,SupervisorAssignment,User};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    private const UNITS=['poultry','goats','rabbits','dogs','garden'];

    public function index(Request $request): View
    {
        $user=$request->user(); $isCeo=$this->hasRole($user,'ceo'); $isDirector=$this->hasRole($user,'director');
        $isFallbackReviewer=$this->hasRole($user,'sales-manager')||$this->hasRole($user,'production-manager');
        $assignments=SupervisorAssignment::with(['supervisor','assigner'])->latest('starts_on')->get();
        $mine=$assignments->where('supervisor_id',$user->id)->filter(fn($a)=>$a->is_active&&$a->starts_on->lte(today())&&(!$a->ends_on||$a->ends_on->gte(today())));
        abort_unless($isCeo||$isDirector||$mine->isNotEmpty()||$isFallbackReviewer,403);
        $dates=$request->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']]);
        $from=$dates['from']??null; $to=$dates['to']??null;
        $withinDates=fn($record)=>(!$from||\Carbon\Carbon::parse($record->date)->gte($from))&&(!$to||\Carbon\Carbon::parse($record->date)->lte($to));
        $pendingRecords=$this->pendingRecords($user,$mine)->filter($withinDates)->values();
        $reviewedRecords=$this->reviewedRecords($user,$mine,$isCeo||$isDirector)->filter($withinDates)->values();
        return view('supervision.index',['assignments'=>$assignments,'mine'=>$mine,'workers'=>User::with(['employee','role'])->where('is_active',true)->whereNotNull('employee_id')->orderBy('name')->get(),'pendingRecords'=>$pendingRecords,'reviewedRecords'=>$reviewedRecords,'from'=>$from,'to'=>$to,'isCeo'=>$isCeo,'isDirector'=>$isDirector,'isFallbackReviewer'=>$isFallbackReviewer,'units'=>self::UNITS]);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($this->hasRole($request->user(),'ceo'),403);
        $d=$request->validate(['supervisor_id'=>['required','exists:users,id'],'unit_code'=>['required',Rule::in(self::UNITS)],'starts_on'=>['required','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'shift'=>['required','in:all_day,morning,afternoon,night'],'notes'=>['nullable','string','max:1000']]);
        abort_if((int)$d['supervisor_id']===$request->user()->id,422,'Choose a farm worker as supervisor.');
        abort_unless(User::whereKey($d['supervisor_id'])->where('is_active',true)->whereHas('employee',fn($q)=>$q->where('status','active'))->exists(),422,'The supervisor must be an active farm worker with an account.');
        $overlap=SupervisorAssignment::where('unit_code',$d['unit_code'])->where('is_active',true)->whereDate('starts_on','<=',$d['ends_on']?:'9999-12-31')->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',$d['starts_on']))->exists();
        abort_if($overlap,422,'That unit already has a supervisor during the selected period. End or adjust that assignment first.');
        $assignment=SupervisorAssignment::create($d+['assigned_by'=>$request->user()->id,'is_active'=>true]);
        AuditLog::record('supervisor_assigned',$assignment,'Daily supervisor appointed for '.$d['unit_code'].'.',null,$d,'assign-supervisors');
        return back()->with('status','Daily supervisor appointed.');
    }

    public function end(Request $request,SupervisorAssignment $assignment):RedirectResponse
    {
        abort_unless($this->hasRole($request->user(),'ceo'),403);$assignment->update(['is_active'=>false,'ends_on'=>$assignment->ends_on?:today()]);return back()->with('status','Supervisor assignment ended.');
    }

    public function showRecord(Request $request,string $type,int $id):View
    {
        $map=$this->recordMap();abort_unless(isset($map[$type]),404);$cfg=$map[$type];
        $row=DB::table($cfg['table'])->where('id',$id)->first();abort_unless($row,404);
        $unit=$cfg['unit'] instanceof \Closure?($cfg['unit'])($row):($cfg['unit']==='unit_code'?$row->unit_code:$cfg['unit']);
        $date=$cfg['date'] instanceof \Closure?($cfg['date'])($row):$row->{$cfg['date']};
        $user=$request->user();$executive=$this->hasRole($user,'ceo')||$this->hasRole($user,'director');
        $assigned=$this->canReviewRecord($user,$type,$unit,$date);abort_unless($executive||$assigned,403);
        $legacy=$cfg['legacy'];$status=$legacy?$row->status:$row->verification_status;
        $excluded=['id','department_id','animal_group_id','poultry_house_id','livestock_group_id','recorded_by','approved_by','verified_by','created_at','updated_at','status','verification_status','approved_at','verified_at','query_note','verification_note'];
        $details=collect(get_object_vars($row))->except($excluded)->mapWithKeys(fn($value,$key)=>[ucwords(str_replace('_',' ',$key))=>$value]);
        $lines=collect();
        if($type==='eggs'){
            $lines=DB::table('egg_collection_lines')->join('egg_grades','egg_grades.id','=','egg_collection_lines.egg_grade_id')->where('egg_collection_id',$id)->get(['egg_grades.name','egg_collection_lines.trays','egg_collection_lines.loose_eggs','egg_collection_lines.total_eggs']);
            $house=PoultryHouse::find($row->poultry_house_id);
            $batch=AnimalGroup::find($row->animal_group_id);
            $details=collect([
                'House number'=>$house?->displayNumber() ?? 'Not recorded',
                'Batch number'=>$batch?->code ?? 'Not recorded',
                'Collection date'=>\Carbon\Carbon::parse($row->collected_on)->format('d M Y'),
                'Collection time'=>$row->collected_at ? \Carbon\Carbon::parse($row->collected_at)->format('H:i:s') : 'Not recorded',
                'Session'=>ucfirst($row->session),
                'Recorded at'=>$row->recorded_at ? \Carbon\Carbon::parse($row->recorded_at)->format('d M Y, H:i:s') : null,
                'Worker note'=>$row->worker_note ?: null,
            ])->filter(fn($value)=>$value!==null&&$value!=='');
        }
        $recorder=User::find($row->recorded_by);
        return view('supervision.record',['type'=>$type,'record'=>$row,'unit'=>$unit,'date'=>$date,'label'=>($cfg['label'])($row),'details'=>$details,'lines'=>$lines,'recorder'=>$recorder,'status'=>$status,'canReview'=>$assigned&&(int)$row->recorded_by!==$user->id&&$status==='submitted']);
    }

    public function reviewRecord(Request $request,string $type,int $id):RedirectResponse
    {
        $map=$this->recordMap();abort_unless(isset($map[$type]),404);$d=$request->validate(['decision'=>['required','in:approved,returned'],'review_note'=>['nullable','required_if:decision,returned','string','max:1000']]);$cfg=$map[$type];$row=DB::table($cfg['table'])->where('id',$id)->first();abort_unless($row,404);$unit=$cfg['unit'] instanceof \Closure?($cfg['unit'])($row):($cfg['unit']==='unit_code'?$row->unit_code:$cfg['unit']);$date=$cfg['date'] instanceof \Closure?($cfg['date'])($row):$row->{$cfg['date']};abort_unless($this->canReviewRecord($request->user(),$type,$unit,$date)&&$row->recorded_by!==$request->user()->id,403);
        $values=$cfg['legacy']?['status'=>$d['decision']==='approved'?'approved':'queried','approved_by'=>$request->user()->id,'approved_at'=>now(),'query_note'=>$d['review_note']??null]:['verification_status'=>$d['decision'],'verified_by'=>$request->user()->id,'verified_at'=>now(),'verification_note'=>$d['review_note']??null];DB::table($cfg['table'])->where('id',$id)->update($values+['updated_at'=>now()]);return back()->with('status','Daily entry '.$d['decision'].'.');
    }

    private function pendingRecords(User $user,$mine){$rows=collect();foreach($this->recordMap() as $type=>$cfg){$q=DB::table($cfg['table'])->where($cfg['legacy']?'status':'verification_status','submitted');foreach($q->latest('id')->limit(50)->get() as $r){$date=$cfg['date'] instanceof \Closure?($cfg['date'])($r):$r->{$cfg['date']};$unit=$cfg['unit'] instanceof \Closure?($cfg['unit'])($r):($cfg['unit']==='unit_code'?$r->unit_code:$cfg['unit']);if($this->canReviewRecord($user,$type,$unit,$date))$rows->push((object)['type'=>$type,'id'=>$r->id,'date'=>$date,'unit'=>$unit,'label'=>($cfg['label'])($r),'recorded_by'=>$r->recorded_by]);}}return $rows->where('recorded_by','!=',$user->id)->sortByDesc('date')->values();}
    private function reviewedRecords(User $user,$mine,bool $executive){
        if(!$executive&&$mine->isEmpty()&&!$this->hasRole($user,'sales-manager')&&!$this->hasRole($user,'production-manager'))return collect();
        $units=$mine->pluck('unit_code');$rows=collect();
        foreach($this->recordMap() as $type=>$cfg){
            $legacy=$cfg['legacy'];$q=DB::table($cfg['table'])->where($legacy?'status':'verification_status','!=','submitted');
            if(!$executive){
                $q->where($legacy?'approved_by':'verified_by',$user->id);
                if($mine->isNotEmpty()){if(is_string($cfg['unit'])){if($cfg['unit']==='poultry'&&!$units->contains('poultry'))continue;if($cfg['unit']==='unit_code')$q->whereIn('unit_code',$units);}else{$q=$cfg['scope']($q,$units);}}
            }
            foreach($q->latest($legacy?'approved_at':'verified_at')->limit(50)->get() as $r){
                $rows->push((object)[
                    'type'=>$type,
                    'id'=>$r->id,
                    'date'=>$cfg['date'] instanceof \Closure?($cfg['date'])($r):$r->{$cfg['date']},
                    'entered_at'=>$r->recorded_at??$r->created_at??null,
                    'unit'=>$cfg['unit'] instanceof \Closure?($cfg['unit'])($r):($cfg['unit']==='unit_code'?$r->unit_code:$cfg['unit']),
                    'label'=>($cfg['label'])($r),
                    'decision'=>$legacy?($r->status==='queried'?'returned':$r->status):$r->verification_status,
                    'reviewer_id'=>$legacy?$r->approved_by:$r->verified_by,
                    'reviewed_at'=>$legacy?$r->approved_at:$r->verified_at,
                    'note'=>$legacy?$r->query_note:$r->verification_note,
                ]);
            }
        }
        $names=User::whereIn('id',$rows->pluck('reviewer_id')->filter()->unique())->get()->keyBy('id');
        return $rows->each(fn($r)=>$r->reviewer=$names->get($r->reviewer_id)?->displayName()??'Unknown user')->sortByDesc('reviewed_at')->take(100)->values();
    }
    private function recordMap():array{$livestockScope=fn($table)=>fn($q,$units)=>$q->join('livestock_groups','livestock_groups.id','=',$table.'.livestock_group_id')->whereIn('livestock_groups.unit_code',$units)->select($table.'.*','livestock_groups.unit_code');$livestockUnit=fn($r)=>$r->unit_code??DB::table('livestock_groups')->where('id',$r->livestock_group_id)->value('unit_code');return [
        'farm'=>['table'=>'farm_unit_records','unit'=>'unit_code','date'=>'record_date','legacy'=>false,'label'=>fn($r)=>ucwords(str_replace('_',' ',$r->record_type)).': '.$r->subject],
        'eggs'=>['table'=>'egg_collections','unit'=>'poultry','date'=>'collected_on','legacy'=>true,'label'=>fn($r)=>number_format($r->total_eggs).' eggs'],
        'poultry_feed'=>['table'=>'feed_consumptions','unit'=>'poultry','date'=>'used_on','legacy'=>true,'label'=>fn($r)=>$r->quantity_kg.' kg '.$r->feed_type],
        'livestock_feed'=>['table'=>'livestock_feed_records','unit'=>$livestockUnit,'date'=>'recorded_on','legacy'=>false,'scope'=>$livestockScope('livestock_feed_records'),'label'=>fn($r)=>$r->quantity_kg.' kg '.$r->feed_name],
        'livestock_health'=>['table'=>'livestock_health_records','unit'=>$livestockUnit,'date'=>'recorded_on','legacy'=>false,'scope'=>$livestockScope('livestock_health_records'),'label'=>fn($r)=>ucfirst($r->event_type).': '.$r->condition_or_vaccine],
        'livestock_mortality'=>['table'=>'livestock_mortality_records','unit'=>$livestockUnit,'date'=>'occurred_on','legacy'=>false,'scope'=>$livestockScope('livestock_mortality_records'),'label'=>fn($r)=>$r->quantity.' mortality'],
        'task'=>['table'=>'farm_task_entries','unit'=>'unit_code','date'=>'done_on','legacy'=>false,'label'=>fn($r)=>'Task · '.$r->task_name.($r->quantity!==null?': '.(float)$r->quantity.' '.$r->measure:'')],
        // Worker reports added after the Farm Hub survey (water, sick birds, problems).
        'house_report'=>['table'=>'poultry_house_reports','unit'=>'unit_code','date'=>'reported_on','legacy'=>false,'label'=>fn($r)=>match($r->report_type){'water'=>'Water: '.number_format((float)$r->quantity).' litres','sick_birds'=>'Sick birds: '.(int)$r->quantity,default=>'Problem: '.(\App\Http\Controllers\Poultry\HouseReportController::PROBLEMS[$r->category]??$r->category)}],
        'livestock_production'=>['table'=>'livestock_production_records','unit'=>$livestockUnit,'date'=>'recorded_on','legacy'=>false,'scope'=>$livestockScope('livestock_production_records'),'label'=>fn($r)=>ucfirst($r->production_type).': '.$r->quantity.' '.$r->measure_unit],
    ];}
    private function activeAssignment(int $userId,string $unit,$date):bool{return SupervisorAssignment::where('supervisor_id',$userId)->where('unit_code',$unit)->where('is_active',true)->whereDate('starts_on','<=',$date)->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',$date))->exists();}
    private function canReviewRecord(User $user,string $type,string $unit,$date):bool
    {
        if($this->activeAssignment($user->id,$unit,$date))return true;
        $fallback=$type==='eggs'?$this->hasRole($user,'sales-manager'):$this->hasRole($user,'production-manager');
        if(!$fallback)return false;
        return !SupervisorAssignment::where('unit_code',$unit)->where('is_active',true)->whereDate('starts_on','<=',$date)->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',$date))->exists();
    }
    private function hasRole(User $user,string $code):bool{return $user->role?->code===$code||$user->roles->contains('code',$code);}
}
