<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CustomerPrepayment;
use App\Models\Department;
use App\Models\FinancialTransaction;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStore;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SalesCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $r): View
    {
        abort_unless($r->user()->canAccess('transactions'), 403);
        $q = FinancialTransaction::with(['recorder','department']);
        if ($r->filled('type')) {
            $q->where('type', $r->query('type'));
        }
        if ($r->filled('from')) $q->whereDate('transaction_date', '>=', $r->date('from')->toDateString());
        if ($r->filled('to')) $q->whereDate('transaction_date', '<=', $r->date('to')->toDateString());
        if ($r->filled('department')) $q->where('department_id', $r->integer('department'));
        if ($r->filled('item')) {
            $item = $r->string('item')->trim()->toString();
            $q->where(fn($query) => $query->where('entry_account', $item)->orWhere('sub_account', $item));
        }
        if ($r->filled('search')) {
            $search = '%'.$r->string('search')->trim()->toString().'%';
            $q->where(fn($query) => $query->where('entry_account','ilike',$search)
                ->orWhere('sub_account','ilike',$search)->orWhere('description','ilike',$search)
                ->orWhere('counterparty','ilike',$search)->orWhere('reference','ilike',$search));
        }

        $filteredTotal = (clone $q)->sum('amount');
        $expenseItems = FinancialTransaction::query()->where('type','money_paid')
            ->get(['entry_account','sub_account'])->flatMap(fn($row)=>[$row->entry_account,$row->sub_account])
            ->filter()->unique()->sort()->values();
        $departments = Department::where('is_active',true)->orderBy('name')->get(['id','name']);

        return view('finance.transactions.index', [
            'transactions'=>$q->latest('transaction_date')->latest('id')->paginate(30)->withQueryString(), 'type'=>$r->query('type'),
            'filteredTotal'=>$filteredTotal, 'expenseItems'=>$expenseItems, 'departments'=>$departments,
        ]);
    }

    public function create(Request $r): View
    {
        abort_unless($r->user()->canDo('transactions', 'create'), 403);
        $configured = Schema::hasTable('finance_payment_accounts') ? DB::table('finance_payment_accounts')->where('is_active', true)->orderBy('name')->pluck('name')->all() : [];
        $farmAccounts = array_values(array_unique(array_merge($configured, ['Cash on Hand', 'Petty Cash (UGX)', 'Petty Cash (USD)', 'Centenary Bank - Sales', 'Centenary Bank - Operations', 'Centenary Bank - Garden', 'In-kind / Barter Clearing'], FinancialTransaction::query()->whereNotNull('account')->distinct()->orderBy('account')->pluck('account')->all())));
        $paymentMethods = Schema::hasTable('finance_payment_methods') ? DB::table('finance_payment_methods')->where('is_active', true)->orderBy('name')->get() : collect([(object) ['code' => 'cash', 'name' => 'Cash'], (object) ['code' => 'bank', 'name' => 'Bank'], (object) ['code' => 'mobile_money', 'name' => 'Mobile money'], (object) ['code' => 'credit', 'name' => 'Credit'], (object) ['code' => 'in_kind', 'name' => 'In kind / barter']]);
        $receivables = Receivable::where('status', 'open')->orderBy('customer_name')->get();
        $payables = Payable::where('status', 'open')->orderBy('supplier_name')->get();
        $entryTypes = config('finance_entry.types');
        $reportingDepartments = config('finance_entry.reporting_departments');
        $departmentCodes = array_keys($reportingDepartments);
        $departments = Department::where('is_active', true)
            ->whereIn('code', $departmentCodes)
            ->get()
            ->sortBy(fn (Department $department) => array_search($department->code, $departmentCodes, true))
            ->values();
        $costCentres = config('finance_entry.cost_centres');

        $purchaseSubAccounts = config('finance_entry.purchase_sub_accounts');
        $purchaseSettlementAccounts = config('finance_entry.purchase_settlement_accounts');
        $suppliers = collect(config('finance_entry.suppliers'))
            ->merge(Payable::query()->whereNotNull('supplier_name')->pluck('supplier_name'))
            ->merge(DB::table('purchases')->whereNotNull('supplier_name')->pluck('supplier_name'))
            ->merge(FinancialTransaction::query()->where('transaction_category', 'purchase')->whereNotNull('counterparty')->pluck('counterparty'))
            ->map(fn ($name) => trim((string) $name))->filter()->unique()->sort()->values();
        $customers = SalesCustomer::where('is_active', true)->orderBy('name')->get();

        $inventoryStores = InventoryStore::where('is_active', true)->orderBy('section')->orderBy('name')->get();
        return view('finance.transactions.create', compact('farmAccounts', 'paymentMethods', 'receivables', 'payables', 'entryTypes', 'departments', 'costCentres', 'purchaseSubAccounts', 'purchaseSettlementAccounts', 'suppliers', 'customers', 'inventoryStores'));
    }

    public function store(Request $r): RedirectResponse
    {
        abort_unless($r->user()->canDo('transactions', 'create'), 403);
        $types = config('finance_entry.types');
        $data = $r->validate(['transaction_date' => ['required', 'date'], 'transaction_category' => ['required', Rule::in(array_keys($types))], 'account_group' => ['required', 'string', 'max:120'], 'department_id' => ['required', 'exists:departments,id'], 'account' => ['required', 'string', 'max:120'], 'counterparty' => ['nullable', 'string', 'max:160'], 'amount' => ['nullable', 'numeric', 'min:0.01'], 'quantity' => ['nullable', 'numeric', 'min:0.01'], 'unit_price' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'in:cash,bank,mobile_money,credit,in_kind'], 'reference' => ['required', 'string', 'max:80'], 'description' => ['required', 'string', 'max:1000'], 'receipt_kind' => ['nullable', 'in:general,customer_payment,customer_prepayment'], 'payment_kind' => ['nullable', 'in:general,supplier_bill'], 'receivable_id' => ['nullable', 'exists:receivables,id'], 'payable_id' => ['nullable', 'exists:payables,id']]);
        $data['entry_account'] = $r->validate(['entry_account' => ['required', 'string', 'max:160']])['entry_account'];
        $inventory = $r->validate(['track_inventory' => ['nullable', 'boolean'], 'inventory_store_id'=>['nullable','required_if:track_inventory,1','exists:inventory_stores,id'], 'inventory_unit' => ['nullable', 'required_if:track_inventory,1', 'string', 'max:40'], 'inventory_location' => ['nullable', 'required_if:track_inventory,1', 'string', 'max:100']]);
        $departmentCode = Department::whereKey($data['department_id'])->value('code');
        $allowedCostCentres = config('finance_entry.cost_centres')[$departmentCode] ?? [];
        $data['cost_centre'] = $r->validate(['cost_centre' => ['required', Rule::in($allowedCostCentres)]])['cost_centre'];
        $subAccounts = config('finance_entry.purchase_sub_accounts');
        $allowedSubAccounts = $data['transaction_category'] === 'purchase' ? ($subAccounts[$data['entry_account']] ?? []) : [];
        $data['sub_account'] = $allowedSubAccounts
            ? $r->validate(['sub_account' => ['required', Rule::in($allowedSubAccounts)]])['sub_account']
            : null;
        if ($data['transaction_category'] === 'purchase') {
            $data['counterparty'] = $r->validate(['counterparty' => ['required', 'string', 'max:160']])['counterparty'];
            $data['counterparty'] = trim($data['counterparty']);
            $allowedSettlementAccounts = config('finance_entry.purchase_settlement_accounts')[$data['entry_account']] ?? [];
            $data['account'] = $r->validate(['account' => ['required', Rule::in($allowedSettlementAccounts)]])['account'];
        }
        $trackInventory = $data['transaction_category'] === 'purchase' && (bool) ($inventory['track_inventory'] ?? false);
        if ($trackInventory && empty($data['quantity'])) {
            return back()->withErrors(['quantity' => 'Enter the purchased quantity for a stock-tracked item.'])->withInput();
        }
        $definition = $types[$data['transaction_category']];
        $data['type'] = $definition['ledger_type'];
        if ($data['transaction_category'] === 'capital_and_equity') {
            $equityActions = [
                'Capital Introduced' => 'money_received',
                'Shareholder Contribution' => 'money_received',
                'Owner Drawings' => 'money_paid',
                'Dividend Paid' => 'money_paid',
                'Reserve Transfer' => 'journal',
            ];
            $data['type'] = $equityActions[$data['entry_account']] ?? 'journal';
            $data['counterparty'] = $r->validate(['counterparty' => ['required', 'string', 'max:160']])['counterparty'];
            if ($data['type'] !== 'journal') {
                $data['account'] = $r->validate(['account' => ['required', 'string', 'max:120']])['account'];
            } else {
                $data['account'] = 'Equity Reserve';
                $data['payment_method'] = null;
            }
        }
        if (in_array($data['transaction_category'], ['revenue', 'customer_prepayment'], true)) {
            $customerName = trim($data['counterparty'] ?? '');
            if ($customerName === '' || strcasecmp($customerName, 'One-time customer') === 0) {
                $data['counterparty'] = 'One-time customer';
            } else {
                $customer = SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower($customerName)])->first()
                    ?: SalesCustomer::create(['name' => $customerName, 'credit_limit' => 0, 'is_active' => true]);
                $data['counterparty'] = $customer->name;
            }
        }
        if (empty($data['amount']) && ! empty($data['quantity']) && isset($data['unit_price'])) {
            $data['amount'] = (float) $data['quantity'] * (float) $data['unit_price'];
        } if (empty($data['amount'])) {
            return back()->withErrors(['amount' => 'Enter the amount, or enter quantity and unit price.'])->withInput();
        } if (($data['payment_method'] ?? null) === 'in_kind') {
            $data['account'] = 'In-kind / Barter Clearing';
        } if (in_array($data['type'], ['money_received', 'money_paid'], true) && empty($data['payment_method'])) {
            return back()->withErrors(['payment_method' => 'Choose how the money moved.'])->withInput();
        } if (in_array($data['type'], ['transfer', 'journal'], true) && empty($data['counterparty'])) {
            return back()->withErrors(['counterparty' => 'Choose the destination or offset account.'])->withInput();
        } if ($data['type'] === 'transfer' && $data['account'] === $data['counterparty']) {
            return back()->withErrors(['counterparty' => 'Choose a different destination account.'])->withInput();
        } $kind = $data['transaction_category'] === 'customer_prepayment'
            ? 'customer_prepayment'
            : ($data['receipt_kind'] ?? 'general');
        if ($data['type'] === 'money_received' && $kind === 'customer_prepayment') {
            // Customer advances increase cash and a liability, not earned income.
            $data['transaction_category'] = 'customer_prepayment';
            $data['account_group'] = 'Current Liabilities';
            $data['entry_account'] = 'Customer Prepayments';
            if (($data['payment_method'] ?? null) === 'credit') {
                return back()->withErrors(['payment_method' => 'A customer prepayment must actually be received by cash, bank, mobile money, or in kind.'])->withInput();
            }
        }
        $payKind = $data['payment_kind'] ?? 'general';
        if ($data['type'] === 'money_received' && $kind === 'customer_payment' && empty($data['receivable_id'])) {
            return back()->withErrors(['receivable_id' => 'Choose the customer amount being paid.'])->withInput();
        } if ($data['type'] === 'money_paid' && $payKind === 'supplier_bill' && empty($data['payable_id'])) {
            return back()->withErrors(['payable_id' => 'Choose the supplier bill being paid.'])->withInput();
        } unset($data['receipt_kind'],$data['payment_kind'],$data['receivable_id'],$data['payable_id']);
        if (in_array($data['type'], ['transfer', 'journal'], true)) {
            $data['payment_method'] = null;
        } try {
            DB::transaction(function () use ($data, $r, $kind, $payKind, $trackInventory, $inventory) {
                $transaction = FinancialTransaction::create($data + ['recorded_by' => $r->user()->id]);
                if ($trackInventory) {
                    $itemName = $data['sub_account'] ?: $data['entry_account'];
                    $code = 'AUTO-'.strtoupper(substr(hash('sha256', strtolower($data['entry_account'].'|'.$itemName.'|'.$inventory['inventory_unit'])), 0, 10));
                    $item = InventoryItem::firstOrCreate(['code' => $code], ['inventory_store_id'=>$inventory['inventory_store_id'],'name' => $itemName, 'category' => strtolower($data['entry_account']), 'unit' => $inventory['inventory_unit'], 'reorder_level' => 0, 'default_location' => $inventory['inventory_location'], 'notes' => 'Created automatically from Finance Data Entry.', 'created_by' => $r->user()->id]);
                    if(!$item->inventory_store_id)$item->update(['inventory_store_id'=>$inventory['inventory_store_id']]);
                    InventoryMovement::create(['inventory_item_id' => $item->id, 'inventory_store_id'=>$item->inventory_store_id,'financial_transaction_id' => $transaction->id, 'movement_date' => $data['transaction_date'], 'movement_type' => 'received', 'location' => $inventory['inventory_location'], 'quantity' => $data['quantity'], 'unit_cost' => $data['unit_price'], 'total_cost' => $data['amount'], 'supplier_or_recipient' => $data['counterparty'], 'reference' => $data['reference'], 'notes' => $data['description'], 'recorded_by' => $r->user()->id]);
                }
                if ($kind === 'customer_payment') {
                    $due = Receivable::lockForUpdate()->findOrFail($r->receivable_id);
                    $balance = (float) $due->amount - (float) $due->amount_paid;
                    if ((float) $data['amount'] > $balance) {
                        throw new \RuntimeException('Payment is more than the customer balance.');
                    }$due->amount_paid += (float) $data['amount'];
                    $due->status = (float) $due->amount_paid >= (float) $due->amount ? 'paid' : 'open';
                    $due->save();
                    ReceivablePayment::create(['receivable_id' => $due->id, 'received_date' => $data['transaction_date'], 'amount' => $data['amount'], 'farm_account' => $data['account'], 'payment_method' => $data['payment_method'], 'reference' => $data['reference'], 'recorded_by' => $r->user()->id]);
                } elseif ($kind === 'customer_prepayment') {
                    $customer = SalesCustomer::whereRaw('lower(name) = ?', [mb_strtolower($data['counterparty'])])->firstOrFail();
                    CustomerPrepayment::create(['customer_id' => $customer->id, 'customer_name' => $customer->name, 'received_date' => $data['transaction_date'], 'amount' => $data['amount'], 'amount_used' => 0, 'farm_account' => $data['account'], 'payment_method' => $data['payment_method'], 'reference' => $data['reference'], 'recorded_by' => $r->user()->id]);
                }if ($payKind === 'supplier_bill') {
                    $bill = Payable::lockForUpdate()->findOrFail($r->payable_id);
                    $balance = (float) $bill->amount - (float) $bill->amount_paid;
                    if ((float) $data['amount'] > $balance) {
                        throw new \RuntimeException('Payment is more than the supplier bill.');
                    }$bill->amount_paid += (float) $data['amount'];
                    $bill->status = (float) $bill->amount_paid >= (float) $bill->amount ? 'paid' : 'open';
                    $bill->save();
                    PayablePayment::create(['payable_id' => $bill->id, 'paid_date' => $data['transaction_date'], 'amount' => $data['amount'], 'farm_account' => $data['account'], 'payment_method' => $data['payment_method'], 'reference' => $data['reference'], 'recorded_by' => $r->user()->id]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('finance.transactions.index')->with('status', 'Transaction posted to the register and ledger.');
    }

    public function generalLedger(Request $r): View
    {
        abort_unless($r->user()->canAccess('general-ledger'), 403);
        $entries = collect();
        FinancialTransaction::orderBy('transaction_date')->orderBy('id')->get()->each(function ($transaction) use ($entries) {
            $source = $transaction->account;
            $other = $transaction->counterparty ?: 'Unspecified account';
            if ($transaction->transaction_category === 'customer_prepayment') {
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => $source, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->amount, 'credit' => 0]);
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => 'Customer Prepayments (Liability)', 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => 0, 'credit' => $transaction->amount]);
            } elseif ($transaction->type === 'revenue_recognized') {
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => 'Customer Prepayments (Liability)', 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->amount, 'credit' => 0]);
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => 'Sales Revenue', 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => 0, 'credit' => $transaction->amount]);
            } elseif ($transaction->type === 'money_received') {
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => $source, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->amount, 'credit' => 0]);
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => 'Income / '.$other, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => 0, 'credit' => $transaction->amount]);
            } elseif ($transaction->type === 'money_paid') {
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => 'Expense / '.$other, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->amount, 'credit' => 0]);
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => $source, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => 0, 'credit' => $transaction->amount]);
            } else {
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => $source, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->type === 'journal' ? $transaction->amount : 0, 'credit' => $transaction->type === 'transfer' ? $transaction->amount : 0]);
                $entries->push((object) ['date' => $transaction->transaction_date, 'account' => $other, 'description' => $transaction->description, 'reference' => $transaction->reference, 'debit' => $transaction->type === 'transfer' ? $transaction->amount : 0, 'credit' => $transaction->type === 'journal' ? $transaction->amount : 0]);
            }
        });
        $accounts = $entries->pluck('account')->unique()->sort()->values();
        if ($r->filled('from')) $entries = $entries->filter(fn ($entry) => $entry->date->toDateString() >= $r->query('from'))->values();
        if ($r->filled('to')) $entries = $entries->filter(fn ($entry) => $entry->date->toDateString() <= $r->query('to'))->values();
        if ($r->filled('account')) {
            $entries = $entries->where('account', $r->query('account'))->values();
        } $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        return view('finance.transactions.general-ledger', compact('entries', 'accounts', 'totalDebit', 'totalCredit'));
    }

    public function cashBook(Request $r): View
    {
        abort_unless($r->user()->canAccess('cash-book'), 403);
        $rows = FinancialTransaction::with('recorder')->where('payment_method', 'cash')
            ->when($r->filled('from'), fn($q)=>$q->whereDate('transaction_date','>=',$r->query('from')))
            ->when($r->filled('to'), fn($q)=>$q->whereDate('transaction_date','<=',$r->query('to')))
            ->orderBy('transaction_date')->orderBy('id')->get();
        $balance = 0;
        $rows->each(function ($x) use (&$balance) {
            $balance += $x->type === 'money_received' ? $x->amount : -$x->amount;
            $x->running_balance = $balance;
        });

        return view('finance.transactions.cash-book', compact('rows', 'balance'));
    }

    public function bankBook(Request $r): View
    {
        abort_unless($r->user()->canAccess('bank-book'), 403);
        $accounts = collect(['Centenary Bank - Sales', 'Centenary Bank - Operations', 'Centenary Bank - Garden'])->merge(FinancialTransaction::where('payment_method', 'bank')->distinct()->orderBy('account')->pluck('account'))->unique()->values();
        $q = FinancialTransaction::with('recorder')->where('payment_method', 'bank');
        if ($r->filled('account')) {
            $q->where('account', $r->query('account'));
        }
        $q->when($r->filled('from'), fn($query)=>$query->whereDate('transaction_date','>=',$r->query('from')))
            ->when($r->filled('to'), fn($query)=>$query->whereDate('transaction_date','<=',$r->query('to')));
        $rows = $q->orderBy('transaction_date')->orderBy('id')->get();
        $balance = 0;
        $rows->each(function ($x) use (&$balance) {
            $balance += $x->type === 'money_received' ? $x->amount : -$x->amount;
            $x->running_balance = $balance;
        });

        return view('finance.transactions.bank-book', compact('rows', 'balance', 'accounts'));
    }
}
