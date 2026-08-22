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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesController extends Controller
{
    private const CATEGORIES = ['eggs', 'poultry', 'goats', 'rabbits', 'dogs', 'milk', 'manure', 'garden', 'other'];

    private const METHODS = ['cash', 'bank', 'mobile_money', 'credit'];

    public function index(Request $request): View
    {
        $screen = $request->query('screen', 'sale');
        abort_unless(in_array($screen, ['sale', 'setup', 'reports'], true), 404);
        abort_unless(match ($screen) {
            'setup' => $this->canManagePrices($request),
            'reports' => $this->canViewSalesReports($request),
            default => $request->user()->canAccess('sales'),
        }, 403);
        $products = SaleProduct::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $products->each(fn ($product) => $product->display_stock = $this->availableStock($product));
        $customers = SalesCustomer::where('is_active', true)->orderBy('name')->get();
        $sales = Sale::with(['lines', 'seller'])->latest('sale_date')->latest('id')->paginate(25)->withQueryString();
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $reportSales = Sale::with(['lines', 'seller'])->whereBetween('sale_date', [$from, $to])->whereNotIn('status', ['void'])->get();
        $reportTotal = (float) $reportSales->sum('total');
        $customerReport = $reportSales->groupBy('customer_name')->map(fn ($rows, $name) => ['name' => $name, 'sales' => $rows->count(), 'amount' => (float) $rows->sum('total')])->sortByDesc('amount')->values()->map(fn ($row, $index) => $row + ['rank' => $index + 1, 'share' => $reportTotal > 0 ? $row['amount'] / $reportTotal * 100 : 0]);
        $productReport = $reportSales->flatMap->lines->groupBy('description')->map(fn ($rows, $name) => ['name' => $name, 'quantity' => (float) $rows->sum('quantity'), 'amount' => (float) $rows->sum('line_total')])->sortByDesc('amount')->values();
        $monthly = collect(range(5, 0))->map(function ($offset) {
            $month = now()->startOfMonth()->subMonths($offset);

            return ['label' => $month->format('M Y'), 'amount' => (float) Sale::whereBetween('sale_date', [$month, $month->copy()->endOfMonth()])->whereNotIn('status', ['void'])->sum('total')];
        });
        $priceHistory = $screen === 'setup'
            ? AuditLog::with('user')->where('event', 'sale_price_changed')->where('auditable_type', SaleProduct::class)->latest('created_at')->limit(20)->get()
            : collect();

        return view('sales.index', compact('products', 'customers', 'sales', 'screen', 'from', 'to', 'customerReport', 'productReport', 'monthly', 'reportTotal', 'reportSales', 'priceHistory') + [
            'todayTotal' => (float) Sale::whereDate('sale_date', today())->whereNotIn('status', ['void'])->sum('total'),
            'monthTotal' => (float) Sale::whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])->whereNotIn('status', ['void'])->sum('total'),
            'outstanding' => (float) Sale::where('balance', '>', 0)->sum('balance'),
            'returnsTotal' => (float) SalesReturn::whereBetween('returned_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('refund_amount'),
            'accounts' => ['Cash on Hand', 'Centenary Bank - Sales', 'MTN Mobile Money', 'Airtel Mobile Money'],
            'categories' => self::CATEGORIES,
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('inventory', 'create'), 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:50', 'unique:sale_products,code'], 'name' => ['required', 'string', 'max:160'], 'category_choice' => ['required', Rule::in([...self::CATEGORIES, 'custom'])], 'custom_category' => ['nullable', 'required_if:category_choice,custom', 'string', 'max:40'], 'unit' => ['required', 'string', 'max:40'], 'unit_price' => ['required', 'numeric', 'min:0'], 'stock_quantity' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $data['category'] = $data['category_choice'] === 'custom' ? strtolower(trim($data['custom_category'])) : $data['category_choice'];
        unset($data['category_choice'],$data['custom_category']);
        SaleProduct::create($data + ['is_active' => true]);

        return back()->with('status', 'Sale product and price added.');
    }

    public function updatePrice(Request $request, SaleProduct $product): RedirectResponse
    {
        abort_unless($this->canManagePrices($request), 403);
        $data = $request->validate(['unit_price' => ['required', 'numeric', 'min:0'], 'effective_from' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000'], 'expected_updated_at' => ['required', 'date'], 'confirm_price_change' => ['nullable', 'boolean']]);
        if ($product->updated_at->format('Y-m-d H:i:s') !== \Carbon\Carbon::parse($data['expected_updated_at'])->format('Y-m-d H:i:s')) {
            return back()->withErrors(['unit_price' => $product->name.' was changed by another user while this page was open. The current price is UGX '.number_format((float) $product->unit_price).'. Review it and try again.']);
        }
        $old = $product->unit_price;
        if ((float) $old !== (float) $data['unit_price'] && (float) $old > 0 && ! $request->boolean('confirm_price_change')) {
            return back()->withErrors(['unit_price' => 'Confirm that you intend to replace the existing '.$product->name.' price of UGX '.number_format((float) $old).'.']);
        }
        $product->update(['unit_price' => $data['unit_price'], 'notes' => $data['notes'] ?? $product->notes]);
        if ($product->egg_grade_id) {
            EggPrice::change($product->egg_grade_id, (float) $data['unit_price'], $data['effective_from'], $request->user()->id, $data['notes'] ?? null);
        }
        AuditLog::record('sale_price_changed', $product, $product->name.' price changed by '.$request->user()->displayName().'.', ['unit_price' => $old], ['unit_price' => $data['unit_price'], 'effective_from' => $data['effective_from']], 'sales');

        return back()->with('status', 'Price updated with an effective-date history.');
    }

    private function canManagePrices(Request $request): bool
    {
        $user = $request->user();
        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();

        return $roleCodes->intersect(['ceo', 'finance-manager'])->isNotEmpty();
    }

    private function canViewSalesReports(Request $request): bool
    {
        $user = $request->user();
        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();

        return $user->canAccess('sales')
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
        $data = $request->validate(['sale_date' => ['required', 'date', 'before_or_equal:today'], 'customer_id' => ['nullable', 'exists:sales_customers,id'], 'walk_in_customer' => ['nullable', 'string', 'max:160'], 'payment_type' => ['required', 'in:cash,credit,mixed'], 'payment_method' => ['nullable', Rule::in(self::METHODS)], 'farm_account' => ['nullable', 'string', 'max:120'], 'amount_paid' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['nullable', 'required_without:lines.*.description', 'exists:sale_products,id'], 'lines.*.category' => ['nullable', 'required_without:lines.*.product_id', Rule::in(self::CATEGORIES)], 'lines.*.description' => ['nullable', 'required_without:lines.*.product_id', 'string', 'max:180'], 'lines.*.unit' => ['nullable', 'required_without:lines.*.product_id', Rule::in(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'])], 'lines.*.quantity' => ['required', 'numeric', 'min:0.01'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0']]);
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
                    $product = ! empty($line['product_id']) ? SaleProduct::lockForUpdate()->findOrFail($line['product_id']) : null;
                    $qty = (float) $line['quantity'];
                    // A configured selling price is authoritative. Manual price
                    // entry is accepted only for an unlisted product or a
                    // catalogue product whose price has not yet been set.
                    $price = $product && (float) $product->unit_price > 0
                        ? (float) $product->unit_price
                        : (float) $line['unit_price'];
                    if ($product) {
                        $available = $this->availableStock($product);
                        if ($available !== null && $qty > $available) {
                            throw new \RuntimeException("Only {$available} {$product->unit} of {$product->name} is available.");
                        }
                    }$total = round($qty * $price, 2);
                    $subtotal += $total;
                    $lines[] = [$product, $line['category'] ?? null, $line['description'] ?? null, $line['unit'] ?? null, $qty, $price, $total];
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
                $sale = Sale::create(['invoice_number' => $this->nextInvoice(), 'sale_date' => $data['sale_date'], 'customer_id' => $customer?->id, 'customer_name' => $customerName, 'payment_type' => $data['payment_type'], 'payment_method' => $data['payment_method'] ?? null, 'farm_account' => $data['farm_account'] ?? null, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'amount_paid' => $paid, 'balance' => $balance, 'status' => $balance > 0 ? 'partly_paid' : 'paid', 'notes' => $data['notes'] ?? null, 'sold_by' => $request->user()->id]);
                foreach ($lines as [$product,$category,$description,$unit,$qty,$price,$lineTotal]) {
                    SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product?->id, 'category' => $product?->category ?: strtolower(trim((string) $category)), 'description' => $product?->name ?: $description, 'unit' => $product?->unit ?: $unit, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $lineTotal]);
                    if ($product && $product->stock_quantity !== null) {
                        $product->decrement('stock_quantity', $qty);
                    }
                }
                if ($balance > 0) {
                    Receivable::create(['customer_name' => $customerName, 'invoice_number' => $sale->invoice_number, 'invoice_date' => $sale->sale_date, 'due_date' => now()->addDays(30)->toDateString(), 'description' => 'Farm sale '.$sale->invoice_number, 'amount' => $total, 'amount_paid' => $paid, 'status' => 'open', 'recorded_by' => $request->user()->id]);
                }
                if ($paid > 0) {
                    $this->recordMoney($sale, $paid, $data['sale_date'], $data['payment_method'] ?? 'cash', $data['farm_account'] ?? 'Cash on Hand', null, $request->user()->id);
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

        return view('sales.show', compact('sale'));
    }

    public function receipt(Request $request, Sale $sale, SalesPayment $payment): View
    {
        abort_unless($request->user()->canAccess('sales') || $request->user()->canAccess('finance'), 403);
        abort_unless($payment->sale_id === $sale->id, 404);
        $sale->load(['lines', 'customer']);
        $payment->load('recorder');

        return view('sales.receipt', compact('sale', 'payment'));
    }

    public function payment(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($request->user()->canDo('receipts', 'create'), 403);
        $data = $request->validate(['paid_on' => ['required', 'date', 'before_or_equal:today'], 'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$sale->balance], 'payment_method' => ['required', Rule::in(self::METHODS)], 'farm_account' => ['required', 'string', 'max:120'], 'reference' => ['nullable', 'string', 'max:100']]);
        DB::transaction(function () use ($sale, $data, $request) {
            $sale->increment('amount_paid', $data['amount']);
            $sale->decrement('balance', $data['amount']);
            $sale->refresh();
            $sale->update(['status' => (float) $sale->balance <= 0 ? 'paid' : 'partly_paid']);
            $this->recordMoney($sale, (float) $data['amount'], $data['paid_on'], $data['payment_method'], $data['farm_account'], $data['reference'] ?? null, $request->user()->id);
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

    private function recordMoney(Sale $sale, float $amount, $date, string $method, string $account, ?string $reference, int $userId): void
    {
        $payment = SalesPayment::create(['sale_id' => $sale->id, 'paid_on' => $date, 'amount' => $amount, 'payment_method' => $method, 'farm_account' => $account, 'reference' => $reference, 'recorded_by' => $userId]);
        $payment->update(['receipt_number' => sprintf('RCT-%06d', $payment->id)]);
        FinancialTransaction::create(['transaction_date' => $date, 'type' => 'money_received', 'account' => $account, 'counterparty' => $sale->customer_name, 'amount' => $amount, 'payment_method' => $method === 'credit' ? null : $method, 'reference' => $payment->receipt_number, 'description' => 'Sales receipt '.$sale->invoice_number, 'recorded_by' => $userId]);
    }

    private function availableStock(SaleProduct $product): ?float
    {
        if (! $product->egg_grade_id) {
            return $product->stock_quantity === null ? null : (float) $product->stock_quantity;
        }$grade = DB::table('egg_grades')->where('id', $product->egg_grade_id)->first();
        if (! $grade) {
            return null;
        }$eggs = (float) DB::table('egg_collection_lines')->join('egg_collections', 'egg_collections.id', '=', 'egg_collection_lines.egg_collection_id')->where('egg_collections.status', 'approved')->where('egg_collection_lines.egg_grade_id', $product->egg_grade_id)->sum('egg_collection_lines.total_eggs');
        $sold = (float) DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.product_id', $product->id)->where('sales.status', '!=', 'void')->sum('sale_lines.quantity');
        $returned = (float) DB::table('sales_returns')->join('sale_lines', 'sale_lines.id', '=', 'sales_returns.sale_line_id')->where('sale_lines.product_id', $product->id)->sum('sales_returns.quantity');

        return max(0, round($eggs / max(1, $grade->eggs_per_tray) - $sold + $returned, 2));
    }

    private function nextInvoice(): string
    {
        $year = now()->format('y');
        $last = Sale::where('invoice_number', 'like', "SAL-%/{$year}")->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')->value('invoice_number');
        $n = $last ? (int) substr($last, 4, strpos($last, '/') - 4) : 0;

        return sprintf('SAL-%04d/%s', $n + 1, $year);
    }
}
