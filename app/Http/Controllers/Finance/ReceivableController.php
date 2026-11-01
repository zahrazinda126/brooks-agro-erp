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
use Illuminate\Validation\Rule;
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
        $paymentMethods = DB::table('finance_payment_methods')->where('is_active', true)->orderBy('name')->pluck('name', 'code');
        // One job per page: balances owed, prepayments held, or the prepayment form.
        // The Receivables page itself (the board), then the plain list of balances, prepayments, and the prepayment form.
        $tab = in_array($request->query('tab'), ['balances', 'prepayments', 'record'], true) ? $request->query('tab') : 'board';
        // Record Payment is now a button on Prepayments: old links open that tab with the form open.
        $openRecord = $tab === 'record';
        if ($openRecord) $tab = 'prepayments';
        $board = $tab === 'board' ? $this->board($request) : null;
        return view('finance.receivables.index', compact('tab', 'items', 'outstanding', 'openItems', 'credits', 'customers', 'farmAccounts', 'paymentMethods', 'board', 'openRecord'));
    }

    /**
     * Customer Overview: customers, outstanding invoices, money owed, prepayments held and customers on credit hold
     * (each against 30 days ago), receivables ageing, balances by status, and every customer with what they bought,
     * paid and still owe.
     */
    private function board(Request $request): array
    {
        $today = today();
        $then = $today->copy()->subDays(30);
        $paidBy = fn ($date = null) => DB::table('receivable_payments')->when($date, fn ($q) => $q->whereDate('received_date', '<=', $date))
            ->selectRaw('receivable_id, sum(amount) as paid')->groupBy('receivable_id')->pluck('paid', 'receivable_id');
        // Open invoices as they stood on a date (today, or 30 days ago).
        $openAt = function ($date) use ($paidBy, $today) {
            $paid = $paidBy($date->isSameDay($today) ? null : $date->toDateString());
            return Receivable::whereDate('invoice_date', '<=', $date->toDateString())->get()->map(function (Receivable $r) use ($paid, $date) {
                $r->setAttribute('balance', max(0, (float) $r->amount - (float) ($paid[$r->id] ?? 0)));
                $r->setAttribute('days_overdue', $r->due_date && $r->due_date->lt($date) ? (int) $r->due_date->diffInDays($date) : 0);
                return $r;
            })->filter(fn ($r) => $r->balance > 0)->values();
        };
        $open = $openAt($today);
        $openThen = $openAt($then);
        $limits = SalesCustomer::pluck('credit_limit', 'name');
        // On credit hold: anything more than 30 days overdue, or owing more than the customer's credit limit.
        $onHold = fn ($rows) => $rows->groupBy('customer_name')->filter(fn ($r, $name) => $r->max('days_overdue') > 30
            || ((float) ($limits[$name] ?? 0) > 0 && $r->sum('balance') > (float) $limits[$name]))->keys();
        $change = fn ($now, $before) => $before > 0 ? ($now - $before) / $before * 100 : ($now > 0 ? null : 0.0);
        $credit = (float) CustomerPrepayment::get()->sum(fn ($c) => max(0, (float) $c->amount - (float) $c->amount_used));
        $creditThen = (float) CustomerPrepayment::whereDate('received_date', '<=', $then->toDateString())->sum('amount');

        // Six-month trends for the card sparklines.
        $months = collect(range(5, 0))->map(fn ($i) => $today->copy()->startOfMonth()->subMonths($i));
        $trend = [
            'customers' => $months->map(fn ($m) => SalesCustomer::whereDate('created_at', '<=', $m->copy()->endOfMonth())->count())->all(),
            'invoices' => $months->map(fn ($m) => Receivable::whereBetween('invoice_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->count())->all(),
            'owed' => $months->map(fn ($m) => (float) Receivable::whereBetween('invoice_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount'))->all(),
            'credit' => $months->map(fn ($m) => (float) CustomerPrepayment::whereBetween('received_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount'))->all(),
        ];

        $buckets = ['Current' => [null, 0], '1 – 30 days' => [1, 30], '31 – 60 days' => [31, 60], '61 – 90 days' => [61, 90], 'Over 90 days' => [91, null]];
        $aging = collect($buckets)->map(function ($range, $label) use ($open) {
            $rows = $open->filter(fn ($r) => $range[0] === null ? $r->days_overdue === 0 : ($r->days_overdue >= $range[0] && ($range[1] === null || $r->days_overdue <= $range[1])));
            return ['label' => $label, 'amount' => (float) $rows->sum('balance'), 'count' => $rows->count()];
        })->values();

        // Every customer: what they bought, paid and still owe; most owing first.
        $sales = DB::table('sales')->where('status', '!=', 'void')->where(fn ($q) => $q->whereNull('is_staff_consumption')->orWhere('is_staff_consumption', false))
            ->selectRaw('customer_name, sum(total) as invoiced, sum(amount_paid) as paid, max(sale_date) as last_sale')->groupBy('customer_name')->get()->keyBy('customer_name');
        $owedBy = $open->groupBy('customer_name');
        $held = $onHold($open);
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), ['overdue', 'due', 'clear', 'hold'], true) ? $request->query('status') : null;
        $customers = SalesCustomer::where('is_active', true)->get()->map(function ($c) use ($sales, $owedBy, $held) {
            $s = $sales->get($c->name); $rows = $owedBy->get($c->name, collect());
            $outstanding = (float) $rows->sum('balance');
            return (object) ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'invoiced' => (float) ($s->invoiced ?? 0), 'paid' => max(0, (float) ($s->invoiced ?? 0) - $outstanding),
                'outstanding' => $outstanding, 'last' => $s->last_sale ?? null, 'hold' => $held->contains($c->name),
                'status' => $rows->isEmpty() ? 'clear' : ($rows->max('days_overdue') > 0 ? 'overdue' : 'due')];
        })->filter(fn ($c) => $q === '' || str_contains(mb_strtolower($c->name.' '.$c->phone), mb_strtolower($q)))
            ->filter(fn ($c) => ! $status || ($status === 'hold' ? $c->hold : $c->status === $status))
            ->sortBy([['outstanding', 'desc'], ['invoiced', 'desc']])->values();
        $page = max(1, (int) $request->query('cpage', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator($customers->forPage($page, 5)->values(), $customers->count(), 5, $page, ['path' => $request->url(), 'pageName' => 'cpage']);
        $paged->appends($request->except('cpage'));

        $customersNow = SalesCustomer::where('is_active', true)->count();
        $customersThen = SalesCustomer::whereDate('created_at', '<=', $then->toDateString())->count();
        $owed = (float) $open->sum('balance');
        return [
            'customers' => $customersNow, 'customersChange' => $change($customersNow, $customersThen),
            'openCount' => $open->count(), 'openChange' => $change($open->count(), $openThen->count()),
            'owed' => $owed, 'owedChange' => $change($owed, (float) $openThen->sum('balance')),
            'credit' => $credit, 'creditChange' => $change($credit, $creditThen),
            'hold' => $held->count(), 'holdChange' => $change($held->count(), $onHold($openThen)->count()),
            'trend' => $trend, 'aging' => $aging, 'agingBy' => $request->query('aging') === 'count' ? 'count' : 'amount',
            'list' => $paged, 'q' => $q, 'status' => $status,
        ];
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
        $data=$request->validate(['receivable_id'=>['required','exists:receivables,id'],'received_date'=>['required','date'],'amount'=>['required','numeric','min:1'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required',Rule::exists('finance_payment_methods','code')->where('is_active',true)],'reference'=>['nullable','string','max:80']]);
        if ($data['payment_method'] === 'in_kind') $data['farm_account'] = 'In-kind / Barter Clearing';
        $receivable=Receivable::findOrFail($data['receivable_id']); $balance=(float)$receivable->amount-(float)$receivable->amount_paid;
        if((float)$data['amount']>$balance) return back()->withErrors(['amount'=>'This is more than the customer still owes. Record the excess as a prepayment.'])->withInput();
        DB::transaction(function() use($data,$receivable,$request){ ReceivablePayment::create($data+['recorded_by'=>$request->user()->id]); $receivable->amount_paid+=(float)$data['amount']; $receivable->status=(float)$receivable->amount_paid >= (float)$receivable->amount ? 'paid' : 'open'; $receivable->save(); FinancialTransaction::create(['transaction_date'=>$data['received_date'],'type'=>'money_received','account'=>$data['farm_account'],'counterparty'=>$receivable->customer_name,'amount'=>$data['amount'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference'],'description'=>'Payment reducing balance of '.($receivable->invoice_number ?: 'customer amount due'),'recorded_by'=>$request->user()->id]); });
        return back()->with('status','Customer payment recorded and amount due reduced.');
    }

    public function recordPrepayment(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('receivables', 'create'), 403);
        $data=$request->validate(['customer_name'=>['required','string','max:160'],'received_date'=>['required','date'],'amount'=>['required','numeric','min:1'],'farm_account'=>['required','string','max:120'],'payment_method'=>['required',Rule::exists('finance_payment_methods','code')->where('is_active',true)],'reference'=>['nullable','string','max:80']]);
        if ($data['payment_method'] === 'in_kind') $data['farm_account'] = 'In-kind / Barter Clearing';
        DB::transaction(function() use($data,$request){ $customer=SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower(trim($data['customer_name']))])->first() ?: SalesCustomer::create(['name'=>trim($data['customer_name']),'credit_limit'=>0,'is_active'=>true]);$data['customer_name']=$customer->name;CustomerPrepayment::create($data+['customer_id'=>$customer->id,'amount_used'=>0,'recorded_by'=>$request->user()->id]); FinancialTransaction::create(['transaction_date'=>$data['received_date'],'type'=>'money_received','transaction_category'=>'customer_prepayment','account_group'=>'Current Liabilities','entry_account'=>'Customer Prepayments','account'=>$data['farm_account'],'counterparty'=>$data['customer_name'],'amount'=>$data['amount'],'payment_method'=>$data['payment_method'],'reference'=>$data['reference'],'description'=>'Customer prepayment (credit held for a future sale)','recorded_by'=>$request->user()->id]); });
        return redirect()->route('finance.receivables.index', ['tab' => 'prepayments'])->with('status','Customer prepayment recorded as available credit.');
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
