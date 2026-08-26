<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalesCustomer;
use App\Models\SalesReturn;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDirectoryController extends Controller
{
    public function customers(Request $request): View
    {
        $this->authorizeCustomerDirectory($request);
        $search = trim((string) $request->query('search'));

        $customers = SalesCustomer::query()
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

        return view('owner.customers', [
            'customers' => $customers,
            'search' => $search,
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
        return view('owner.customer-account', compact('customer','activities','receivables','prepaymentAvailable','outstanding','canDeposit','farmAccounts'));
    }

    private function authorizeCustomerDirectory(Request $request): void
    {
        $codes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        abort_unless($codes->contains(fn ($code) => in_array($code, ['ceo', 'director', 'finance-manager', 'sales-manager'], true)), 403);
    }
}
