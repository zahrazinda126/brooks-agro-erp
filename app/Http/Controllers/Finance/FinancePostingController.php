<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{FinancialTransaction, Payable, Receivable};
use App\Services\LedgerPosting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finance Posting overview: income, spending and net for the year (against the year before), what customers owe
 * and what is owed to suppliers, whether the ledger balances, income and spending per month, and recent postings.
 */
class FinancePostingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('transactions'), 403);
        $year = $request->query('year') === 'last' ? now()->subYear()->year : now()->year;
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();

        $ledger = fn () => FinancialTransaction::whereNull('reversed_at')->whereNull('reversal_of_id');
        $isIncome = fn ($q) => $q->where(fn ($x) => $x->where('type', 'revenue_recognized')->orWhere(fn ($y) => $y->where('type', 'money_received')
            ->where(fn ($z) => $z->whereNull('transaction_category')->orWhereNotIn('transaction_category', ['capital_and_equity', 'customer_prepayment', 'banking']))));
        $totals = function (Carbon $from, Carbon $to) use ($ledger, $isIncome) {
            $income = (float) $isIncome($ledger()->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]))->sum('amount');
            $spent = (float) $ledger()->where('type', 'money_paid')->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
            return ['income' => $income, 'spending' => $spent, 'net' => $income - $spent];
        };
        $now = $totals($start, $end);
        $before = $totals($start->copy()->subYear(), $end->copy()->subYear());
        $change = fn ($a, $b) => $b != 0.0 ? ($a - $b) / abs($b) * 100 : null;

        $months = collect(range(1, 12))->map(function ($m) use ($year, $totals) {
            $from = Carbon::create($year, $m, 1);
            $t = $from->isFuture() ? ['income' => 0.0, 'spending' => 0.0] : $totals($from, $from->copy()->endOfMonth());
            return ['label' => $from->format('M'), 'income' => $t['income'], 'spending' => $t['spending']];
        });

        $owedByCustomers = (float) Receivable::where('status', 'open')->get()->sum(fn ($r) => max(0, (float) $r->amount - (float) $r->amount_paid));
        $owedToSuppliers = (float) Payable::where('status', 'open')->get()->sum(fn ($p) => max(0, (float) $p->amount - (float) $p->amount_paid));
        $trial = app(LedgerPosting::class)->trialBalance($ledger()->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->get());
        $difference = round((float) $trial->sum('debit') - (float) $trial->sum('credit'), 2);

        $q = trim((string) $request->query('q'));
        $type = in_array($request->query('type'), ['income', 'expense', 'transfer', 'capital'], true) ? $request->query('type') : null;
        $postings = $ledger()->with('recorder')->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->when($type === 'income', fn ($x) => $isIncome($x))
            ->when($type === 'expense', fn ($x) => $x->where('type', 'money_paid'))
            ->when($type === 'transfer', fn ($x) => $x->where('type', 'transfer'))
            ->when($type === 'capital', fn ($x) => $x->where('transaction_category', 'capital_and_equity'))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('description', 'ilike', "%{$q}%")->orWhere('reference', 'ilike', "%{$q}%")->orWhere('counterparty', 'ilike', "%{$q}%")))
            ->orderByDesc('transaction_date')->orderByDesc('id')->paginate(5, ['*'], 'ppage')->withQueryString();

        return view('finance.posting.index', [
            'year' => $year, 'isLastYear' => $year !== now()->year,
            'income' => $now['income'], 'spending' => $now['spending'], 'net' => $now['net'],
            'incomeChange' => $change($now['income'], $before['income']), 'spendingChange' => $change($now['spending'], $before['spending']), 'netChange' => $change($now['net'], $before['net']),
            'owedByCustomers' => $owedByCustomers, 'owedToSuppliers' => $owedToSuppliers,
            'balanced' => abs($difference) < 1, 'difference' => $difference,
            'months' => $months, 'postings' => $postings, 'q' => $q, 'type' => $type,
        ]);
    }

    /** Which part of the system a posting came from, for the postings table. */
    public static function moduleOf(FinancialTransaction $t): string
    {
        $text = mb_strtolower((string) $t->description);
        return match (true) {
            str_starts_with($text, 'sales receipt') => 'Sales',
            $t->type === 'transfer' => 'Banking',
            $t->transaction_category === 'capital_and_equity' => 'Capital',
            $t->transaction_category === 'customer_prepayment' || str_contains($text, 'reducing balance') => 'Receivables',
            str_contains($text, 'payment of bill') => 'Payables',
            $t->transaction_category === 'batch_prepayment' => 'Batch orders',
            $t->transaction_category === 'staff_payment' || str_contains($text, 'salary') || str_contains($text, 'wage') => 'Payroll',
            $t->transaction_category === 'purchase' => 'Purchases',
            $t->type === 'money_paid' => 'Expenses',
            default => 'Finance',
        };
    }
}
