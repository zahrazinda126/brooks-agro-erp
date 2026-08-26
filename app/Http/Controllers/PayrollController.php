<?php

namespace App\Http\Controllers;

use App\Models\{Approval,AuditLog,Employee,EmployeeRecord,FinancialTransaction,PayrollLine,PayrollRun,StaffSalaryWalletTransaction};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\CommunicationService;
use App\Services\PoultryPerformanceService;
use App\Services\UgandaPayrollCalculator;

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

    public function myBalance(Request $request):View
    {
        $employee=$request->user()->employee;
        abort_unless($employee,404,'Your login is not linked to an employee record. Ask HR to link it.');
        $transactions=StaffSalaryWalletTransaction::where('employee_id',$employee->id)->with('recorder')->latest('transaction_date')->latest('id')->get();
        $credited=(float)$transactions->where('type','credit')->sum('amount');$withdrawn=(float)$transactions->where('type','withdrawal')->sum('amount');
        return view('payroll.my-balance',compact('employee','transactions','credited','withdrawn')+['balance'=>$credited-$withdrawn]);
    }

    public function salaryAccount(Request $request, Employee $employee): View
    {
        abort_unless($request->user()->canAccess('payroll'), 403);

        $employee->load(['department', 'role']);
        $transactions = StaffSalaryWalletTransaction::where('employee_id', $employee->id)
            ->with(['payrollRun', 'payrollLine', 'recorder'])
            ->latest('transaction_date')->latest('id')->get();
        $lines = PayrollLine::where('employee_id', $employee->id)
            ->with('run')->latest('id')->get();
        $credited = (float) $transactions->where('type', 'credit')->sum('amount');
        $withdrawn = (float) $transactions->where('type', 'withdrawal')->sum('amount');

        return view('payroll.salary-account', [
            'employee' => $employee,
            'transactions' => $transactions,
            'lines' => $lines,
            'credited' => $credited,
            'withdrawn' => $withdrawn,
            'balance' => $credited - $withdrawn,
            'waiting' => (float) $lines->filter(fn (PayrollLine $line) =>
                (float) $line->wallet_credited === 0.0
                && in_array($line->run?->status, ['approved', 'partly_funded', 'partly_paid'], true)
            )->sum('net_pay'),
            'isFinance' => $this->hasRole($request, 'finance-manager'),
        ]);
    }

    public function generate(Request $request):RedirectResponse
    {
        abort_unless($this->hasRole($request,'hr-manager')&&$request->user()->canDo('payroll','create'),403);
        $data=$request->validate(['frequency'=>['required','in:monthly,weekly,daily'],'period_start'=>['required','date'],'period_end'=>['required','date','after_or_equal:period_start'],'notes'=>['nullable','string','max:2000']]);
        $existing = PayrollRun::where('frequency', $data['frequency'])
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->first();

        if ($existing) {
            return redirect()->route('payroll.show', $existing)->with(
                'status',
                "Payroll {$existing->payroll_number} already covers this period, so no duplicate was created."
            );
        }

        $shortfalls=PoultryPerformanceService::shortfallsByEmployee($data['period_start'],$data['period_end']);
        $run=DB::transaction(function()use($data,$request,$shortfalls){$run=PayrollRun::create($data+['payroll_number'=>$this->nextNumber(),'status'=>'draft','prepared_by'=>$request->user()->id]);$nssfEmployee=$this->setting('nssf_employee_rate',5)/100;$nssfEmployer=$this->setting('nssf_employer_rate',10)/100;foreach(Employee::active()->where('pay_frequency',$data['frequency'])->orderBy('staff_no')->get() as $employee){$basic=(float)$employee->base_salary;$advance=(float)EmployeeRecord::where('employee_id',$employee->id)->where('type','advances')->whereNull('payroll_run_id')->whereBetween('recorded_on',[$data['period_start'],$data['period_end']])->sum('amount');$penalties=(float)EmployeeRecord::where('employee_id',$employee->id)->where('is_payroll_deduction',true)->where('payroll_deduction_status','approved')->whereNull('payroll_run_id')->where('recorded_on','<=',$data['period_end'])->sum('amount');$performance=(float)($shortfalls[$employee->id]??0);$gross=$basic;$nssf=round($gross*$nssfEmployee,2);$employer=round($gross*$nssfEmployer,2);$paye=$this->payeForRun($employee->id,$gross,$data['period_end'],$run->id);$deductions=$nssf+$paye+$advance+$performance+$penalties;PayrollLine::create(['payroll_run_id'=>$run->id,'employee_id'=>$employee->id,'basic_pay'=>$basic,'allowances'=>0,'overtime_pay'=>0,'gross_pay'=>$gross,'nssf_employee'=>$nssf,'paye'=>$paye,'sacco'=>0,'salary_advance'=>$advance,'performance_adjustment'=>$performance,'penalties'=>$penalties,'arrears'=>0,'other_deductions'=>0,'total_deductions'=>$deductions,'net_pay'=>max(0,$gross-$deductions),'nssf_employer'=>$employer,'payment_method'=>$employee->payment_method,'payment_account'=>$employee->bank_account?:$employee->mobile_money_number,'payment_status'=>'pending']);} $this->recalculate($run);return $run;});return redirect()->route('payroll.show',$run)->with('status','Draft payroll generated with automatic resident PAYE, employee NSSF and the full-period poultry performance shortfall. Review every line before submission.');
    }

    public function show(Request $request,PayrollRun $payroll):View
    {
        abort_unless($request->user()->canAccess('payroll'),403);
        if ($payroll->status === 'draft') {
            $this->recalculateDraftLines($payroll);
        }
        $payroll->refresh()->load(['lines.employee.department','approvals.steps.decisionMaker']);
        $accounts=DB::table('finance_payment_accounts')->where('is_active',true)->orderBy('name')->pluck('name');
        return view('payroll.show',['payroll'=>$payroll,'approval'=>$payroll->approvals->sortByDesc('id')->first(),'isHr'=>$this->hasRole($request,'hr-manager'),'isFinance'=>$this->hasRole($request,'finance-manager'),'accounts'=>$accounts]);
    }

    public function updateLine(Request $request,PayrollRun $payroll,PayrollLine $line):RedirectResponse
    {
        abort_unless($line->payroll_run_id===$payroll->id&&$payroll->status==='draft'&&$this->hasRole($request,'hr-manager'),403);
        $d=$request->validate(['basic_pay'=>['required','numeric','min:0'],'allowances'=>['required','numeric','min:0'],'overtime_pay'=>['required','numeric','min:0'],'sacco'=>['required','numeric','min:0'],'salary_advance'=>['required','numeric','min:0'],'performance_adjustment'=>['required','numeric','min:0'],'penalties'=>['required','numeric','min:0'],'arrears'=>['required','numeric','min:0'],'other_deductions'=>['required','numeric','min:0'],'payment_method'=>['nullable','in:cash,bank,mobile_money'],'payment_account'=>['nullable','string','max:120'],'payment_status'=>['required','in:pending,cleared,held'],'notes'=>['nullable','string','max:1000']]);$gross=(float)$d['basic_pay']+(float)$d['allowances']+(float)$d['overtime_pay']+(float)$d['arrears'];$nssf=round($gross*$this->setting('nssf_employee_rate',5)/100,2);$employer=round($gross*$this->setting('nssf_employer_rate',10)/100,2);$paye=$this->payeForRun($line->employee_id,$gross,$payroll->period_end,$payroll->id);$deductions=$nssf+$paye+(float)$d['sacco']+(float)$d['salary_advance']+(float)$d['performance_adjustment']+(float)$d['penalties']+(float)$d['other_deductions'];$line->update($d+['gross_pay'=>$gross,'nssf_employee'=>$nssf,'nssf_employer'=>$employer,'paye'=>$paye,'total_deductions'=>$deductions,'net_pay'=>max(0,$gross-$deductions)]);$this->recalculate($payroll);return back()->with('status','Payroll line updated and PAYE recalculated.');
    }

    public function submit(Request $request,PayrollRun $payroll):RedirectResponse
    {
        abort_unless($payroll->status==='draft'&&$this->hasRole($request,'hr-manager'),403);abort_if($payroll->lines()->count()===0,422,'Payroll has no employees.');DB::transaction(function()use($payroll,$request){$payroll->update(['status'=>'submitted']);$approval=$payroll->approvals()->create(['module_key'=>'payroll','department_id'=>$request->user()->department_id,'submitted_by'=>$request->user()->id,'submitted_at'=>now(),'submission_note'=>'Payroll '.$payroll->payroll_number.' submitted for financial review.','required_levels'=>3,'due_at'=>now()->addDays(2)]);$approval->steps()->createMany([['stage'=>1,'role_code'=>'finance-manager','status'=>'pending'],['stage'=>2,'role_code'=>'ceo','status'=>'waiting'],['stage'=>3,'role_code'=>'director','status'=>'waiting']]);app(CommunicationService::class)->toRole('finance-manager','Payroll awaiting your review','Payroll '.$payroll->payroll_number.' was submitted by '.$request->user()->displayName().'.',$request->user()->id);});return back()->with('status','Payroll sent to the Finance Manager for review.');
    }

    public function pay(Request $request,PayrollRun $payroll):RedirectResponse
    {
        abort_unless($payroll->status==='approved'&&$this->hasRole($request,'finance-manager'),403);$d=$request->validate(['pay_date'=>['required','date'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required','in:cash,bank,mobile_money'],'reference'=>['nullable','string','max:100']]);DB::transaction(function()use($payroll,$d,$request){FinancialTransaction::create(['transaction_date'=>$d['pay_date'],'type'=>'money_paid','account'=>$d['farm_account'],'counterparty'=>'Employees','amount'=>$payroll->net_total,'payment_method'=>$d['payment_method'],'reference'=>$d['reference']?:$payroll->payroll_number,'description'=>'Net payroll payment '.$payroll->payroll_number,'recorded_by'=>$request->user()->id]);$payroll->update(['status'=>'paid','pay_date'=>$d['pay_date'],'paid_by'=>$request->user()->id,'paid_at'=>now()]);EmployeeRecord::whereNull('payroll_run_id')->where(function($q)use($payroll){$q->where(function($x)use($payroll){$x->where('type','advances')->whereBetween('recorded_on',[$payroll->period_start,$payroll->period_end]);})->orWhere(function($x)use($payroll){$x->where('is_payroll_deduction',true)->where('payroll_deduction_status','approved')->where('recorded_on','<=',$payroll->period_end);});})->update(['payroll_run_id'=>$payroll->id,'payroll_deduction_status'=>DB::raw("case when is_payroll_deduction then 'processed' else payroll_deduction_status end"),'updated_at'=>now()]);});return back()->with('status','Payroll marked paid and posted to Finance.');
    }

    public function fund(Request $request,PayrollRun $payroll):RedirectResponse
    {
        abort_unless(in_array($payroll->status,['approved','partly_funded','partly_paid'],true)&&$this->hasRole($request,'finance-manager'),403);
        $data=$request->validate(['pay_date'=>['required','date'],'farm_account'=>['required',Rule::exists('finance_payment_accounts','name')->where('is_active',true)],'reference'=>['nullable','string','max:100'],'lines'=>['required','array','min:1'],'lines.*'=>['integer','exists:payroll_lines,id']]);
        DB::transaction(function()use($payroll,$data,$request){
            $selected=$payroll->lines()->whereIn('id',$data['lines'])->where('wallet_credited',0)->lockForUpdate()->get();abort_if($selected->isEmpty(),422,'Choose at least one employee who is still waiting for funding.');$fundedAmount=(float)$selected->sum('net_pay');
            $selected->each(function($line)use($payroll,$data,$request){
                StaffSalaryWalletTransaction::create(['employee_id'=>$line->employee_id,'payroll_run_id'=>$payroll->id,'payroll_line_id'=>$line->id,'type'=>'credit','amount'=>$line->net_pay,'transaction_date'=>$data['pay_date'],'company_account'=>$data['farm_account'],'reference'=>$data['reference']?:$payroll->payroll_number,'notes'=>'Net salary credited to staff balance','recorded_by'=>$request->user()->id]);
                $line->update(['wallet_credited'=>$line->net_pay,'wallet_withdrawn'=>0,'payment_status'=>'credited']);
            });
            FinancialTransaction::create(['transaction_date'=>$data['pay_date'],'type'=>'transfer','account'=>$data['farm_account'],'counterparty'=>'Staff Salary Holding Account','amount'=>$fundedAmount,'payment_method'=>null,'reference'=>$data['reference']?:$payroll->payroll_number,'description'=>'Selected payroll staff funded into salary balances '.$payroll->payroll_number,'recorded_by'=>$request->user()->id]);
            $unfunded=$payroll->lines()->where('wallet_credited',0)->count();$withdrawn=(float)$payroll->lines()->sum('wallet_withdrawn');$payroll->update(['status'=>$unfunded?'partly_funded':($withdrawn>0?'partly_paid':'funded'),'funding_account'=>$data['farm_account'],'funding_reference'=>$data['reference']?:$payroll->payroll_number,'funded_by'=>$request->user()->id,'funded_at'=>now()]);
            EmployeeRecord::whereNull('payroll_run_id')->where(function($q)use($payroll){$q->where(function($x)use($payroll){$x->where('type','advances')->whereBetween('recorded_on',[$payroll->period_start,$payroll->period_end]);})->orWhere(function($x)use($payroll){$x->where('is_payroll_deduction',true)->where('payroll_deduction_status','approved')->where('recorded_on','<=',$payroll->period_end);});})->update(['payroll_run_id'=>$payroll->id,'payroll_deduction_status'=>DB::raw("case when is_payroll_deduction then 'processed' else payroll_deduction_status end"),'updated_at'=>now()]);
        });
        return back()->with('status','The selected employees were funded. Employees left unticked remain waiting.');
    }

    public function withdraw(Request $request,PayrollRun $payroll,PayrollLine $line):RedirectResponse
    {
        abort_unless($line->payroll_run_id===$payroll->id&&in_array($payroll->status,['funded','partly_funded','partly_paid'],true)&&$this->hasRole($request,'finance-manager'),403);
        $available=(float)$line->wallet_credited-(float)$line->wallet_withdrawn;
        $data=$request->validate(['amount'=>['required','numeric','min:1','max:'.$available],'withdrawn_on'=>['required','date'],'payment_method'=>['required','in:cash,bank,mobile_money'],'reference'=>['required','string','max:100'],'confirmation'=>['required','string','max:500']]);
        DB::transaction(function()use($payroll,$line,$data,$request){
            $locked=PayrollLine::lockForUpdate()->findOrFail($line->id);$balance=(float)$locked->wallet_credited-(float)$locked->wallet_withdrawn;abort_if((float)$data['amount']>$balance,422,'Withdrawal exceeds the employee balance.');
            StaffSalaryWalletTransaction::create(['employee_id'=>$locked->employee_id,'payroll_run_id'=>$payroll->id,'payroll_line_id'=>$locked->id,'type'=>'withdrawal','amount'=>$data['amount'],'transaction_date'=>$data['withdrawn_on'],'company_account'=>'Staff Salary Holding Account','payment_method'=>$data['payment_method'],'reference'=>$data['reference'],'notes'=>$data['confirmation'],'recorded_by'=>$request->user()->id]);
            $withdrawn=(float)$locked->wallet_withdrawn+(float)$data['amount'];$locked->update(['wallet_withdrawn'=>$withdrawn,'payment_status'=>$withdrawn>=(float)$locked->wallet_credited?'cleared':'partly_withdrawn']);
            $remaining=(float)$payroll->lines()->sum(DB::raw('wallet_credited-wallet_withdrawn'));$unfunded=$payroll->lines()->where('wallet_credited',0)->count();$complete=$unfunded===0&&$remaining<=0;$payroll->update(['status'=>$unfunded?'partly_funded':($complete?'paid':'partly_paid'),'paid_at'=>$complete?now():null,'paid_by'=>$complete?$request->user()->id:null]);
        });
        return back()->with('status','Employee withdrawal recorded. The staff balance and payroll status were updated.');
    }

    public function withdrawalForm(Request $request, PayrollRun $payroll, PayrollLine $line): View
    {
        abort_unless($line->payroll_run_id === $payroll->id && $this->hasRole($request, 'finance-manager'), 403);
        abort_unless(in_array($payroll->status, ['funded', 'partly_funded', 'partly_paid', 'paid'], true), 403);

        $available = (float) $line->wallet_credited - (float) $line->wallet_withdrawn;
        $history = StaffSalaryWalletTransaction::where('payroll_line_id', $line->id)
            ->with('recorder')->latest('transaction_date')->latest('id')->get();

        return view('payroll.withdrawal', compact('payroll', 'line', 'available', 'history'));
    }

    private function recalculate(PayrollRun $run):void{$run->update(['gross_total'=>$run->lines()->sum('gross_pay'),'deduction_total'=>$run->lines()->sum('total_deductions'),'net_total'=>$run->lines()->sum('net_pay'),'employer_contribution_total'=>$run->lines()->sum('nssf_employer')]);}
    private function recalculateDraftLines(PayrollRun $run):void
    {
        DB::transaction(function () use ($run) {
            $employeeRate=$this->setting('nssf_employee_rate',5)/100;
            $employerRate=$this->setting('nssf_employer_rate',10)/100;
            foreach($run->lines()->get() as $line){
                $gross=(float)$line->basic_pay+(float)$line->allowances+(float)$line->overtime_pay+(float)$line->arrears;
                $nssf=round($gross*$employeeRate,2);
                $employer=round($gross*$employerRate,2);
                $paye=$this->payeForRun($line->employee_id,$gross,$run->period_end,$run->id);
                $deductions=$nssf+$paye+(float)$line->sacco+(float)$line->salary_advance+(float)$line->performance_adjustment+(float)$line->penalties+(float)$line->other_deductions;
                $line->update(['gross_pay'=>$gross,'nssf_employee'=>$nssf,'nssf_employer'=>$employer,'paye'=>$paye,'total_deductions'=>$deductions,'net_pay'=>max(0,$gross-$deductions)]);
            }
            $this->recalculate($run);
        });
    }
    private function payeForRun(int $employeeId,float $gross,$periodEnd,?int $excludeRunId=null):float{$date=\Carbon\Carbon::parse($periodEnd);$prior=PayrollLine::query()->join('payroll_runs','payroll_runs.id','=','payroll_lines.payroll_run_id')->where('payroll_lines.employee_id',$employeeId)->whereMonth('payroll_runs.period_end',$date->month)->whereYear('payroll_runs.period_end',$date->year)->when($excludeRunId,fn($q)=>$q->where('payroll_runs.id','!=',$excludeRunId))->whereNotIn('payroll_runs.status',['cancelled','rejected'])->selectRaw('coalesce(sum(payroll_lines.gross_pay),0) gross, coalesce(sum(payroll_lines.paye),0) paye')->first();$monthlyTax=app(UgandaPayrollCalculator::class)->residentPaye((float)$prior->gross+$gross);return max(0,round($monthlyTax-(float)$prior->paye,2));}
    private function setting(string $key,float $default):float{return (float)(DB::table('finance_settings')->where('group','tax')->where('key',$key)->value('value')??$default);}
    private function hasRole(Request $request,string $role):bool{return $request->user()->role?->code===$role||$request->user()->roles->contains('code',$role);}
    private function nextNumber():string{$year=now()->format('y');$last=PayrollRun::where('payroll_number','like',"PAY-%/{$year}")->orderByRaw('LENGTH(payroll_number) DESC, payroll_number DESC')->value('payroll_number');$n=$last?(int)substr($last,4,strpos($last,'/')-4):0;return sprintf('PAY-%04d/%s',$n+1,$year);}
}
