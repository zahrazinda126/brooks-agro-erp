<?php
namespace App\Http\Controllers\Finance;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceAdministrationController extends Controller
{
    private const TITLES=['chart-of-accounts'=>'Chart of Accounts','finance-periods'=>'Financial Periods','finance-accounts'=>'Bank & Cash Accounts','payment-methods'=>'Payment Methods','document-numbering'=>'Document Numbering','tax-settings'=>'Tax & Payroll Settings','budget-settings'=>'Budget Settings','finance-imports'=>'Import & Reconciliation','audit'=>'Finance Audit Trail'];

    public function index(Request $request,string $screen):View
    {
        abort_unless(isset(self::TITLES[$screen]),404);
        abort_unless($request->user()->canAccess($screen)||($screen==='audit'&&$request->user()->canAccess('audit')),403);
        $items=match($screen){
            'chart-of-accounts'=>DB::table('finance_accounts')->orderBy('code')->get(),
            'finance-periods'=>DB::table('finance_periods')->orderByDesc('starts_on')->get(),
            'finance-accounts'=>DB::table('finance_payment_accounts')->orderBy('kind')->orderBy('name')->get(),
            'payment-methods'=>DB::table('finance_payment_methods')->orderBy('name')->get(),
            'document-numbering'=>DB::table('finance_sequences')->orderBy('document_type')->get(),
            'tax-settings'=>DB::table('finance_settings')->where('group','tax')->orderBy('label')->get(),
            'budget-settings'=>DB::table('finance_settings')->where('group','budget')->orderBy('label')->get(),
            'finance-imports'=>DB::table('finance_import_batches')->latest()->get(),
            'audit'=>AuditLog::whereIn('module_key',['finance','transactions','budget','assets','purchases','approvals'])->latest('created_at')->paginate(50),
        };
        return view('finance.administration.index',['screen'=>$screen,'title'=>self::TITLES[$screen],'items'=>$items]);
    }

    public function store(Request $request,string $screen):RedirectResponse
    {
        abort_unless($request->user()->canDo($screen,'create')||$request->user()->canDo($screen,'edit'),403);
        match($screen){
            'chart-of-accounts'=>$this->account($request),
            'finance-periods'=>$this->period($request),
            'finance-accounts'=>$this->paymentAccount($request),
            'payment-methods'=>$this->paymentMethod($request),
            'document-numbering'=>$this->sequence($request),
            'tax-settings','budget-settings'=>$this->settings($request,$screen==='tax-settings'?'tax':'budget'),
            default=>abort(422),
        };
        AuditLog::record('finance_setting_updated', null, self::TITLES[$screen].' updated.', null, ['screen'=>$screen], 'finance');
        return back()->with('status',self::TITLES[$screen].' updated.');
    }

    private function account($r):void {$d=$r->validate(['code'=>['required','string','max:30','unique:finance_accounts,code'],'name'=>['required','string','max:160'],'type'=>['required','in:asset,liability,equity,income,cogs,expense']]);DB::table('finance_accounts')->insert($d+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
    private function period($r):void {$d=$r->validate(['name'=>['required','string','max:100'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after:starts_on']]);DB::table('finance_periods')->insert($d+['status'=>'open','created_at'=>now(),'updated_at'=>now()]);}
    private function paymentAccount($r):void {$d=$r->validate(['name'=>['required','string','max:160','unique:finance_payment_accounts,name'],'kind'=>['required','in:cash,bank,mobile_money'],'currency'=>['required','size:3'],'institution'=>['nullable','string','max:120'],'account_number'=>['nullable','string','max:80']]);DB::table('finance_payment_accounts')->insert($d+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
    private function paymentMethod($r):void {$d=$r->validate(['code'=>['required','string','max:40','unique:finance_payment_methods,code'],'name'=>['required','string','max:100']]);DB::table('finance_payment_methods')->insert($d+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
    private function sequence($r):void {$d=$r->validate(['document_type'=>['required','string','max:60'],'prefix'=>['required','string','max:30'],'next_number'=>['required','integer','min:1'],'padding'=>['required','integer','min:1','max:12']]);DB::table('finance_sequences')->updateOrInsert(['document_type'=>$d['document_type']],$d+['created_at'=>now(),'updated_at'=>now()]);}
    private function settings($r,$group):void {$d=$r->validate(['settings'=>['required','array'],'settings.*'=>['nullable','string','max:500']]);foreach($d['settings'] as $id=>$value)DB::table('finance_settings')->where('id',$id)->where('group',$group)->update(['value'=>$value,'updated_at'=>now()]);}

    public function togglePeriod(Request $request,int $period):RedirectResponse
    {
        abort_unless($request->user()->canDo('finance-periods','edit'),403);
        $row=DB::table('finance_periods')->where('id',$period)->first();abort_unless($row,404);
        $closed=$row->status==='open';
        DB::table('finance_periods')->where('id',$period)->update(['status'=>$closed?'closed':'open','closed_by'=>$closed?$request->user()->id:null,'closed_at'=>$closed?now():null,'updated_at'=>now()]);
        AuditLog::record($closed?'financial_period_closed':'financial_period_reopened', null, $row->name, ['status'=>$row->status], ['status'=>$closed?'closed':'open'], 'finance');
        return back()->with('status',$closed?'Financial period closed.':'Financial period reopened.');
    }
}
