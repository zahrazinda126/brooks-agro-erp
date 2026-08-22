<?php
namespace App\Http\Controllers;
use App\Models\{AnimalGroup,AuditLog,Breed,HealthEvent,Medication,VaccinationPlan};
use Illuminate\Validation\Rule;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\View\View;
class VaccinationScheduleController extends Controller {
    public function index(Request $r):View{
        $vaccines=Medication::where('type','vaccine')->where('is_active',true)->orderBy('name')->get();
        return view('vaccination-schedule.index',[
            'plans'=>VaccinationPlan::with(['medication','breed'])->orderBy('purpose')->orderBy('age_days')->get(),
            'medications'=>$vaccines,'breeds'=>Breed::orderBy('name')->get(),'canEdit'=>$this->canEdit($r),
            'batches'=>AnimalGroup::with('breed')->where('status','active')->orderBy('code')->get(),
            'records'=>HealthEvent::with(['batch.breed','medication','recorder'])->where('event_type','vaccination')->latest('occurred_on')->latest('id')->paginate(30),
        ]);
    }
    public function store(Request $r):RedirectResponse{abort_unless($this->canEdit($r),403);VaccinationPlan::create($this->data($r));return back()->with('status','Vaccination schedule item added.');}
    public function update(Request $r,VaccinationPlan $plan):RedirectResponse{abort_unless($this->canEdit($r),403);$plan->update($this->data($r));return back()->with('status','Vaccination schedule updated.');}
    public function storeRecord(Request $r):RedirectResponse{
        abort_unless($this->canEdit($r),403);
        $data=$r->validate([
            'animal_group_id'=>['required',Rule::exists('animal_groups','id')->where('status','active')],
            'medication_id'=>['required',Rule::exists('medications','id')->where('type','vaccine')->where('is_active',true)],
            'occurred_on'=>['required','date','before_or_equal:today'],'birds_treated'=>['required','integer','min:1','max:1000000'],
            'dose_given'=>['required','string','max:160'],'application_route'=>['nullable','string','max:80'],
            'vaccine_batch_number'=>['required','string','max:100'],'manufacturer'=>['nullable','string','max:160'],
            'manufactured_on'=>['nullable','date','before_or_equal:today'],'expires_on'=>['required','date','after_or_equal:occurred_on'],
            'next_due_on'=>['nullable','date','after:occurred_on'],'administered_name'=>['nullable','string','max:160'],
            'vet_involved'=>['nullable','boolean'],'cost'=>['nullable','numeric','min:0'],'findings'=>['nullable','string','max:2000'],
        ]);
        $batch=AnimalGroup::findOrFail($data['animal_group_id']);
        $event=HealthEvent::create($data+[
            'department_id'=>$batch->department_id,'event_type'=>'vaccination','days_of_course'=>1,
            'reason'=>'Scheduled vaccination','vet_involved'=>$r->boolean('vet_involved'),
            'administered_by'=>$r->user()->id,'recorded_by'=>$r->user()->id,'recorded_at'=>now(),
            'cost'=>$data['cost']??0,
        ]);
        AuditLog::record('vaccination_recorded',$event,'Vaccination recorded for '.$batch->code.': '.$event->medication?->name.'.',null,$event->toArray(),'health');
        return redirect()->route('vaccination-schedule.index')->with('status','Vaccination recorded for '.$batch->code.'.');
    }
    private function data(Request $r):array{return $r->validate(['medication_id'=>['required','exists:medications,id'],'breed_id'=>['nullable','exists:breeds,id'],'purpose'=>['required','in:layer,broiler'],'age_days'=>['required','integer','min:0','max:2000'],'window_days'=>['required','integer','min:0','max:60'],'repeat_every_days'=>['nullable','integer','min:1'],'repeat_until_days'=>['nullable','integer','gte:age_days'],'is_essential'=>['nullable','boolean'],'is_active'=>['nullable','boolean'],'note'=>['nullable','string','max:1000']])+['is_essential'=>$r->boolean('is_essential'),'is_active'=>$r->boolean('is_active')];}
    private function canEdit(Request $r):bool{$u=$r->user();return $u->isSuperuser()||$u->role?->code==='production-manager'||$u->roles->contains('code','production-manager');}
}
