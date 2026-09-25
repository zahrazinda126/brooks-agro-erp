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
use App\Models\DailySalesBalance;
use App\Models\AnimalGroup;
use App\Models\FarmUnitRecord;
use App\Models\PoultryHouse;
use App\Models\PoultryHouseAssignment;
use App\Models\Approval;
use App\Models\User;
use App\Services\CommunicationService;
use App\Services\BrandedReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesController extends Controller
{
    private const CATEGORIES = ['eggs', 'poultry', 'goats', 'rabbits', 'dogs', 'milk', 'manure', 'garden', 'other'];

    private const METHODS = ['cash', 'bank', 'agent', 'mobile_money', 'in_kind', 'prepayment', 'farm_consumption'];

    public function index(Request $request): View
    {
        $screen = $request->query('screen', 'sale');
        abort_unless(in_array($screen, ['sale', 'inventory', 'setup', 'reports', 'analysis', 'balancing', 'receipt_archive'], true), 404);
        $inventoryUnit = $request->query('unit');
        $priceUnit = $screen === 'setup' ? $request->query('price_unit') : null;
        $analysisView = $request->query('analysis_view');
        $revenueView = $analysisView === 'revenue' ? $request->query('revenue_view') : null;
        if ($screen === 'inventory' && $inventoryUnit !== null) {
            abort_unless(in_array($inventoryUnit, ['poultry', 'goats', 'rabbits', 'dogs', 'garden'], true), 404);
        }
        if ($screen === 'analysis' && $analysisView !== null) {
            abort_unless(in_array($analysisView, ['summary', 'monthly', 'product_amount', 'product_quantity', 'revenue'], true), 404);
        }
        if ($revenueView !== null) abort_unless(in_array($revenueView, ['ytd', 'monthly'], true), 404);
        if ($priceUnit !== null) {
            abort_unless(in_array($priceUnit, ['poultry', 'goats', 'rabbits', 'dogs', 'garden', 'other'], true), 404);
        }
        abort_unless(match ($screen) {
            'setup' => $this->canManagePrices($request),
            'balancing', 'receipt_archive' => $this->isFinanceUser($request),
            'reports', 'analysis' => $this->canViewSalesReports($request),
            default => $request->user()->canAccess('sales'),
        }, 403);
        $nonSalePoultryStock = ['BIRD-BROILER','BIRD-COCKEREL','BIRD-CHICK','BIRD-LOCAL','BIRD-PULLET','BIRD-SPENT-LAYER'];
        $nonSaleEggConditions = ['Broken eggs','Dirty eggs','Soft shell eggs'];
        $products = SaleProduct::with('eggGrade')->whereNotIn('code', $nonSalePoultryStock)->whereNotIn('name', $nonSaleEggConditions)->when($screen !== 'setup', fn($query) => $query->where('is_active', true))->orderBy('category')->orderBy('name')->get();
        if ($priceUnit) {
            $products = $products->filter(function (SaleProduct $product) use ($priceUnit) {
                $category = strtolower((string) $product->category);
                $name = strtolower((string) $product->name);
                $unit = match (true) {
                    in_array($category, ['eggs', 'poultry'], true) => 'poultry',
                    $category === 'goats' || $category === 'milk' || str_contains($name, 'goat') => 'goats',
                    $category === 'rabbits' || str_contains($name, 'rabbit') => 'rabbits',
                    $category === 'dogs' || str_contains($name, 'dog') => 'dogs',
                    $category === 'garden' => 'garden',
                    $category === 'manure' => str_contains($name, 'goat') ? 'goats' : (str_contains($name, 'rabbit') ? 'rabbits' : 'poultry'),
                    default => 'other',
                };
                return $unit === $priceUnit;
            })->values();
        }
        $products->each(fn ($product) => $product->display_stock = $this->availableStock($product));
        $eggStock = $products->whereNotNull('egg_grade_id')->unique('egg_grade_id')->map(function (SaleProduct $product) {
            $eggs = (int) floor($this->availableEggCount($product));
            $perTray = max(1, (int) ($product->eggGrade?->eggs_per_tray ?? 30));
            return ['name' => $product->name, 'eggs' => $eggs, 'trays' => intdiv($eggs, $perTray), 'loose' => $eggs % $perTray, 'eggs_per_tray' => $perTray];
        })->values();
        $customers = SalesCustomer::where('is_active', true)->orderBy('name')->get();
        $poultryHouses = PoultryHouse::with('batch')->where('is_active', true)->whereNotNull('animal_group_id')->where('bird_quantity', '>', 0)->orderBy('house_number')->get();
        $prepayments = CustomerPrepayment::with('customer')->whereRaw('amount_used < amount')->orderBy('customer_name')->get();
        $sales = Sale::with(['lines', 'seller'])->latest('sale_date')->latest('id')->paginate(25)->withQueryString();
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $fromMonth = $request->query('from_month', now()->subMonths(11)->format('Y-m'));
        $toMonth = $request->query('to_month', now()->format('Y-m'));
        if ($screen === 'analysis' && $analysisView === 'monthly') {
            abort_unless(preg_match('/^\d{4}-\d{2}$/', $fromMonth) && preg_match('/^\d{4}-\d{2}$/', $toMonth), 422, 'Choose valid From and To months.');
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $fromMonth.'-01')->startOfMonth()->toDateString();
            $to = \Carbon\Carbon::createFromFormat('Y-m-d', $toMonth.'-01')->endOfMonth()->toDateString();
            abort_if($from > $to, 422, 'The From month must not be after the To month.');
        }
        $reportSales = Sale::with(['lines', 'seller', 'customer'])->whereBetween('sale_date', [$from, $to])->whereNotIn('status', ['void'])->where('approval_status','approved')->where('is_staff_consumption', false)->get();
        $reportTotal = (float) $reportSales->sum('total');
        $customerReport = $reportSales->groupBy('customer_name')->map(fn ($rows, $name) => ['name' => $name, 'sales' => $rows->count(), 'amount' => (float) $rows->sum('total')])->sortByDesc('amount')->values()->map(fn ($row, $index) => $row + ['rank' => $index + 1, 'share' => $reportTotal > 0 ? $row['amount'] / $reportTotal * 100 : 0]);
        $productReport = $reportSales->flatMap->lines->groupBy(fn ($line) => $line->description.'|'.$line->unit)->map(fn ($rows) => ['name' => $rows->first()->description, 'unit' => $rows->first()->unit, 'quantity' => (float) $rows->sum('quantity'), 'amount' => (float) $rows->sum('line_total')])->sortByDesc('amount')->values();
        $paymentAnalysis = $reportSales->groupBy(fn ($sale) => $sale->payment_method ?: 'not_specified')->map(fn ($rows, $method) => ['method' => str($method)->replace('_', ' ')->title()->toString(), 'sales' => $rows->count(), 'amount' => (float) $rows->sum('total')])->sortByDesc('amount')->values();
        $unitAnalysis = $reportSales->flatMap->lines->groupBy(fn ($line) => match ($line->category) {
            'eggs', 'poultry' => 'Poultry', 'goats', 'milk' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden', default => 'Other',
        })->map(fn ($rows, $unit) => ['unit' => $unit, 'quantity' => (float) $rows->sum('quantity'), 'amount' => (float) $rows->sum('line_total')])->sortByDesc('amount')->values();
        $analysisMonthStart = $screen === 'analysis' && $analysisView === 'monthly' ? \Carbon\Carbon::parse($from)->startOfMonth() : \Carbon\Carbon::parse($to)->startOfMonth()->subMonths(11);
        $analysisMonthEnd = \Carbon\Carbon::parse($to)->startOfMonth();
        $analysisMonths = collect(\Carbon\CarbonPeriod::create($analysisMonthStart, '1 month', $analysisMonthEnd))->map(fn ($month) => ['key' => $month->format('Y-m'), 'label' => $month->format('M-y'), 'from' => $month->copy()->startOfMonth()->toDateString(), 'to' => $month->copy()->endOfMonth()->toDateString()])->values();
        $analysisMonthlySales = Sale::with('lines')->whereBetween('sale_date', [$analysisMonths->first()['from'], $analysisMonths->last()['to']])->whereNotIn('status', ['void'])->where('approval_status', 'approved')->where('is_staff_consumption', false)->get();
        $analysisCustomerNames = $customers->pluck('name')->merge($reportSales->pluck('customer_name'))->merge($analysisMonthlySales->pluck('customer_name'))->filter()->unique()->sort()->values();
        $customerMonthlyAnalysis = $analysisCustomerNames->map(fn ($name) => [
            'name' => $name,
            'months' => $analysisMonths->mapWithKeys(fn ($month) => [$month['key'] => (float) $analysisMonthlySales->where('customer_name', $name)->filter(fn ($sale) => $sale->sale_date->format('Y-m') === $month['key'])->sum('total')]),
        ]);
        $analysisProductColumns = $products->pluck('name')->merge($reportSales->flatMap->lines->pluck('description'))->filter()->unique()->sort()->values();
        $customerProductAnalysis = $analysisCustomerNames->map(function ($name) use ($reportSales, $analysisProductColumns) {
            $lines = $reportSales->where('customer_name', $name)->flatMap->lines;
            return [
                'name' => $name,
                'amounts' => $analysisProductColumns->mapWithKeys(fn ($product) => [$product => (float) $lines->where('description', $product)->sum('line_total')]),
                'quantities' => $analysisProductColumns->mapWithKeys(fn ($product) => [$product => (float) $lines->where('description', $product)->sum('quantity')]),
            ];
        });
        $revenueFinancialYear = (int) $request->query('financial_year', now()->month >= 7 ? now()->year : now()->subYear()->year);
        abort_unless($revenueFinancialYear >= 2000 && $revenueFinancialYear <= now()->addYear()->year, 422, 'Choose a valid financial year.');
        $revenueYearStart = \Carbon\Carbon::create($revenueFinancialYear, 7, 1)->startOfDay();
        $revenueYearEnd = $revenueYearStart->copy()->addYear()->subDay()->endOfDay();
        $revenueMonths = collect(\Carbon\CarbonPeriod::create($revenueYearStart, '1 month', $revenueYearEnd->copy()->startOfMonth()))
            ->map(fn ($month) => ['key'=>$month->format('Y-m'), 'label'=>$month->format('M-y')])->values();
        $revenueSales = Sale::with('lines')->whereBetween('sale_date', [$revenueYearStart->copy()->subYear()->toDateString(), $revenueYearEnd->toDateString()])
            ->whereNotIn('status', ['void'])->where('approval_status', 'approved')->where('is_staff_consumption', false)->get();
        $revenueLines = $this->normalisedRevenueLines($revenueSales);
        $revenueCategories = collect(['Large eggs','Manure','Off-layers','Starter eggs','Damaged eggs','Medium eggs','Garden sales','Other sales','Goat sales','Rabbit sales','Rabbit meat sales','Goat meat sales']);
        $revenueMonthlyAnalysis = $revenueCategories->map(fn ($category) => ['category'=>$category, 'months'=>$revenueMonths->mapWithKeys(fn ($month) => [$month['key'] => (float) $revenueLines->where('category', $category)->where('month', $month['key'])->sum('amount')])]);
        $revenueYtdEnd = min(now()->endOfDay(), $revenueYearEnd);
        if ($revenueYtdEnd->lt($revenueYearStart)) $revenueYtdEnd = $revenueYearEnd;
        $revenuePriorYtdEnd = $revenueYtdEnd->copy()->subYear();
        $revenueYtdAnalysis = $revenueCategories->map(function ($category) use ($revenueLines, $revenueYearStart, $revenueYtdEnd, $revenuePriorYtdEnd) {
            $current = (float) $revenueLines->where('category', $category)->filter(fn ($row) => $row['date']->betweenIncluded($revenueYearStart, $revenueYtdEnd))->sum('amount');
            $previous = (float) $revenueLines->where('category', $category)->filter(fn ($row) => $row['date']->betweenIncluded($revenueYearStart->copy()->subYear(), $revenuePriorYtdEnd))->sum('amount');
            return ['category'=>$category, 'current'=>$current, 'previous'=>$previous, 'variance'=>$current-$previous, 'percent'=>$previous > 0 ? (($current-$previous)/$previous*100) : null];
        });
        $monthly = collect(range(5, 0))->map(function ($offset) {
            $month = now()->startOfMonth()->subMonths($offset);

            return ['label' => $month->format('M Y'), 'amount' => (float) Sale::whereBetween('sale_date', [$month, $month->copy()->endOfMonth()])->whereNotIn('status', ['void'])->where('approval_status','approved')->where('is_staff_consumption', false)->sum('total')];
        });
        $priceHistory = $screen === 'setup'
            ? AuditLog::with('user')->whereIn('event', ['sale_price_approved','sale_price_changed'])->where('auditable_type', SaleProduct::class)->latest('created_at')->limit(20)->get()
            : collect();
        $farmStock = $screen === 'inventory' ? $this->farmProductsInStock($eggStock) : collect();

        $balanceDate = $request->query('balance_date', now()->toDateString());
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $balanceDate), 422, 'Choose a valid balancing date.');
        $balanceSales = collect();
        $balancePayments = collect();
        $balanceByMethod = collect();
        $balanceOpening = 0.0;
        $balanceCashReceived = 0.0;
        $balanceCashDue = 0.0;
        $dailyBalance = null;
        $receiptArchive = collect();
        $receiptFrom = $request->query('receipt_from', now()->subMonths(11)->startOfMonth()->toDateString());
        $receiptTo = $request->query('receipt_to', now()->toDateString());
        $receiptDestination = $request->query('receipt_destination');
        $receiptSalesPerson = $request->integer('receipt_sales_person');
        $receiptSalesPeople = collect();
        if ($screen === 'balancing') {
            $requestedSalesPerson = (int) $request->query('sales_person_id', $request->user()->id);
            $salesPersonId = $this->isFinanceUser($request) ? $requestedSalesPerson : $request->user()->id;
            $balanceSales = Sale::with(['lines', 'payments', 'customer'])
                ->whereDate('sale_date', $balanceDate)
                ->where('sold_by', $salesPersonId)
                ->whereNotIn('status', ['void'])
                ->orderBy('created_at')
                ->get();
            $balancePayments = SalesPayment::with('sale')
                ->whereDate('paid_on', $balanceDate)
                ->whereHas('sale', fn ($query) => $query->where('sold_by', $salesPersonId)->whereNotIn('status', ['void']))
                ->orderBy('created_at')
                ->get();
            $balanceByMethod = $balancePayments->groupBy('payment_method')->map(fn ($rows) => (float) $rows->sum('amount'));
            $priorCash = (float) SalesPayment::whereDate('paid_on', '<', $balanceDate)
                ->where('payment_method', 'cash')
                ->whereHas('sale', fn ($query) => $query->where('sold_by', $salesPersonId)->whereNotIn('status', ['void']))
                ->sum('amount');
            $priorTransfers = (float) DailySalesBalance::where('sales_person_id', $salesPersonId)
                ->whereDate('business_date', '<', $balanceDate)
                ->sum('amount_transferred');
            $balanceOpening = max(0, $priorCash - $priorTransfers);
            $balanceCashReceived = (float) ($balanceByMethod['cash'] ?? 0);
            $balanceCashDue = $balanceOpening + $balanceCashReceived;
            $dailyBalance = DailySalesBalance::where('sales_person_id', $salesPersonId)
                ->whereDate('business_date', $balanceDate)
                ->first();
        }
        if ($screen === 'receipt_archive') {
            abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $receiptFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $receiptTo), 422, 'Choose valid receipt archive dates.');
            abort_if($receiptFrom > $receiptTo, 422, 'The From date must not be after the To date.');
            abort_unless($receiptDestination === null || in_array($receiptDestination, ['bank', 'reservoir'], true), 422, 'Choose a valid destination.');
            $receiptArchive = DailySalesBalance::with(['salesPerson', 'closer'])
                ->whereNotNull('receipt_path')
                ->whereBetween('business_date', [$receiptFrom, $receiptTo])
                ->when($receiptDestination, fn ($query) => $query->where('destination', $receiptDestination))
                ->when($receiptSalesPerson, fn ($query) => $query->where('sales_person_id', $receiptSalesPerson))
                ->latest('business_date')
                ->latest('closed_at')
                ->get();
            $receiptSalesPeople = User::whereIn('id', DailySalesBalance::whereNotNull('receipt_path')->select('sales_person_id'))
                ->orderBy('name')
                ->get();
        }

        return view('sales.index', compact('products', 'eggStock', 'farmStock', 'inventoryUnit', 'priceUnit', 'analysisView', 'revenueView', 'customers', 'poultryHouses', 'prepayments', 'sales', 'screen', 'from', 'to', 'fromMonth', 'toMonth', 'customerReport', 'productReport', 'paymentAnalysis', 'unitAnalysis', 'analysisMonths', 'customerMonthlyAnalysis', 'analysisProductColumns', 'customerProductAnalysis', 'revenueFinancialYear', 'revenueMonths', 'revenueMonthlyAnalysis', 'revenueYtdAnalysis', 'revenueYearStart', 'revenueYtdEnd', 'monthly', 'reportTotal', 'reportSales', 'priceHistory', 'balanceDate', 'balanceSales', 'balancePayments', 'balanceByMethod', 'balanceOpening', 'balanceCashReceived', 'balanceCashDue', 'dailyBalance', 'receiptArchive', 'receiptFrom', 'receiptTo', 'receiptDestination', 'receiptSalesPerson', 'receiptSalesPeople') + [
            'todayTotal' => (float) Sale::whereDate('sale_date', today())->whereNotIn('status', ['void'])->where('approval_status','approved')->where('is_staff_consumption', false)->sum('total'),
            'monthTotal' => (float) Sale::whereBetween('sale_date', [now()->startOfMonth(), now()->endOfMonth()])->whereNotIn('status', ['void'])->where('approval_status','approved')->where('is_staff_consumption', false)->sum('total'),
            'pendingApprovalCount'=>Sale::where('approval_status','pending')->count(),
            'outstanding' => (float) Sale::where('balance', '>', 0)->sum('balance'),
            'returnsTotal' => (float) SalesReturn::whereBetween('returned_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('refund_amount'),
            'accounts' => ['Cash on Hand', 'Centenary Bank - Sales', 'MTN Mobile Money', 'Airtel Mobile Money', 'In-kind / Barter Clearing'],
            'categories' => self::CATEGORIES,
        ]);
    }

    public function closeDailyBalance(Request $request): RedirectResponse
    {
        abort_unless($this->isFinanceUser($request), 403, 'Only Finance can bank the money and close a sales day.');
        $data = $request->validate([
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'sales_person_id' => ['required', 'exists:users,id'],
            'amount_transferred' => ['required', 'numeric', 'min:0'],
            'destination' => ['required', Rule::in(['bank', 'reservoir'])],
            'deposit_receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120', Rule::requiredIf(fn () => $request->input('destination') === 'bank')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $userId = (int) $data['sales_person_id'];
        abort_if(DailySalesBalance::where('sales_person_id', $userId)->whereDate('business_date', $data['business_date'])->exists(), 422, 'This sales day has already been closed.');
        $cashReceived = (float) SalesPayment::whereDate('paid_on', $data['business_date'])
            ->where('payment_method', 'cash')
            ->whereHas('sale', fn ($query) => $query->where('sold_by', $userId)->whereNotIn('status', ['void']))
            ->sum('amount');
        $priorCash = (float) SalesPayment::whereDate('paid_on', '<', $data['business_date'])
            ->where('payment_method', 'cash')
            ->whereHas('sale', fn ($query) => $query->where('sold_by', $userId)->whereNotIn('status', ['void']))
            ->sum('amount');
        $priorTransfers = (float) DailySalesBalance::where('sales_person_id', $userId)->whereDate('business_date', '<', $data['business_date'])->sum('amount_transferred');
        $opening = max(0, $priorCash - $priorTransfers);
        $cashDue = round($opening + $cashReceived, 2);
        abort_if(abs((float) $data['amount_transferred'] - $cashDue) > 0.009, 422, 'The transferred amount must equal the cash to account for: UGX '.number_format($cashDue, 2).'.');

        $receiptPath = $request->file('deposit_receipt')?->store('daily-sales-deposit-receipts', 'public');

        DailySalesBalance::create([
            ...collect($data)->except(['sales_person_id', 'deposit_receipt'])->all(),
            'sales_person_id' => $userId,
            'receipt_path' => $receiptPath,
            'opening_balance' => $opening,
            'cash_received' => $cashReceived,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return redirect()->route('sales.index', ['screen' => 'balancing', 'balance_date' => $data['business_date'], 'sales_person_id' => $userId])
            ->with('status', 'Sales day balanced and closed. The next day starts with UGX 0 brought forward.');
    }

    public function dailyBalanceReceipt(Request $request, DailySalesBalance $dailySalesBalance): StreamedResponse
    {
        abort_unless($this->isFinanceUser($request), 403);
        abort_unless($dailySalesBalance->receipt_path && Storage::disk('public')->exists($dailySalesBalance->receipt_path), 404, 'Receipt file not found.');

        return Storage::disk('public')->response(
            $dailySalesBalance->receipt_path,
            basename($dailySalesBalance->receipt_path),
            ['Content-Disposition' => 'inline; filename="'.basename($dailySalesBalance->receipt_path).'"']
        );
    }

    private function isFinanceUser(Request $request): bool
    {
        $user = $request->user();
        $roles = $user->roles->pluck('code')->push($user->role?->code);

        return $roles->contains('finance-manager')
            || $user->department?->code === 'finance'
            || $user->departments->contains('code', 'finance');
    }

    private function farmProductsInStock($eggStock)
    {
        $poultry = $eggStock->map(fn ($stock) => [
            'item' => $stock['name'],
            'detail' => number_format($stock['trays']).' full trays and '.number_format($stock['loose']).' loose eggs',
            'quantity' => $stock['eggs'],
            'unit' => 'eggs',
        ]);

        foreach (AnimalGroup::with('breed')->active()->where('current_quantity', '>', 0)->orderBy('code')->get() as $group) {
            $poultry->push([
                'item' => $group->name ?: $group->code,
                'detail' => collect([$group->code, $group->breed?->name, $group->house])->filter()->unique()->implode(' · '),
                'quantity' => (float) $group->current_quantity,
                'unit' => 'birds',
            ]);
        }

        $stock = collect(['poultry' => $poultry]);
        foreach (['goats', 'rabbits', 'dogs'] as $unit) {
            $stock[$unit] = DB::table('livestock_groups')
                ->where('unit_code', $unit)
                ->where('status', 'active')
                ->where('current_quantity', '>', 0)
                ->orderBy('group_code')
                ->get()
                ->map(fn ($group) => [
                    'item' => $group->group_code,
                    'detail' => $group->breed ?: 'Breed not recorded',
                    'quantity' => (float) $group->current_quantity,
                    'unit' => 'animals',
                ]);
        }

        $gardenRecords = FarmUnitRecord::query()
            ->where('unit_code', 'garden')
            ->where('verification_status', 'approved')
            ->whereIn('record_type', ['harvest', 'loss', 'sale'])
            ->whereNotNull('quantity')
            ->orderBy('subject')
            ->get();
        $stock['garden'] = $gardenRecords
            ->groupBy(fn ($record) => mb_strtolower(trim($record->subject)).'|'.mb_strtolower(trim($record->measure_unit ?: 'units')))
            ->map(function ($records) {
                $first = $records->first();
                $available = $records->sum(fn ($record) => $record->record_type === 'harvest'
                    ? (float) $record->quantity
                    : -(float) $record->quantity);

                return [
                    'item' => $first->subject,
                    'detail' => 'Approved harvest balance',
                    'quantity' => max(0, $available),
                    'unit' => $first->measure_unit ?: 'units',
                ];
            })
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->sortBy('item')
            ->values();

        return $stock;
    }

    private function normalisedRevenueLines($sales)
    {
        return $sales->flatMap(function (Sale $sale) {
            $lineTotal = max(0.0, (float) $sale->lines->sum('line_total'));
            $factor = $lineTotal > 0 ? (float) $sale->total / $lineTotal : 1.0;
            return $sale->lines->map(fn (SaleLine $line) => [
                'category'=>$this->revenueCategory($line),
                'month'=>$sale->sale_date->format('Y-m'),
                'date'=>$sale->sale_date->copy()->startOfDay(),
                'amount'=>(float) $line->line_total * $factor,
            ]);
        });
    }

    private function revenueCategory(SaleLine $line): string
    {
        $category = Str::lower((string) $line->category);
        $name = Str::lower((string) $line->description);
        return match (true) {
            str_contains($name, 'large egg') => 'Large eggs',
            str_contains($name, 'starter egg') => 'Starter eggs',
            str_contains($name, 'medium egg') => 'Medium eggs',
            str_contains($name, 'damaged egg') || str_contains($name, 'broken egg') || str_contains($name, 'dirty egg') || str_contains($name, 'soft shell') => 'Damaged eggs',
            str_contains($name, 'off layer') || str_contains($name, 'spent layer') => 'Off-layers',
            $category === 'manure' || str_contains($name, 'manure') => 'Manure',
            $category === 'garden' => 'Garden sales',
            $category === 'goats' && str_contains($name, 'meat') => 'Goat meat sales',
            $category === 'goats' || str_contains($name, 'goat') => 'Goat sales',
            $category === 'rabbits' && str_contains($name, 'meat') => 'Rabbit meat sales',
            $category === 'rabbits' || str_contains($name, 'rabbit') => 'Rabbit sales',
            default => 'Other sales',
        };
    }

    public function exportAnalysis(Request $request, BrandedReportExport $export): StreamedResponse
    {
        abort_unless($this->canViewSalesReports($request), 403);
        $view = $request->query('analysis_view');
        abort_unless(in_array($view, ['summary', 'monthly', 'product_amount', 'product_quantity', 'revenue'], true), 404);

        if ($view === 'monthly') {
            $fromMonth = $request->query('from_month', now()->subMonths(11)->format('Y-m'));
            $toMonth = $request->query('to_month', now()->format('Y-m'));
            abort_unless(preg_match('/^\d{4}-\d{2}$/', $fromMonth) && preg_match('/^\d{4}-\d{2}$/', $toMonth), 422);
            $from = \Carbon\Carbon::parse($fromMonth.'-01')->startOfMonth();
            $to = \Carbon\Carbon::parse($toMonth.'-01')->endOfMonth();
        } else {
            $from = \Carbon\Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
            $to = \Carbon\Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        }
        abort_if($from->gt($to), 422, 'The report start must not be after its end.');

        $sales = Sale::with('lines')->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])->whereNotIn('status', ['void'])->where('approval_status', 'approved')->where('is_staff_consumption', false)->get();
        $total = (float) $sales->sum('total');
        $customers = SalesCustomer::where('is_active', true)->pluck('name')->merge($sales->pluck('customer_name'))->filter()->unique()->sort()->values();

        if ($view === 'revenue') {
            $year = (int) $request->query('financial_year', now()->month >= 7 ? now()->year : now()->subYear()->year);
            abort_unless($year >= 2000 && $year <= now()->addYear()->year, 422, 'Choose a valid financial year.');
            $yearStart = \Carbon\Carbon::create($year, 7, 1)->startOfDay();
            $yearEnd = $yearStart->copy()->addYear()->subDay()->endOfDay();
            $months = collect(\Carbon\CarbonPeriod::create($yearStart, '1 month', $yearEnd->copy()->startOfMonth()));
            $revenueSales = Sale::with('lines')->whereBetween('sale_date', [$yearStart->toDateString(), $yearEnd->toDateString()])->whereNotIn('status', ['void'])->where('approval_status', 'approved')->where('is_staff_consumption', false)->get();
            $lines = $this->normalisedRevenueLines($revenueSales);
            $categories = collect(['Large eggs','Manure','Off-layers','Starter eggs','Damaged eggs','Medium eggs','Garden sales','Other sales','Goat sales','Rabbit sales','Rabbit meat sales','Goat meat sales']);
            $headers = array_merge(['Revenue category'], $months->map->format('M-y')->all(), ['Total (UGX)']);
            $rows = $categories->map(function ($category) use ($months, $lines) {
                $values = $months->map(fn ($month) => (float) $lines->where('category', $category)->where('month', $month->format('Y-m'))->sum('amount'));
                return array_merge([$category], $values->map(fn ($value) => number_format($value, 2))->all(), [number_format($values->sum(), 2)]);
            });
            $rows->push(array_merge(['Total operating revenue'], $months->map(fn ($month) => number_format((float) $lines->where('month', $month->format('Y-m'))->sum('amount'), 2))->all(), [number_format((float) $lines->sum('amount'), 2)]));
            return $export->download('Revenue Analysis', 'Financial year: '.$yearStart->format('d M Y').' to '.$yearEnd->format('d M Y'), $headers, $rows, 'revenue-analysis-'.$year.'.xls', $request->user()->displayName());
        } elseif ($view === 'summary') {
            $ranked = $sales->groupBy('customer_name')->map(fn ($items, $name) => ['name'=>$name, 'amount'=>(float)$items->sum('total')])->sortByDesc('amount')->values();
            $rows = $ranked->map(fn ($row, $index) => [$row['name'], number_format($row['amount'], 2), number_format($total > 0 ? $row['amount'] / $total * 100 : 0, 1).'%', $index + 1]);
            $headers = ['Current receivables', 'Amount (UGX)', '%', 'Rank'];
            $title = 'Sales Analysis - Summary';
        } elseif ($view === 'monthly') {
            $months = collect(\Carbon\CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()));
            $headers = array_merge(['Current receivables'], $months->map->format('M-y')->all());
            $rows = $customers->map(fn ($name) => array_merge([$name], $months->map(fn ($month) => (float)$sales->where('customer_name', $name)->filter(fn ($sale) => $sale->sale_date->format('Y-m') === $month->format('Y-m'))->sum('total'))->all()));
            $title = 'Sales Analysis - Month on Month';
        } else {
            $products = SaleProduct::where('is_active', true)->pluck('name')->merge($sales->flatMap->lines->pluck('description'))->filter()->unique()->sort()->values();
            $headers = array_merge(['Current receivables'], $products->all());
            $field = $view === 'product_amount' ? 'line_total' : 'quantity';
            $rows = $customers->map(function ($name) use ($sales, $products, $field) {
                $lines = $sales->where('customer_name', $name)->flatMap->lines;
                return array_merge([$name], $products->map(fn ($product) => (float)$lines->where('description', $product)->sum($field))->all());
            });
            $title = $view === 'product_amount' ? 'Sales Analysis - Product Type by Amount' : 'Sales Analysis - Product Type by Quantity';
        }

        return $export->download($title, 'Reporting period: '.$from->format('d M Y').' to '.$to->format('d M Y'), $headers, $rows, str($title)->slug().'-'.now()->format('Ymd-His').'.xls', $request->user()->displayName());
    }

    public function storeProduct(Request $request, CommunicationService $communications): RedirectResponse
    {
        abort_unless($this->canManagePrices($request), 403);
        $data = $request->validate(['code' => ['nullable', 'string', 'max:50'], 'name' => ['required', 'string', 'max:160'], 'category_choice' => ['required', Rule::in([...self::CATEGORIES, 'custom'])], 'custom_category' => ['nullable', 'required_if:category_choice,custom', 'string', 'max:40'], 'unit' => ['required', 'string', 'max:40'], 'unit_price' => ['required', 'numeric', 'min:0'], 'loose_unit_price' => ['nullable', 'numeric', 'min:0'], 'availability' => ['required', Rule::in(['in_stock', 'out_of_stock', 'untracked'])], 'stock_quantity' => ['nullable', 'required_if:availability,in_stock', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $data['category'] = $data['category_choice'] === 'custom' ? strtolower(trim($data['custom_category'])) : $data['category_choice'];
        $wholeUnits = ['animal', 'head', 'bird', 'chick', 'egg', 'piece'];
        if ($data['availability'] === 'in_stock' && in_array($data['unit'], $wholeUnits, true) && floor((float) $data['stock_quantity']) !== (float) $data['stock_quantity']) {
            return back()->withInput()->withErrors(['stock_quantity' => 'Availability for '.$data['unit'].' must be entered as a whole number.']);
        }
        $data['code'] = $this->uniqueProductCode($data['category'], $data['name']);
        $data['stock_quantity'] = match ($data['availability']) {
            'out_of_stock' => 0,
            'untracked' => null,
            default => $data['stock_quantity'],
        };
        unset($data['category_choice'],$data['custom_category'],$data['availability']);
        $proposedPrice=$data['unit_price']; $proposedLoose=$data['loose_unit_price']??null;
        $productData=$data; $productData['unit_price']=0; $productData['loose_unit_price']=null; $productData['is_active']=false;
        $product=SaleProduct::create($productData);
        SalePriceChangeRequest::create(['sale_product_id'=>$product->id,'previous_unit_price'=>0,'proposed_unit_price'=>$proposedPrice,'previous_loose_unit_price'=>null,'proposed_loose_unit_price'=>$proposedLoose,'effective_from'=>now()->toDateString(),'request_note'=>'Initial selling price for a new catalogue product.','status'=>'pending','requested_by'=>$request->user()->id]);
        $communications->toRole('director','New product price approval: '.$product->name,'Approve the initial price of UGX '.number_format((float)$proposedPrice).' before this product becomes available for sale.',$request->user()->id,route('sales.price-approvals',[],false));
        return back()->with('status', 'Product saved and its initial price sent to the Director. It will appear for sale after approval.');
    }

    private function uniqueProductCode(string $category, string $name): string
    {
        $itemWords = explode('-', Str::upper(Str::slug($name, '-')));
        $prefix = match (true) {
            in_array('RABBIT', $itemWords, true) => 'RABBIT',
            in_array('GOAT', $itemWords, true) => 'GOAT',
            count(array_intersect(['EGG', 'EGGS'], $itemWords)) > 0 => 'EGG',
            count(array_intersect(['DOG', 'PUPPY', 'PUPPIES'], $itemWords)) > 0 => 'DOG',
            default => ['eggs'=>'EGG', 'rabbits'=>'RABBIT', 'goats'=>'GOAT', 'poultry'=>'BIRD', 'dogs'=>'DOG', 'garden'=>'GARDEN'][$category] ?? Str::upper(Str::slug($category, '-')),
        };
        $item = implode('-', array_values(array_filter($itemWords, fn ($word) => ! in_array($word, [$prefix, $prefix.'S'], true)))) ?: 'ITEM';
        $base = Str::limit(trim($prefix.'-'.$item, '-'), 45, '');
        $code = $base;
        $suffix = 2;
        while (SaleProduct::where('code', $code)->exists()) {
            $code = Str::limit($base, 45 - strlen((string) $suffix), '').'-'.$suffix++;
        }

        return $code;
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
            || $roleCodes->intersect(['ceo', 'director', 'finance-manager'])->isNotEmpty();
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('customers', 'create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'], 'address' => ['nullable', 'string', 'max:1000'], 'tax_number' => ['nullable', 'string', 'max:80'], 'credit_limit' => ['nullable', 'numeric', 'min:0']]);
        SalesCustomer::create($data + ['credit_limit' => $data['credit_limit'] ?? 0, 'is_active' => true]);

        return back()->with('status', 'Customer added.');
    }

    public function storeSale(Request $request, CommunicationService $communications): RedirectResponse
    {
        abort_unless($request->user()->canDo('sales', 'create'), 403);
        $data = $request->validate(['sale_date' => ['required', 'date', 'before_or_equal:today'], 'customer_id' => ['nullable', 'exists:sales_customers,id'], 'walk_in_customer' => ['nullable', 'string', 'max:160'], 'staff_consumption'=>['nullable','boolean'], 'consumed_by'=>['nullable','required_if:staff_consumption,1','string','max:180'], 'payment_type' => ['required', 'in:cash'], 'payment_method' => ['nullable', Rule::in(self::METHODS)], 'farm_account' => ['nullable', 'string', 'max:120'], 'amount_paid' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'numeric', 'min:0'], 'merchant_code' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:100'], 'payment_receipt' => ['nullable', 'required_if:payment_method,bank', 'required_if:payment_method,agent', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.product_id' => ['nullable', 'required_without:lines.*.description', 'exists:sale_products,id'], 'lines.*.category' => ['nullable', 'required_without:lines.*.product_id', Rule::in(self::CATEGORIES)], 'lines.*.description' => ['nullable', 'required_without:lines.*.product_id', 'string', 'max:180'], 'lines.*.unit' => ['nullable', 'required_without:lines.*.product_id', Rule::in(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'])], 'lines.*.quantity' => ['required', 'numeric', 'min:0.01'], 'lines.*.unit_price' => ['required', 'numeric', 'min:0'], 'lines.*.poultry_house_id' => ['nullable', 'exists:poultry_houses,id']]);
        $receiptPath = $request->file('payment_receipt')?->store('sales-payment-receipts', 'public');
        $staffConsumption = $request->boolean('staff_consumption') || ($data['payment_method'] ?? null) === 'farm_consumption';
        if ($staffConsumption) {
            $data['payment_type']='cash'; $data['payment_method']=null;
            $data['farm_account']=null;
            $data['walk_in_customer']='Internal farm use - '.trim($data['consumed_by']);
        }
        $customerId = $data['customer_id'] ?? null;
        $customer = $customerId ? SalesCustomer::find($customerId) : null;
        $enteredName = trim($data['walk_in_customer'] ?? '');
        if (! $staffConsumption && ! $customer && $enteredName !== '' && strcasecmp($enteredName, 'One-time customer') !== 0) {
            $customer = SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower($enteredName)])->first()
                ?: SalesCustomer::create(['name' => $enteredName, 'credit_limit' => 0, 'is_active' => true]);
        }
        $customerName = $staffConsumption ? $data['walk_in_customer'] : ($customer?->name ?: 'One-time customer');
        try {
            $sale = DB::transaction(function () use ($data, $customer, $customerName, $request, $staffConsumption, $receiptPath) {
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
                    $description = $product?->name ?: ($line['description'] ?? '');
                    $isOffLayer = $saleUnit === 'bird' && str_contains(strtolower($description), 'layer');
                    if ($isOffLayer && empty($line['poultry_house_id'])) throw new \RuntimeException('Choose the poultry house supplying the off-layer birds.');
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
                    $lines[] = [$product, $line['category'] ?? null, $line['description'] ?? null, $saleUnit, $qty, $price, $total, $isOffLayer ? (int)$line['poultry_house_id'] : null];
                }
                $discount = (float) ($data['discount'] ?? 0);
                $total = max(0, round($subtotal - $discount, 2));
                $paid = $staffConsumption ? 0 : (float) ($data['amount_paid'] ?? 0);
                if ($paid > $total) {
                    throw new \RuntimeException('Amount paid cannot exceed the sale total.');
                }if (! $staffConsumption && $data['payment_type'] === 'cash') {
                    $paid = $total;
                }$balance = $staffConsumption ? 0 : round($total - $paid, 2);
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
                $sale = Sale::create(['invoice_number' => $this->nextInvoice(), 'sale_date' => $data['sale_date'], 'customer_id' => $customer?->id, 'customer_name' => $customerName, 'payment_type' => $data['payment_type'], 'payment_method' => $data['payment_method'] ?? null, 'farm_account' => $data['farm_account'] ?? null, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total, 'amount_paid' => $paid, 'balance' => $balance, 'status' => $staffConsumption ? 'internal_use' : ($balance > 0 ? 'partly_paid' : 'paid'), 'approval_status'=>'pending', 'is_staff_consumption'=>$staffConsumption, 'consumed_by'=>$data['consumed_by']??null, 'notes' => $data['notes'] ?? null, 'sold_by' => $request->user()->id]);
                foreach ($lines as [$product,$category,$description,$unit,$qty,$price,$lineTotal,$houseId]) {
                    $house = null;
                    $batch = null;
                    if ($houseId) {
                        $house = PoultryHouse::lockForUpdate()->findOrFail($houseId);
                        if (! $house->is_active || ! $house->animal_group_id) throw new \RuntimeException('The selected poultry house is no longer occupied and active.');
                        if (floor($qty) !== $qty) throw new \RuntimeException('Off-layer birds must be sold as a whole number.');
                        if ($qty > $house->bird_quantity) throw new \RuntimeException('Only '.number_format($house->bird_quantity).' birds remain in '.$house->displayNumber().'.');
                        $batch = AnimalGroup::lockForUpdate()->findOrFail($house->animal_group_id);
                        if ($qty > $batch->current_quantity) throw new \RuntimeException('The sale exceeds the remaining bird balance for batch '.$batch->code.'.');
                    }
                    SaleLine::create(['sale_id' => $sale->id, 'product_id' => $product?->id, 'animal_group_id' => $batch?->id, 'poultry_house_id' => $house?->id, 'category' => $product?->category ?: strtolower(trim((string) $category)), 'description' => $product?->name ?: $description, 'unit' => $unit, 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $lineTotal]);
                    if ($house && $batch) {
                        $sold = (int)$qty;
                        $house->decrement('bird_quantity', $sold);
                        $batch->decrement('current_quantity', $sold);
                        $batch->increment('total_sold', $sold);
                        $house->refresh();
                        if ($house->bird_quantity === 0) {
                            PoultryHouseAssignment::where('poultry_house_id', $house->id)->whereNull('assigned_until')->update(['assigned_until'=>$data['sale_date'], 'updated_at'=>now()]);
                            $house->update(['animal_group_id'=>null, 'is_active'=>true, 'notes'=>trim(($house->notes ? $house->notes."\n\n" : '').'Freed after final off-layer sale '.$sale->invoice_number.' on '.$data['sale_date'].'.')]);
                        }
                    }
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
                if ($staffConsumption) {
                    FinancialTransaction::create(['transaction_date'=>$data['sale_date'],'type'=>'expense_recognized','transaction_category'=>'internal_product_use','account_group'=>'Operating Expenses','entry_account'=>'Farm Products Used Internally','account'=>'Non-cash internal use','counterparty'=>$data['consumed_by'],'amount'=>$total,'payment_method'=>null,'reference'=>$sale->invoice_number,'description'=>'Farm products used internally without payment: '.$sale->invoice_number,'recorded_by'=>$request->user()->id]);
                }

                return $sale;
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['sale' => $e->getMessage()])->withInput();
        }

        $approval = $sale->approvals()->create(['module_key'=>'sales','department_id'=>$request->user()->department_id,'submitted_by'=>$request->user()->id,'submitted_at'=>now(),'submission_note'=>($staffConsumption?'Internal farm product use':'Customer sale').' '.$sale->invoice_number.' requires Finance and CEO approval'.($staffConsumption?'.':' before receipt printing.'),'status'=>'pending','level'=>1,'required_levels'=>2,'due_at'=>now()->addDay()]);
        $approval->steps()->createMany([['stage'=>1,'role_code'=>'finance-manager','status'=>'pending'],['stage'=>2,'role_code'=>'ceo','status'=>'waiting']]);
        $message=$staffConsumption
            ? $sale->invoice_number.' records UGX '.number_format((float)$sale->total).' of farm products used internally by '.$data['consumed_by'].', entered by '.$request->user()->displayName().'. No payment or revenue was recorded.'
            : $sale->invoice_number.' for UGX '.number_format((float)$sale->total).' was entered by '.$request->user()->displayName().'. Review it before its receipt can be printed.';
        $communications->toRole('finance-manager',($staffConsumption?'Internal farm use':'Sale').' awaiting approval: '.$sale->invoice_number,$message,$request->user()->id,route('approvals.show',$approval,false));
        $communications->toRole('ceo',($staffConsumption?'Internal farm use recorded: ':'New sale entered: ').$sale->invoice_number,$message.' Finance approval is the first stage.',$request->user()->id,route('approvals.show',$approval,false));

        return redirect()->to(route('dashboard', ['sales_from' => $data['sale_date'], 'sales_to' => $data['sale_date']]).'#daily-sales-table')->with('status', $staffConsumption ? 'Internal farm use recorded with no payment or revenue and sent for approval.' : 'Sale recorded. Finance and the CEO have been notified for approval.');
    }

    public function show(Request $request, Sale $sale): View
    {
        abort_unless($request->user()->canAccess('sales') || $request->user()->canAccess('finance'), 403);
        $sale->load(['lines.product', 'lines.batch', 'lines.poultryHouse', 'payments', 'customer', 'seller']);

        $prepayments=CustomerPrepayment::whereRaw('amount_used < amount')->where('customer_id',$sale->customer_id)->orderBy('received_date')->get();
        return view('sales.show', compact('sale','prepayments'));
    }

    public function receipt(Request $request, Sale $sale, SalesPayment $payment): View
    {
        abort_unless($request->user()->canAccess('sales') || $request->user()->canAccess('finance'), 403);
        abort_unless($payment->sale_id === $sale->id, 404);
        abort_unless($sale->approval_status === 'approved', 403, 'Finance and the CEO must approve this sale before its receipt can be printed.');
        $sale->load(['lines.batch', 'lines.poultryHouse', 'customer']);
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
        DB::transaction(function () use ($sale, $data, $request, $receiptPath) {
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
        if ($method === 'in_kind') {
            FinancialTransaction::create(['transaction_date'=>$date,'type'=>'revenue_recognized','transaction_category'=>'revenue_in_kind','account_group'=>'Sales','entry_account'=>'Revenue in kind','account'=>$account,'counterparty'=>$sale->customer_name,'amount'=>$amount,'payment_method'=>'in_kind','reference'=>$payment->receipt_number,'description'=>'Farm product used or consumed by staff without cash payment: '.$sale->invoice_number,'recorded_by'=>$userId]);
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
