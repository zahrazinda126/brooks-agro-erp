<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EggPrice;
use App\Models\FinancialTransaction;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleProduct;
use App\Models\SalesCustomer;
use App\Models\SalesPayment;
use App\Models\SalesReturn;
use App\Models\CustomerPrepayment;
use App\Models\SalePriceChangeRequest;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesController extends Controller
{
    private const CATEGORIES = ['eggs', 'poultry', 'goats', 'rabbits', 'dogs', 'milk', 'manure', 'garden', 'other'];

    private const METHODS = ['cash', 'bank', 'agent', 'mobile_money', 'credit', 'in_kind', 'prepayment'];

    public function index(Request $request): View
    {
        $screen = $request->query('screen', 'sale');
        abort_unless(in_array($screen, ['sale', 'setup', 'reports'], true), 404);
        abort_unless(match ($screen) {
            'setup' => $this->canManagePrices($request),
            'reports' => $this->canViewSalesReports($request),
            default => $request->user()->canAccess('sales'),
        }, 403);
        $products = SaleProduct::with('eggGrade')->when($screen !== 'setup', fn($query) => $query->where('is_active', true))->orderBy('category')->orderBy('name')->get();
        $products->each(fn ($product) => $product->display_stock = $this->availableStock($product));
        $eggStock = $products->whereNotNull('egg_grade_id')->unique('egg_grade_id')->map(function (SaleProduct $product) {
            $eggs = (int) floor($this->availableEggCount($product));
            $perTray = max(1, (int) ($product->eggGrade?->eggs_per_tray ?? 30));
            return ['name' => $product->name, 'eggs' => $eggs, 'trays' => intdiv($eggs, $perTray), 'loose' => $eggs % $perTray, 'eggs_per_tray' => $perTray];
        })->values();
        $customers = SalesCustomer::where('is_active', true)->orderBy('name')->get();
        $prepayments = CustomerPrepayment::with('customer')->whereRaw('amount_used < amount')->orderBy('customer_name')->get();
        $sales = Sale::with(['lines', 'seller'])->latest('sale_date')->latest('id')->paginate(25)->withQueryString();
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $reportSales = Sale::with(['lines', 'seller'])->whereBetween('sale_date', [$from, $to])->whereNotIn('status', ['void'])->get();
        $reportTotal = (float) $reportSales->sum('total');
        $customerReport = $reportSales->groupBy('customer_name')->map(fn ($rows, $name) => ['name' => $name, 'sales' => $rows->count(), 'amount' => (float) $rows->sum('total')])->sortByDesc('amount')->values()->map(fn ($row, $index) => $row + ['rank' => $index + 1, 'share' => $reportTotal > 0 ? $row['amount'] / $reportTotal * 100 : 0]);
        $productReport = $reportSales->flatMap->lines->groupBy(fn ($line) => $line->description.'|'.$line->unit)->map(fn ($rows) => ['name' => $rows->first()->description, 'unit' => $rows->first()->unit, 'quantity' => (float) $rows->sum('quantity'), 'amount' => (float) $rows->sum('line_total')])->sortByDesc('amount')->values();
        $monthly = collect(range(5, 0))->map(function ($offset) {
            $month = now()->startOfMonth()->subMonths($offset);

            return ['label' => $month->format('M Y'), 'amount' => (float) Sale::whereBetween('sale_date', [$month, $month->copy()->endOfMonth()])->whereNotIn('status', ['void'])->sum('total')];
        });
        $priceHistory = $screen === 'setup'
            ? AuditLog::with('user')->whereIn('event', ['sale_price_approved','sale_price_changed'])->where('auditable_type', SaleProduct::class)->latest('created_at')->limit(20)->get()
            : collect();

        return view('sales.index', compact('products', 'eggStock', 'customers', 'prepayments', 'sales', 'screen', 'from', 'to', 'customerReport', 'productReport', 'monthly', 'reportTotal', 'reportSales', 'priceHistory') + [
            'todayTotal' => (float) Sale::whereDate('sale_date', today())->whereNotIn('status', ['void'])->sum('total'),
            'monthTotal' => (float) Sale::whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])->whereNotIn('status', ['void'])->sum('total'),
            'outstanding' => (float) Sale::where('balance', '>', 0)->sum('balance'),
            'returnsTotal' => (float) SalesReturn::whereBetween('returned_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('refund_amount'),
            'accounts' => ['Cash on Hand', 'Centenary Bank - Sales', 'MTN Mobile Money', 'Airtel Mobile Money', 'In-kind / Barter Clearing'],
            'categories' => self::CATEGORIES,
        ]);
    }

    public function storeProduct(Request $request, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->canManagePrices($request), 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'unique:sale_products,code'], 'name' => ['required', 'string', 'max:160'], 'category_choice' => ['required', Rule::in([...self::CATEGORIES, 'custom'])], 'custom_category' => ['nullable', 'required_if:category_choice,custom', 'string', 'max:40'], 'unit' => ['required', 'string', 'max:40'], 'unit_price' => ['required', 'numeric', 'min:0'], 'loose_unit_price' => ['nullable', 'numeric', 'min:0'], 'stock_quantity' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $data['category'] = $data['category_choice'] === 'custom' ? strtolower(trim($data['custom_category'])) : $data['category_choice'];
        unset($data['category_choice'],$data['custom_category']);
        $proposedPrice=$data['unit_price']; $proposedLoose=$data['loose_unit_price']??null;
        $productData=$data; $productData['unit_price']=0; $productData['loose_unit_price']=null; $productData['is_active']=false;
        $product=SaleProduct::create($productData);
        SalePriceChangeRequest::create(['sale_product_id'=>$product->id,'previous_unit_price'=>0,'proposed_unit_price'=>$proposedPrice,'previous_loose_unit_price'=>null,'proposed_loose_unit_price'=>$proposedLoose,'effective_from'=>now()->toDateString(),'request_note'=>'Initial selling price for a new catalogue product.','status'=>'pending','requested_by'=>$request->user()->id]);
        $communications->toRole('director','New product price approval: '.$product->name,'Approve the initial price of UGX '.number_format((float)$proposedPrice).' before this product becomes available for sale.',$request->user()->id,route('sales.price-approvals',[],false));
        return back()->with('status', 'Product saved and its initial price sent to the Director. It will appear for sale after approval.');
    }

    public function updatePrice(Request $request, SaleProduct $product, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->canManagePrices($request), 403);
        $data = $request->validate(['unit_price' => ['required', 'numeric', 'min:0'], 'loose_unit_price' => ['nullable', 'numeric', 'min:0'], 'effective_from' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000'], 'expected_updated_at' => ['required', 'date'], 'confirm_price_change' => ['nullable', 'boolean']]);
        if ($product->updated_at->format('Y-m-d H:i:s') !== \Carbon\Carbon::parse($data['expected_updated_at'])->format('Y-m-d H:i:s')) {
            return back()->withErrors(['unit_price' => $product->name.' was changed by another user while this page was open. The current price is UGX '.number_format((float) $product->unit_price).'. Review it and try again.']);
        }
        $old = $product->unit_price;
        if ((float) $old !== (float) $data['unit_price'] && (float) $old > 0 && ! $request->boolean('confirm_price_change')) {
            return back()->withErrors(['unit_price' => 'Confirm that you intend to replace the existing '.$product->name.' price of UGX '.number_format((float) $old).'.']);
        }
        if (SalePriceChangeRequest::where('sale_product_id', $product->id)->where('status', 'pending')->exists()) return back()->withErrors(['unit_price' => 'This product already has a price awaiting the Director’s decision.']);
        $priceRequest = SalePriceChangeRequest::create(['sale_product_id'=>$product->id,'previous_unit_price'=>$product->unit_price,'proposed_unit_price'=>$data['unit_price'],'previous_loose_unit_price'=>$product->loose_unit_price,'proposed_loose_unit_price'=>$data['loose_unit_price']??$product->loose_unit_price,'effective_from'=>$data['effective_from'],'request_note'=>$data['notes']??null,'status'=>'pending','requested_by'=>$request->user()->id]);
        AuditLog::record('sale_price_requested', $product, $product->name.' price approval requested by '.$request->user()->displayName().'.', ['unit_price'=>$old], ['unit_price'=>$data['unit_price']], 'sales');
        $communications->toRole('director', 'Price approval required: '.$product->name, 'Proposed price: UGX '.number_format((float)$data['unit_price']).'. Review and approve before it becomes active.', $request->user()->id, route('sales.price-approvals', [], false));
        return back()->with('status', 'Proposed price sent to the Director. The current price remains active until approval.');
    }

    public function priceApprovals(Request $request): View
    {
        abort_unless($this->isDirector($request), 403);
        return view('sales.price-approvals', ['pending'=>SalePriceChangeRequest::with(['product','requester'])->where('status','pending')->oldest()->get(),'history'=>SalePriceChangeRequest::with(['product','requester','decisionMaker'])->where('status','!=','pending')->latest('decided_at')->limit(30)->get()]);
    }

    public function decidePrice(Request $request, SalePriceChangeRequest $priceRequest, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->isDirector($request), 403);
        abort_unless($priceRequest->status === 'pending', 422, 'This price request has already been decided.');
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'decision_note'=>['nullable','required_if:decision,reject','string','max:1000']]);
        $product=$priceRequest->product;
        if ($data['decision']==='approve') {
            abort_if((float)$product->unit_price !== (float)$priceRequest->previous_unit_price, 422, 'The product price changed after this request was made. Reject it and request a fresh approval.');
            $product->update(['unit_price'=>$priceRequest->proposed_unit_price,'loose_unit_price'=>$priceRequest->proposed_loose_unit_price,'notes'=>$priceRequest->request_note??$product->notes,'is_active'=>true]);
            if($product->egg_grade_id) EggPrice::change($product->egg_grade_id,(float)$priceRequest->proposed_unit_price,$priceRequest->effective_from->toDateString(),$request->user()->id,$priceRequest->request_note);
        }
        $status=$data['decision']==='approve'?'approved':'rejected';
        $priceRequest->update(['status'=>$status,'decided_by'=>$request->user()->id,'decided_at'=>now(),'decision_note'=>$data['decision_note']??null]);
        AuditLog::record('sale_price_'.$status,$product,$product->name.' price '.$status.' by the Director.',['unit_price'=>$priceRequest->previous_unit_price],['unit_price'=>$priceRequest->proposed_unit_price],'sales');
        $communications->toUser($priceRequest->requested_by,'Price request '.$status.': '.$product->name,'Proposed price UGX '.number_format((float)$priceRequest->proposed_unit_price).' was '.$status.'.'.(!empty($data['decision_note'])?' '.$data['decision_note']:''),'workflow',$request->user()->id,route('sales.index',['screen'=>'setup'],false));
        return back()->with('status','Price request '.$status.'.');
    }

    private function canManagePrices(Request $request): bool
    {
        $user = $request->user();
        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();

        return $roleCodes->intersect(['ceo', 'finance-manager'])->isNotEmpty();
    }

    private function isDirector(Request $request): bool
    {
        return $request->user()->roles->contains('code','director') || $request->user()->role?->code === 'director';
    }

    private function canViewSalesReports(Request $request): bool
    {
        $user = $request->user();
        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();

        return $user->canViewAllReports()
            || $user->canAccess('sales')
            || $roleCodes->intersect(['ceo', 'director', 'finance-manager', 'sales-manager'])->isNotEmpty();
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('customers', 'create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'], 'address' => ['nullable', 'string', 'max:1000'], 'tax_number' => ['nullable', 'string', 'max:80'], 'credit_limit' => ['nullable', 'numeric', 'min:0']]);
        SalesCustomer::create($data + ['credit_limit' => $data['credit_limit'] ?? 0, 'is_active' => true]);

        return back()->with('status', 'Customer added.');
    }

    public function storeSale(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('sales', 'create'), 403);
        $data = $request->validate(['sale_date' => ['required', 'date', 'before_or_equal:today'], 'customer_id' => ['nullable', 'exists:sales_customers,id'], 'walk_in_customer' => ['nullable', 'string', 'max:160'], 'payment_type' => ['required', 'in:cash,credit,mixed'], 'payment_method' => ['nullable', Rule::in(self::METHODS)], 'farm_account' => ['nullable', 'string', 'max:120'], 'amount_paid' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'], 'merchant_code' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:100'], 'payment_receipt' => ['nullable', 'required_if:payment_method,bank', 'required_if:payment_method,agent', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['nullable', 'required_without:lines.*.description', 'exists:sale_products,id'], 'lines.*.category' => ['nullable', 'required_without:lines.*.product_id', Rule::in(self::CATEGORIES)], 'lines.*.description' => ['nullable', 'required_without:lines.*.product_id', 'string', 'max:180'], 'lines.*.unit' => ['nullable', 'required_without:lines.*.product_id', Rule::in(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'])], 'lines.*.quantity' => ['required', 'numeric', 'min:0.01'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $receiptPath = $request->file('payment_receipt')?->store('sales-payment-receipts', 'public');
        $data['payment_type'] = 'cash';
        $data['amount_paid'] = 0;
        $data['discount'] = 0;
        $customerId = $data['customer_id'] ?? null;
        $customer = $customerId ? SalesCustomer::find($customerId) : null;
        $enteredName = trim($data['walk_in_customer'] ?? '');
        if (! $customer && $enteredName !== '' && strcasecmp($enteredName, 'One-time customer') !== 0) {
            $customer = SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower($enteredName)])->first()
                ?: SalesCustomer::create(['name' => $enteredName, 'credit_limit' => 0, 'is_active' => true]);
        }
        $customerName = $customer?->name ?: 'One-time customer';
        try {
            $sale = DB::transaction(function () use ($data, $customer, $customerName, $request) {
                $lines = [];
                $subtotal = 0;
                foreach ($data['lines'] as $line) {
                    $product = ! empty($line['product_id']) ? SaleProduct::with('eggGrade')->lockForUpdate()->findOrFail($line['product_id']) : null;
                    $qty = (float) $line['quantity'];
                    // A configured selling price is authoritative. Manual price
                    // entry is accepted only for an unlisted product or a
                    // catalogue product whose price has not yet been set.
                    $price = $product && (float) $product->unit_price > 0
                        ? (float) $product->unit_price
                        : (float) $line['unit_price'];
                    $saleUnit = $product ? ($line['unit'] ?? $product->unit) : $line['unit'];
                    if ($product?->egg_grade_id) {
                        $looseEggsAllowed = (bool)$product->eggGrade?->is_defect;
                        $allowedUnits = $looseEggsAllowed ? ['tray', 'egg'] : ['tray'];
                        if (! in_array($saleUnit, $allowedUnits, true)) throw new \RuntimeException($product->name.' can only be sold by the tray. Only damaged eggs may be sold individually.');
                        $eggsPerTray = max(1, (int)($product->eggGrade?->eggs_per_tray ?? 30));
                        if ($saleUnit === 'egg') {
                            if (floor($qty) !== $qty) throw new \RuntimeException('Loose eggs must be entered as a whole number of eggs.');
                            $price = (float)($product->loose_unit_price ?: 250);
                        }
                        $requestedEggs = $saleUnit === 'egg' ? $qty : $qty * $eggsPerTray;
                        $availableEggs = $this->availableEggCount($product);
                        if ($requestedEggs > $availableEggs) throw new \RuntimeException('Only '.number_format($availableEggs).' '.$product->name.' are available as individual eggs.');
                    }
                    if ($product) {
                        $available = $this->availableStock($product);
                        if (! $product->egg_grade_id && $available !== null && $qty > $available) {
                            throw new \RuntimeException("Only {$available} {$product->unit} of {$product->name} is available.");
                        }
                    }$total = round($qty * $price, 2);
                    $subtotal += $total;
                    $lines[] = [$product, $line['category'] ?? null, $line['description'] ?? null, $saleUnit, $qty, $price, $total];
                }
                $discount = (float) ($data['discount'] ?? 0);
                $total = max(0, round($subtotal - $discount, 2));
                $paid = (float) ($data['amount_paid'] ?? 0);
                if ($paid > $total) {
                    throw new \RuntimeException('Amount paid cannot exceed the sale total.');
                }if ($data['payment_type'] === 'cash') {
                    $paid = $total;
                }$balance = round($total - $paid, 2);
                if ($balance > 0 && ! $customer) {
                    throw new \RuntimeException('Choose a registered customer for a credit or partly paid sale.');
                }
                if ($customer && $customer->credit_limit > 0) {
                    $existing = (float) Sale::where('customer_id', $customer->id)->sum('balance');
                    if ($existing + $balance > (float) $customer->credit_limit) {
                        throw new \RuntimeException('This sale exceeds the customer credit limit.');
                    }
                }
                if (($data['payment_method'] ?? null) === 'prepayment') {
                    if (! $customer) throw new \RuntimeException('Choose the registered customer whose prepayment is being used.');
                    $this->consumeCustomerPrepayment($customer, $paid);
                }
                $sale = Sale::create(['invoice_number' => $this->nextInvoice(), 'sale_date' => $data['sale_date'], 'customer_id' => $customer?->id, 'customer_name' => $customerName, 'payment_type' => $data['payment_type'], 'payment_method' => $data['payment_method'] ?? null, 'farm_account' => $data['farm_account'] ?? null, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'amount_paid' => $paid, 'balance' => $balance, 'status' => $balance > 0 ? 'partly_paid' : 'paid', 'notes' => $data['notes'] ?? null, 'sold_by' => $request->user()->id]);
                foreach ($lines as [$product,$category,$description,$unit,$qty,$price,$lineTotal]) {
                    SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product?->id, 'category' => $product?->category ?: strtolower(trim((string) $category)), 'description' => $product?->name ?: $description, 'unit' => $unit, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $lineTotal]);
                    if ($product && $product->stock_quantity !== null) {
                        $product->decrement('stock_quantity', $qty);
                    }
                }
                if ($balance > 0) {
                    Receivable::create(['customer_name' => $customerName, 'invoice_number' => $sale->invoice_number, 'invoice_date' => $sale->sale_date, 'due_date' => now()->addDays(30)->toDateString(), 'description' => 'Farm sale '.$sale->invoice_number, 'amount' => $total, 'amount_paid' => $paid, 'status' => 'open', 'recorded_by' => $request->user()->id]);
                }
                if ($paid > 0) {
                    $this->recordMoney($sale, $paid, $data['sale_date'], $data['payment_method'] ?? 'cash', $data['farm_account'] ?? 'Cash on Hand', null, $request->user()->id, $data['merchant_code'] ?? null, $receiptPath);
                }

                return $sale;
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['sale' => $e->getMessage()])->withInput();
        }

        $payment = $sale->payments()->latest('id')->firstOrFail();

        return redirect()->route('sales.receipts.print', [$sale, $payment])->with('status', 'Sale '.$sale->invoice_number.' recorded. Receipt number generated automatically.');
    }

    public function show(Request $request, Sale $sale): View
    {
        abort_unless($request->user()->canAccess('sales') || $request->user()->canAccess('finance'), 403);
        $sale->load(['lines.product', 'payments', 'customer', 'seller']);

        $prepayments=CustomerPrepayment::whereRaw('amount_used < amount')->where('customer_id',$sale->customer_id)->orderBy('received_date')->get();
        return view('sales.show', compact('sale','prepayments'));
    }

    public function receipt(Request $request, Sale $sale, SalesPayment $payment): View
    {
        abort_unless($request->user()->canAccess('sales') || $request->user()->canAccess('finance'), 403);
        abort_unless($payment->sale_id === $sale->id, 404);
        $sale->load(['lines', 'customer']);
        $payment->load('recorder');
        $creditUsed = $payment->payment_method === 'prepayment' ? (float)$payment->amount : 0.0;
        $remainingAccountCredit = $sale->customer
            ? (float)CustomerPrepayment::where('customer_id', $sale->customer_id)->sum(DB::raw('amount-amount_used'))
            : 0.0;

        return view('sales.receipt', compact('sale', 'payment', 'creditUsed', 'remainingAccountCredit'));
    }

    public function payment(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->canDo('receipts', 'create'), 403);
        $data = $request->validate(['paid_on' => ['required', 'date', 'before_or_equal:today'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$sale->balance], 'payment_method' => ['required', Rule::in(self::METHODS)], 'farm_account' => ['nullable', 'required_unless:payment_method,prepayment','string','max:120'], 'reference' => ['nullable', 'string', 'max:100'], 'merchant_code' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:100'], 'payment_receipt' => ['nullable', 'required_if:payment_method,bank', 'required_if:payment_method,agent', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $receiptPath = $request->file('payment_receipt')?->store('sales-payment-receipts', 'public');
        DB::transaction(function () use ($sale, $data, $request) {
            if ($data['payment_method'] === 'prepayment') {
                $customer=$sale->customer ?: SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower($sale->customer_name)])->first();
                abort_unless($customer,422,'This sale is not linked to a registered customer account.');
                if (! $sale->customer_id) $sale->update(['customer_id'=>$customer->id]);
                $this->consumeCustomerPrepayment($customer,(float)$data['amount']);
            }
            $sale->increment('amount_paid', $data['amount']);
            $sale->decrement('balance', $data['amount']);
            $sale->refresh();
            $sale->update(['status' => (float) $sale->balance <= 0 ? 'paid' : 'partly_paid']);
            $this->recordMoney($sale, (float) $data['amount'], $data['paid_on'], $data['payment_method'], $data['farm_account'] ?? 'Customer Prepayment Credit', $data['reference'] ?? null, $request->user()->id, $data['merchant_code'] ?? null, $receiptPath);
            $due = Receivable::where('invoice_number', $sale->invoice_number)->first();
            if ($due) {
                $due->increment('amount_paid', $data['amount']);
                $due->refresh();
                $due->update(['status' => (float) $due->amount_paid >= (float) $due->amount ? 'paid' : 'open']);
            }
        });
        $payment = $sale->payments()->latest('id')->first();

        return redirect()->route('sales.receipts.print', [$sale, $payment])->with('status', 'Customer payment recorded. Receipt ready to print.');
    }

    public function returnLine(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->canDo('returns', 'create'), 403);
        $data = $request->validate(['sale_line_id' => ['required', 'exists:sale_lines,id'], 'returned_on' => ['required', 'date', 'before_or_equal:today'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'refund_amount' => ['nullable', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $line = $sale->lines()->findOrFail($data['sale_line_id']);
        $returned = (float) SalesReturn::where('sale_line_id', $line->id)->sum('quantity');
        abort_if($returned + (float) $data['quantity'] > (float) $line->quantity, 422, 'Return quantity exceeds the quantity sold.');
        DB::transaction(function () use ($sale, $line, $data, $request) {
            SalesReturn::create($data + ['sale_id' => $sale->id, 'recorded_by' => $request->user()->id]);
            if ($line->product && $line->product->stock_quantity !== null) {
                $line->product->increment('stock_quantity', $data['quantity']);
            }if ((float) ($data['refund_amount'] ?? 0) > 0) {
                FinancialTransaction::create(['transaction_date' => $data['returned_on'], 'type' => 'money_paid', 'account' => $sale->farm_account ?: 'Cash on Hand', 'counterparty' => $sale->customer_name, 'amount' => $data['refund_amount'], 'payment_method' => $sale->payment_method ?: 'cash', 'reference' => $sale->invoice_number, 'description' => 'Sales return refund for '.$line->description, 'recorded_by' => $request->user()->id]);
            }$sale->update(['status' => 'part_returned']);
        });

        return back()->with('status', 'Sales return recorded and tracked stock restored.');
    }

    private function recordMoney(Sale $sale, float $amount, $date, string $method, string $account, ?string $reference, int $userId, ?string $merchantCode = null, ?string $receiptPath = null): void
    {
        if ($method === 'in_kind') {
            $account = 'In-kind / Barter Clearing';
        }
        if ($method === 'prepayment') {
            $account = 'Customer Prepayment Credit';
        }
        $payment = SalesPayment::create(['sale_id' => $sale->id, 'paid_on' => $date, 'amount' => $amount, 'payment_method' => $method, 'farm_account' => $account, 'reference' => $reference, 'merchant_code' => $merchantCode, 'receipt_path' => $receiptPath, 'recorded_by' => $userId]);
        $payment->update(['receipt_number' => sprintf('%s-RCT-%06d', $this->receiptProductPrefix($sale), $payment->id)]);
        if ($method === 'prepayment') {
            FinancialTransaction::create(['transaction_date' => $date, 'type' => 'revenue_recognized', 'transaction_category' => 'revenue', 'account_group' => 'Sales', 'entry_account' => 'Sales Revenue', 'account' => 'Customer Prepayments (Liability)', 'counterparty' => $sale->customer_name, 'amount' => $amount, 'payment_method' => null, 'reference' => $payment->receipt_number, 'description' => 'Sales revenue recognized from customer prepayment for '.$sale->invoice_number, 'recorded_by' => $userId]);
            return;
        }
        FinancialTransaction::create(['transaction_date' => $date, 'type' => 'money_received', 'account' => $account, 'counterparty' => $sale->customer_name, 'amount' => $amount, 'payment_method' => $method === 'credit' ? null : $method, 'reference' => $payment->receipt_number, 'description' => 'Sales receipt '.$sale->invoice_number, 'recorded_by' => $userId]);
    }

    private function receiptProductPrefix(Sale $sale): string
    {
        $categories = $sale->lines()
            ->with('product:id,category')
            ->get()
            ->map(fn ($line) => strtolower((string) ($line->product?->category ?: $line->category ?: 'other')))
            ->filter()
            ->unique()
            ->values();

        if ($categories->count() !== 1) {
            return 'MIX';
        }

        return match ($categories->first()) {
            'eggs' => 'EGG',
            'poultry' => 'PLT',
            'goats' => 'GOAT',
            'rabbits' => 'RBT',
            'dogs' => 'DOG',
            'milk' => 'MLK',
            'manure' => 'MAN',
            'garden' => 'GRD',
            default => 'OTH',
        };
    }

    private function consumeCustomerPrepayment(SalesCustomer $customer, float $amount): void
    {
        $credits = CustomerPrepayment::where('customer_id', $customer->id)->whereRaw('amount_used < amount')
            ->orderBy('received_date')->orderBy('id')->lockForUpdate()->get();
        $available = (float)$credits->sum(fn ($credit) => (float)$credit->amount - (float)$credit->amount_used);
        if ($amount > $available) throw new \RuntimeException($customer->name.' has only UGX '.number_format($available).' available as prepayment.');
        $remaining = $amount;
        foreach ($credits as $credit) {
            if ($remaining <= 0) break;
            $use = min($remaining, (float)$credit->amount - (float)$credit->amount_used);
            $credit->increment('amount_used', $use);
            $remaining -= $use;
        }
    }

    private function availableStock(SaleProduct $product): ?float
    {
        if (! $product->egg_grade_id) {
            return $product->stock_quantity === null ? null : (float) $product->stock_quantity;
        }$grade = DB::table('egg_grades')->where('id', $product->egg_grade_id)->first();
        if (! $grade) {
            return null;
        }

        return round($this->availableEggCount($product) / max(1, $grade->eggs_per_tray), 2);
    }

    private function availableEggCount(SaleProduct $product): float
    {
        $eggsPerTray = max(1, (int)($product->eggGrade?->eggs_per_tray ?? DB::table('egg_grades')->where('id', $product->egg_grade_id)->value('eggs_per_tray') ?? 30));
        $collected = (float)DB::table('egg_collection_lines')->join('egg_collections', 'egg_collections.id', '=', 'egg_collection_lines.egg_collection_id')->where('egg_collections.status', 'approved')->where('egg_collection_lines.egg_grade_id', $product->egg_grade_id)->sum('egg_collection_lines.total_eggs');
        $sold = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.product_id', $product->id)->where('sales.status', '!=', 'void')->get(['sale_lines.unit','sale_lines.quantity'])->sum(fn ($line) => (float)$line->quantity * ($line->unit === 'egg' ? 1 : $eggsPerTray));
        $returned = DB::table('sales_returns')->join('sale_lines', 'sale_lines.id', '=', 'sales_returns.sale_line_id')->where('sale_lines.product_id', $product->id)->get(['sale_lines.unit','sales_returns.quantity'])->sum(fn ($line) => (float)$line->quantity * ($line->unit === 'egg' ? 1 : $eggsPerTray));

        return max(0, $collected - $sold + $returned);
    }

    private function nextInvoice(): string
    {
        $year = now()->format('y');
        $last = Sale::where('invoice_number', 'like', "SAL-%/{$year}")->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')->value('invoice_number');
        $n = $last ? (int) substr($last, 4, strpos($last, '/') - 4) : 0;

        return sprintf('SAL-%04d/%s', $n + 1, $year);
    }
}
