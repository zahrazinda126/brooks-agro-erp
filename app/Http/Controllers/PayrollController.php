<?php

namespace App\Http\Controllers;

use App\Models\{Approval,AuditLog,Employee,EmployeeRecord,FinancialTransaction,PayrollLine,PayrollRun};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request):View
    {
        abort_unless($request->user()->canAccess('payroll'),403);
        return view('payroll.index',['runs'=>PayrollRun::withCount('lines')->latest('period_end')->paginate(25),
            'currentGross'=>(float)PayrollRun::whereMonth('period_end',now()->month)->whereYear('period_end',now()->year)->sum('gross_total'),
            'currentNet'=>(float)PayrollRun::whereMonth('period_end',now()->month)->whereYear('period_end',now()->year)->sum('net_total'),
            'pending'=>PayrollRun::whereIn('status',['submitted','finance_reviewed','ceo_reviewed'])->count()]);
    }

    public function generate(Request $request):RedirectResponse
    {
        abort_unless($this->hasRole($request,'hr-manager')&&$request->user()->canDo('payroll','create'),403);
        $data=$request->validate(['frequency'=>['required','in:monthly,weekly,daily'],'period_start'=>['required','date'],'period_end'=>['required','date','after_or_equal:period_start'],'notes'=>['nullable','string','max:2000']]);
        $run=DB::transaction(function()use($data,$request){$run=PayrollRun::create($data+['payroll_number'=>$this->nextNumber(),'status'=>'draft','prepared_by'=>$request->user()->id]);$nssfEmployee=$this->setting('nssf_employee_rate',5)/100;$nssfEmployer=$this->setting('nssf_employer_rate',10)/100;foreach(Employee::active()->where('pay_frequency',$data['frequency'])->orderBy('staff_no')->get() as $employee){$basic=(float)$employee->base_salary;$advance=(float)EmployeeRecord::where('employee_id',$employee->id)->where('type','advances')->whereNull('payroll_run_id')->whereBetween('recorded_on',[$data['period_start'],$data['period_end']])->sum('amount');$penalties=(float)EmployeeRecord::where('employee_id',$employee->id)->where('is_payroll_deduction',true)->where('payroll_deduction_status','approved')->whereNull('payroll_run_id')->where('recorded_on','<=',$data['period_end'])->sum('amount');$gross=$basic;$nssf=round($gross*$nssfEmployee,2);$employer=round($gross*$nssfEmployer,2);$deductions=$nssf+$advance+$penalties;PayrollLine::create(['payroll_run_id'=>$run->id,'employee_id'=>$employee->id,'basic_pay'=>$basic,'allowances'=>0,'overtime_pay'=>0,'gross_pay'=>$gross,'nssf_employee'=>$nssf,'paye'=>0,'salary_advance'=>$advance,'penalties'=>$penalties,'other_deductions'=>0,'total_deductions'=>$deductions,'net_pay'=>max(0,$gross-$deductions),'nssf_employer'=>$employer,'payment_method'=>$employee->payment_method,'payment_account'=>$employee->bank_account?:$employee->mobile_money_number]);} $this->recalculate($run);return $run;});return redirect()->route('payroll.show',$run)->with('status','Draft payroll generated. Review every line before submission.');
    }

    public function show(Request $request,PayrollRun $payroll):View
    {
        abort_unless($request->user()->canAccess('payroll'),403);$payroll->load(['lines.employee.department','approvals.steps.decisionMaker']);return view('payroll.show',['payroll'=>$payroll,'approval'=>$payroll->approvals->sortByDesc('id')->first(),'isHr'=>$this->hasRole($request,'hr-manager'),'isFinance'=>$this->hasRole($request,'finance-manager')]);
    }

    public function updateLine(Request $request,PayrollRun $payroll,PayrollLine $line):RedirectResponse
    {
        abort_unless($line->payroll_run_id===$payroll->id&&$payroll->status==='draft'&&$this->hasRole($request,'hr-manager'),403);
        $d=$request->validate(['basic_pay'=>['required','numeric','min:0'],'allowances'=>['required','numeric','min:0'],'overtime_pay'=>['required','numeric','min:0'],'paye'=>['required','numeric','min:0'],'other_deductions'=>['required','numeric','min:0'],'payment_method'=>['nullable','in:cash,bank,mobile_money'],'payment_account'=>['nullable','string','max:120'],'notes'=>['nullable','string','max:1000']]);$gross=(float)$d['basic_pay']+(float)$d['allowances']+(float)$d['overtime_pay'];$nssf=round($gross*$this->setting('nssf_employee_rate',5)/100,2);$employer=round($gross*$this->setting('nssf_employer_rate',10)/100,2);$deductions=$nssf+(float)$d['paye']+(float)$line->salary_advance+(float)$line->penalties+(float)$d['other_deductions'];$line->update($d+['gross_pay'=>$gross,'nssf_employee'=>$nssf,'nssf_employer'=>$employer,'total_deductions'=>$deductions,'net_pay'=>max(0,$gross-$deductions)]);$this->recalculate($payroll);return back()->with('status','Payroll line updated.');
    }

    public function submit(Request $request,PayrollRun $payroll):RedirectResponse
    {
        abort_unless($payroll->status==='draft'&&$this->hasRole($request,'hr-manager'),403);abort_if($payroll->lines()->count()===0,422,'Payroll has no employees.');DB::transaction(function()use($payroll,$request){$payroll->update(['status'=>'submitted']);$approval=$payroll->approvals()->create(['module_key'=>'payroll','department_id'=>$request->user()->department_id,'submitted_by'=>$request->user()->id,'submitted_at'=>now(),'submission_note'=>'Payroll '.$payroll->payroll_number.' submitted for financial review.','required_levels'=>3,'due_at'=>now()->addDays(2)]);$approval->steps()->createMany([['stage'=>1,'role_code'=>'finance-manager','status'=>'pending'],['stage'=>2,'role_code'=>'ceo','status'=>'waiting'],['stage'=>3,'role_code'=>'director','status'=>'waiting']]);});return back()->with('status','Payroll sent to the Finance Manager for review.');
    }

    public function pay(Request $request,PayrollRun $payroll):RedirectResponse
    {
        abort_unless($payroll->status==='approved'&&$this->hasRole($request,'finance-manager'),403);$d=$request->validate(['pay_date'=>['required','date'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required','in:cash,bank,mobile_money'],'reference'=>['nullable','string','max:100']]);DB::transaction(function()use($payroll,$d,$request){FinancialTransaction::create(['transaction_date'=>$d['pay_date'],'type'=>'money_paid','account'=>$d['farm_account'],'counterparty'=>'Employees','amount'=>$payroll->net_total,'payment_method'=>$d['payment_method'],'reference'=>$d['reference']?:$payroll->payroll_number,'description'=>'Net payroll payment '.$payroll->payroll_number,'recorded_by'=>$request->user()->id]);$payroll->update(['status'=>'paid','pay_date'=>$d['pay_date'],'paid_by'=>$request->user()->id,'paid_at'=>now()]);EmployeeRecord::whereNull('payroll_run_id')->where(function($q)use($payroll){$q->where(function($x)use($payroll){$x->where('type','advances')->whereBetween('recorded_on',[$payroll->period_start,$payroll->period_end]);})->orWhere(function($x)use($payroll){$x->where('is_payroll_deduction',true)->where('payroll_deduction_status','approved')->where('recorded_on','<=',$payroll->period_end);});})->update(['payroll_run_id'=>$payroll->id,'payroll_deduction_status'=>DB::raw("case when is_payroll_deduction = 1 then 'processed' else payroll_deduction_status end"),'updated_at'=>now()]);});return back()->with('status','Payroll marked paid and posted to Finance.');
    }

    private function recalculate(PayrollRun $run):void{$run->update(['gross_total'=>$run->lines()->sum('gross_pay'),'deduction_total'=>$run->lines()->sum('total_deductions'),'net_total'=>$run->lines()->sum('net_pay'),'employer_contribution_total'=>$run->lines()->sum('nssf_employer')]);}
    private function setting(string $key,float $default):float{return (float)(DB::table('finance_settings')->where('group','tax')->where('key',$key)->value('value')??$default);}
    private function hasRole(Request $request,string $role):bool{return $request->user()->role?->code===$role||$request->user()->roles->contains('code',$role);}
    private function nextNumber():string{$year=now()->format('y');$last=PayrollRun::where('payroll_number','like',"PAY-%/{$year}")->orderByRaw('LENGTH(payroll_number) DESC, payroll_number DESC')->value('payroll_number');$n=$last?(int)substr($last,4,strpos($last,'/')-4):0;return sprintf('PAY-%04d/%s',$n+1,$year);}
}
