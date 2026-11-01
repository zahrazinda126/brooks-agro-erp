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
            'chart-of-accounts'=>DB::table('finance_accounts')->orderByRaw('sort_order is null')->orderBy('sort_order')->orderBy('code')->get(),
            'finance-periods'=>DB::table('finance_periods')->orderByDesc('starts_on')->get(),
            'finance-accounts'=>DB::table('finance_payment_accounts')->orderBy('kind')->orderBy('name')->get(),
            'payment-methods'=>DB::table('finance_payment_methods')->orderBy('name')->get(),
            'document-numbering'=>DB::table('finance_sequences')->orderBy('document_type')->get(),
            'tax-settings'=>DB::table('finance_settings')->where('group','tax')->orderBy('label')->get(),
            'budget-settings'=>DB::table('finance_settings')->where('group','budget')->orderBy('label')->get(),
            'finance-imports'=>DB::table('finance_import_batches')->latest()->get(),
            'audit'=>$this->auditTrail($request),
        };
        return view('finance.administration.index',['screen'=>$screen,'title'=>self::TITLES[$screen],'items'=>$items]+($screen==='audit'?$this->auditFilterOptions():[]));
    }

    private const AUDIT_MODULES = ['finance', 'transactions', 'budget', 'assets', 'purchases', 'approvals', 'payables'];

    /** Finance audit trail, narrowed by dates, person, area, action and a search of the details. */
    private function auditTrail(Request $request)
    {
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'user' => ['nullable', 'integer'], 'module' => ['nullable', 'string', 'max:40'], 'event' => ['nullable', 'string', 'max:80'], 'q' => ['nullable', 'string', 'max:120']]);
        return AuditLog::with('user:id,name,username')->whereIn('module_key', self::AUDIT_MODULES)
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($f['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['module'] ?? null, fn ($q, $m) => $q->where('module_key', $m))
            ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($f['q'] ?? null, fn ($q, $term) => $q->whereRaw('lower(description) like ?', ['%'.mb_strtolower($term).'%']))
            ->latest('created_at')->paginate(50)->withQueryString();
    }

    private function auditFilterOptions(): array
    {
        $base = AuditLog::whereIn('module_key', self::AUDIT_MODULES);
        return [
            'auditUsers' => \App\Models\User::whereIn('id', (clone $base)->whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name', 'username']),
            'auditModules' => (clone $base)->distinct()->orderBy('module_key')->pluck('module_key'),
            'auditEvents' => (clone $base)->distinct()->orderBy('event')->pluck('event'),
        ];
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
