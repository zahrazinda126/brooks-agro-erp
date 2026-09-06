<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalesCustomer;
use App\Models\SalesReturn;
use App\Models\Receivable;
use App\Models\SaleProduct;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerDirectoryController extends Controller
{
    public function customers(Request $request): View
    {
        $this->authorizeCustomerDirectory($request);
        $search = trim((string) $request->query('search'));
        $request->validate([
            'month'=>['nullable','date_format:Y-m'],
            'product_id'=>['nullable','exists:sale_products,id'],
            'status'=>['nullable',Rule::in(['all','active','archived'])],
            'account'=>['nullable',Rule::in(['all','prepayment','owing','frequent','one-time','no-purchases'])],
            'purchased_from'=>['nullable','date'],
            'purchased_to'=>['nullable','date','after_or_equal:purchased_from'],
            'created_from'=>['nullable','date'],
            'created_to'=>['nullable','date','after_or_equal:created_from'],
        ]);
        $statusFilter = (string) $request->query('status', 'active');
        $accountFilter = (string) $request->query('account', 'all');
        $purchasedFrom = $request->date('purchased_from')?->toDateString();
        $purchasedTo = $request->date('purchased_to')?->toDateString();
        $createdFrom = $request->date('created_from')?->toDateString();
        $createdTo = $request->date('created_to')?->toDateString();
        $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m')))->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $productId = $request->integer('product_id') ?: null;

        $customers = SalesCustomer::query()
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'archived', fn ($query) => $query->where('is_active', false))
            ->when($accountFilter === 'prepayment', fn ($query) => $query->whereHas('prepayments', fn ($credits) => $credits->whereColumn('amount', '>', 'amount_used')))
            ->when($accountFilter === 'owing', fn ($query) => $query->whereHas('sales', fn ($sales) => $sales->where('status', '!=', 'void')->where('balance', '>', 0)))
            ->when($accountFilter === 'frequent', fn ($query) => $query->whereHas('sales', fn ($sales) => $sales->where('status', '!=', 'void'), '>=', 2))
            ->when($accountFilter === 'one-time', fn ($query) => $query->whereHas('sales', fn ($sales) => $sales->where('status', '!=', 'void'), '=', 1))
            ->when($accountFilter === 'no-purchases', fn ($query) => $query->whereDoesntHave('sales', fn ($sales) => $sales->where('status', '!=', 'void')))
            ->when($purchasedFrom || $purchasedTo, fn ($query) => $query->whereHas('sales', function ($sales) use ($purchasedFrom, $purchasedTo) {
                $sales->where('status', '!=', 'void')
                    ->when($purchasedFrom, fn ($rows) => $rows->whereDate('sale_date', '>=', $purchasedFrom))
                    ->when($purchasedTo, fn ($rows) => $rows->whereDate('sale_date', '<=', $purchasedTo));
            }))
            ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
            ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            }))
            ->withCount(['sales as purchase_count' => fn ($query) => $query->where('status', '!=', 'void')])
            ->withSum(['sales as total_purchased' => fn ($query) => $query->where('status', '!=', 'void')], 'total')
            ->withSum(['sales as outstanding_balance' => fn ($query) => $query->where('status', '!=', 'void')], 'balance')
            ->withSum('prepayments as prepayment_received', 'amount')
            ->withSum('prepayments as prepayment_used', 'amount_used')
            ->withMax(['sales as last_purchase_on' => fn ($query) => $query->where('status', '!=', 'void')], 'sale_date')
            ->orderByDesc('total_purchased')->orderBy('name')->paginate(30)->withQueryString();

        $sales = Sale::where('status', '!=', 'void');

        $productRows = DB::table('sale_lines as line')
            ->join('sales as sale', 'sale.id', '=', 'line.sale_id')
            ->leftJoin('sales_customers as customer', 'customer.id', '=', 'sale.customer_id')
            ->leftJoin('sale_products as product', 'product.id', '=', 'line.product_id')
            ->leftJoin('egg_grades as grade', 'grade.id', '=', 'product.egg_grade_id')
            ->where('sale.status', '!=', 'void')
            ->whereBetween('sale.sale_date', [$month->toDateString(), $monthEnd->toDateString()])
            ->when($productId, fn ($query) => $query->where('line.product_id', $productId))
            ->selectRaw("coalesce(customer.id, 0) as customer_id, coalesce(customer.name, sale.customer_name) as customer_name, max(customer.phone) as phone, coalesce(product.name, line.description) as product_name, case when product.egg_grade_id is not null then 'trays' else line.unit end as unit, sum(case when product.egg_grade_id is not null and line.unit = 'egg' then line.quantity / greatest(coalesce(grade.eggs_per_tray, 30), 1) else line.quantity end) as quantity, sum(line.line_total) as amount, case when sum(case when product.egg_grade_id is not null and line.unit = 'egg' then line.quantity / greatest(coalesce(grade.eggs_per_tray, 30), 1) else line.quantity end) > 0 then sum(line.line_total) / sum(case when product.egg_grade_id is not null and line.unit = 'egg' then line.quantity / greatest(coalesce(grade.eggs_per_tray, 30), 1) else line.quantity end) else 0 end as average_price, count(distinct sale.id) as purchase_days, max(sale.sale_date) as last_purchase")
            ->groupByRaw("coalesce(customer.id, 0), coalesce(customer.name, sale.customer_name), coalesce(product.name, line.description), case when product.egg_grade_id is not null then 'trays' else line.unit end")
            ->orderByDesc('amount')->get();
        $productRevenue = (float) $productRows->sum('amount');
        $revenueByProduct = $productRows->groupBy('product_name')->map(fn ($rows) => (float) $rows->sum('amount'));
        $productAnalysis = $productRows->map(function ($row) use ($revenueByProduct) {
            $productTotal = (float) $revenueByProduct->get($row->product_name, 0);
            $share = $productTotal > 0 ? (float) $row->amount / $productTotal * 100 : 0;
            $row->share = $share;
            $row->grade = $share >= 10 ? 'Platinum' : ($share >= 5 ? 'Gold' : ($share >= 2 ? 'Silver' : 'Regular'));
            return $row;
        });

        $cutoff = now()->subDays(30)->toDateString();
        $history = Sale::query()->where('status', '!=', 'void')->whereNotNull('customer_id')
            ->whereDate('sale_date', '>=', now()->subMonths(6))->orderBy('sale_date')->get(['customer_id','sale_date']);
        $followUps = $history->groupBy('customer_id')->map(function ($sales, $customerId) use ($cutoff) {
            if ($sales->count() < 2) return null;
            $dates = $sales->pluck('sale_date')->map(fn ($date) => Carbon::parse($date))->sort()->values();
            $intervals = $dates->zip($dates->slice(1))->filter(fn ($pair) => $pair[1])->map(fn ($pair) => $pair[0]->diffInDays($pair[1]));
            $normalGap = max(7, (int) round($intervals->avg() ?: 14));
            $last = $dates->last();
            $daysAbsent = $last->diffInDays(today());
            if ($last->toDateString() >= $cutoff || $daysAbsent < max(21, $normalGap * 2)) return null;
            $customer = SalesCustomer::find($customerId);
            if (! $customer) return null;
            $weekdays = $dates->groupBy(fn ($date) => $date->format('l'))->sortByDesc(fn ($rows) => $rows->count())->keys()->take(3)->join(', ');
            return (object) ['customer'=>$customer, 'last_purchase'=>$last, 'days_absent'=>$daysAbsent, 'normal_gap'=>$normalGap, 'usual_days'=>$weekdays, 'purchases'=>$sales->count()];
        })->filter()->sortByDesc('days_absent')->values();

        return view('owner.customers', [
            'customers' => $customers,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'accountFilter' => $accountFilter,
            'purchasedFrom' => $purchasedFrom,
            'purchasedTo' => $purchasedTo,
            'createdFrom' => $createdFrom,
            'createdTo' => $createdTo,
            'totalCustomers' => SalesCustomer::where('is_active', true)->count(),
            'repeatCustomers' => SalesCustomer::whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'), '>=', 2)->count(),
            'oneTimeCustomers' => SalesCustomer::whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'), '=', 1)->count(),
            'customersWithoutSales' => SalesCustomer::whereDoesntHave('sales', fn ($query) => $query->where('status', '!=', 'void'))->count(),
            'topCustomers' => SalesCustomer::query()->withCount(['sales as purchase_count' => fn ($query) => $query->where('status', '!=', 'void')])
                ->withSum(['sales as total_purchased' => fn ($query) => $query->where('status', '!=', 'void')], 'total')
                ->whereHas('sales', fn ($query) => $query->where('status', '!=', 'void'))
                ->orderByDesc('purchase_count')->orderByDesc('total_purchased')->limit(5)->get(),
            'salesValue' => (float) (clone $sales)->sum('total'),
            'outstanding' => (float) (clone $sales)->sum('balance'),
            'products' => SaleProduct::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'selectedProduct' => $productId ? SaleProduct::find($productId) : null,
            'selectedMonth' => $month->format('Y-m'),
            'productAnalysis' => $productAnalysis,
            'productRevenue' => $productRevenue,
            'followUps' => $followUps,
            'canCreateCustomer' => $request->user()->canDo('customers','create'),
        ]);
    }

    public function showCustomer(Request $request, SalesCustomer $customer): View
    {
        $this->authorizeCustomerDirectory($request);
        $customer->load(['sales' => fn ($query) => $query->with(['lines', 'payments'])->where('status', '!=', 'void')->latest('sale_date'), 'prepayments' => fn ($query) => $query->latest('received_date')]);
        $returns = SalesReturn::whereIn('sale_id', $customer->sales->pluck('id'))->latest('returned_on')->get();
        $receivables = Receivable::whereRaw('lower(customer_name) = ?', [mb_strtolower($customer->name)])->latest('invoice_date')->get();
        $activities = collect();
        foreach ($customer->sales as $sale) {
            $activities->push(['date'=>$sale->sale_date,'recorded_at'=>$sale->created_at,'type'=>'Sale','reference'=>$sale->invoice_number,'description'=>$sale->lines->pluck('description')->join(', '),'debit'=>(float)$sale->total,'credit'=>0,'link'=>route('sales.show',$sale)]);
            foreach ($sale->payments as $payment) {
                $activities->push(['date'=>$payment->paid_on,'recorded_at'=>$payment->created_at,'type'=>$payment->payment_method==='prepayment'?'Prepayment used':'Payment received','reference'=>$payment->receipt_number ?: $payment->reference,'description'=>$payment->payment_method==='prepayment'?'Customer credit applied to '.$sale->invoice_number:ucfirst(str_replace('_',' ',$payment->payment_method)).' payment for '.$sale->invoice_number,'debit'=>0,'credit'=>(float)$payment->amount,'link'=>route('sales.show',$sale)]);
            }
        }
        foreach ($customer->prepayments as $credit) {
            $activities->push(['date'=>$credit->received_date,'recorded_at'=>$credit->created_at,'type'=>'Prepayment deposited','reference'=>$credit->reference,'description'=>'Money received in advance via '.str_replace('_',' ',$credit->payment_method),'debit'=>0,'credit'=>(float)$credit->amount,'link'=>null]);
        }
        foreach ($returns as $return) {
            $activities->push(['date'=>$return->returned_on,'recorded_at'=>$return->created_at,'type'=>'Sales return','reference'=>null,'description'=>$return->reason,'debit'=>0,'credit'=>(float)$return->refund_amount,'link'=>route('sales.show',$return->sale_id)]);
        }
        $activities = $activities->sortByDesc(fn ($row) => ($row['date']?->format('Y-m-d') ?? '').'|'.($row['recorded_at']?->format('H:i:s') ?? ''))->values();
        $prepaymentAvailable = (float)$customer->prepayments->sum(fn ($credit)=>(float)$credit->amount-(float)$credit->amount_used);
        $outstanding = (float)$customer->sales->sum('balance');
        $canDeposit = $request->user()->canDo('receivables', 'create');
        $farmAccounts = ['Cash on Hand','Petty Cash (UGX)','Centenary Bank - Sales','Centenary Bank - Operations','Centenary Bank - Garden','In-kind / Barter Clearing'];
        return view('owner.customer-account', compact('customer','activities','receivables','prepaymentAvailable','outstanding','canDeposit','farmAccounts') + [
            'canEditCustomer'=>$request->user()->canDo('customers','edit'),
            'canDeleteCustomer'=>$request->user()->canDo('customers','delete'),
        ]);
    }

    public function updateCustomer(Request $request, SalesCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomerDirectory($request);
        abort_unless($request->user()->canDo('customers','edit'),403);
        $data=$request->validate(['name'=>['required','string','max:160'],'phone'=>['nullable','string','max:40'],'email'=>['nullable','email','max:160'],'address'=>['nullable','string','max:1000'],'tax_number'=>['nullable','string','max:80'],'credit_limit'=>['nullable','numeric','min:0']]);
        $before=$customer->only(array_keys($data)); $customer->update($data+['credit_limit'=>$data['credit_limit']??0]);
        AuditLog::record('customer_updated',$customer,'Customer details updated.',$before,$data,'customers');
        return back()->with('status','Customer details updated.');
    }

    public function destroyCustomer(Request $request, SalesCustomer $customer): RedirectResponse
    {
        $this->authorizeCustomerDirectory($request);
        abort_unless($request->user()->canDo('customers','delete'),403);
        if($customer->sales()->exists()||$customer->prepayments()->exists()){
            $customer->update(['is_active'=>false]);
            AuditLog::record('customer_archived',$customer,'Customer archived; transaction history retained.',null,['is_active'=>false],'customers');
            return redirect()->route('owner.customers')->with('status','Customer archived. Their financial history was retained.');
        }
        AuditLog::record('customer_deleted',$customer,'Unused customer record deleted.',null,$customer->toArray(),'customers');
        $customer->delete();
        return redirect()->route('owner.customers')->with('status','Unused customer record deleted.');
    }

    private function authorizeCustomerDirectory(Request $request): void
    {
        $codes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        abort_unless($codes->contains(fn ($code) => in_array($code, ['ceo', 'director', 'finance-manager', 'sales-manager'], true)), 403);
    }
}
