<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Receivable;
use App\Models\CustomerPrepayment;
use App\Models\ReceivablePayment;
use App\Models\FinancialTransaction;
use App\Models\SalesCustomer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivableController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('receivables'), 403);
        $items = Receivable::query()->latest('invoice_date')->paginate(30);
        $outstanding = Receivable::where('status', 'open')->get()->sum(fn (Receivable $item) => (float) $item->amount - (float) $item->amount_paid);
        $openItems = Receivable::where('status', 'open')->orderBy('customer_name')->get();
        $credits = CustomerPrepayment::whereRaw('amount_used < amount')->orderBy('customer_name')->get();
        $customers = SalesCustomer::where('is_active', true)->orderBy('name')->get();
        $farmAccounts = ['Cash on Hand','Petty Cash (UGX)','Centenary Bank - Sales','Centenary Bank - Operations','Centenary Bank - Garden','In-kind / Barter Clearing'];
        return view('finance.receivables.index', compact('items', 'outstanding', 'openItems', 'credits', 'customers', 'farmAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('receivables', 'create'), 403);
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        Receivable::create($data + ['amount_paid' => 0, 'status' => 'open', 'recorded_by' => $request->user()->id]);
        return back()->with('status', 'Customer amount due recorded.');
    }

    public function recordPayment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('receivables', 'create'), 403);
        $data=$request->validate(['receivable_id'=>['required','exists:receivables,id'],'received_date'=>['required','date'],'amount'=>['required','numeric','min:1'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required','in:cash,bank,mobile_money,credit,in_kind'],'reference'=>['nullable','string','max:80']]);
        if ($data['payment_method'] === 'in_kind') $data['farm_account'] = 'In-kind / Barter Clearing';
        $receivable=Receivable::findOrFail($data['receivable_id']); $balance=(float)$receivable->amount-(float)$receivable->amount_paid;
        if((float)$data['amount']>$balance) return back()->withErrors(['amount'=>'This is more than the customer still owes. Record the excess as a prepayment.'])->withInput();
        DB::transaction(function() use($data,$receivable,$request){ ReceivablePayment::create($data+['recorded_by'=>$request->user()->id]); $receivable->amount_paid+=(float)$data['amount']; $receivable->status=(float)$receivable->amount_paid >= (float)$receivable->amount ? 'paid' : 'open'; $receivable->save(); FinancialTransaction::create(['transaction_date'=>$data['received_date'],'type'=>'money_received','account'=>$data['farm_account'],'counterparty'=>$receivable->customer_name,'amount'=>$data['amount'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference'],'description'=>'Payment reducing balance of '.($receivable->invoice_number ?: 'customer amount due'),'recorded_by'=>$request->user()->id]); });
        return back()->with('status','Customer payment recorded and amount due reduced.');
    }

    public function recordPrepayment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('receivables', 'create'), 403);
        $data=$request->validate(['customer_name'=>['required','string','max:160'],'received_date'=>['required','date'],'amount'=>['required','numeric','min:1'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required','in:cash,bank,mobile_money,credit,in_kind'],'reference'=>['nullable','string','max:80']]);
        if ($data['payment_method'] === 'in_kind') $data['farm_account'] = 'In-kind / Barter Clearing';
        DB::transaction(function() use($data,$request){ $customer=SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower(trim($data['customer_name']))])->first() ?: SalesCustomer::create(['name'=>trim($data['customer_name']),'credit_limit'=>0,'is_active'=>true]);$data['customer_name']=$customer->name;CustomerPrepayment::create($data+['customer_id'=>$customer->id,'amount_used'=>0,'recorded_by'=>$request->user()->id]); FinancialTransaction::create(['transaction_date'=>$data['received_date'],'type'=>'money_received','transaction_category'=>'customer_prepayment','account_group'=>'Current Liabilities','entry_account'=>'Customer Prepayments','account'=>$data['farm_account'],'counterparty'=>$data['customer_name'],'amount'=>$data['amount'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference'],'description'=>'Customer prepayment (credit held for a future sale)','recorded_by'=>$request->user()->id]); });
        return back()->with('status','Customer prepayment recorded as available credit.');
    }

    public function applyPrepayment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('receivables', 'create'), 403);
        $data=$request->validate(['receivable_id'=>['required','exists:receivables,id'],'prepayment_id'=>['required','exists:customer_prepayments,id'],'amount'=>['required','numeric','min:1']]); $receivable=Receivable::findOrFail($data['receivable_id']); $credit=CustomerPrepayment::findOrFail($data['prepayment_id']);
        $balance=(float)$receivable->amount-(float)$receivable->amount_paid; $available=(float)$credit->amount-(float)$credit->amount_used;
        if(strcasecmp($receivable->customer_name,$credit->customer?->name ?? $credit->customer_name)!==0) return back()->withErrors(['prepayment_id'=>'Choose credit belonging to the same customer account.'])->withInput(); if((float)$data['amount']>$balance || (float)$data['amount']>$available) return back()->withErrors(['amount'=>'Amount cannot exceed the unpaid balance or available customer credit.'])->withInput();
        DB::transaction(function() use($data,$receivable,$credit,$request){ $credit->amount_used+=(float)$data['amount'];$credit->save();$receivable->amount_paid+=(float)$data['amount'];$receivable->status=(float)$receivable->amount_paid >= (float)$receivable->amount?'paid':'open';$receivable->save();ReceivablePayment::create(['receivable_id'=>$receivable->id,'received_date'=>now()->toDateString(),'amount'=>$data['amount'],'source'=>'prepayment','recorded_by'=>$request->user()->id]); });
        return back()->with('status','Customer credit applied and amount due reduced.');
    }
}
