<?php

namespace App\Services;

use App\Models\FinanceAccount;
use App\Models\FinancialTransaction;
use Illuminate\Support\Collection;

/**
 * Double-entry view of the single-row financial_transactions table.
 *
 * Every transaction is expanded into balanced debit/credit lines on demand, so the
 * lines can never drift from the stored entries. Reversals (negative amounts) swap sides.
 */
class LedgerPosting
{
    private array $accountCache = [];

    /** @return array<int, array{account:string, code:?string, debit:float, credit:float}> */
    public function lines(FinancialTransaction $x): array
    {
        $amount = (float) $x->amount;
        $other = $x->counterparty ?: 'Unclassified';
        $entry = $x->entry_account;

        [$debit, $credit] = match (true) {
            $x->transaction_category === 'customer_prepayment' => [$x->account, 'Customer Prepayments'],
            $x->type === 'revenue_recognized' => [$x->account ?: 'Customer Prepayments', $entry ?: 'Sales Revenue'],
            $x->type === 'expense_recognized' => [$entry ?: 'Expense / '.$other, $x->account],
            $x->type === 'money_received' => [$x->account, $entry ?: 'Income / '.$other],
            $x->type === 'money_paid' => [$entry ?: 'Expense / '.$other, $x->account],
            // Money leaves the source account (account) and arrives at the destination (counterparty).
            $x->type === 'transfer' => [$x->counterparty ?: 'Destination not specified', $x->account],
            $x->type === 'journal' && $x->transaction_category === 'asset_management' => [$entry ?: $x->account, $other],
            $x->type === 'journal' && in_array($x->transaction_category, ['liabilities', 'capital_and_equity'], true) => [$other, $entry ?: $x->account],
            default => [$x->account, $other],
        };

        if ($amount < 0) {
            [$debit, $credit] = [$credit, $debit];
            $amount = abs($amount);
        }

        return [
            $this->line($debit, $amount, 0.0, $debit === $entry ? $x->finance_account_id : null),
            $this->line($credit, 0.0, $amount, $credit === $entry ? $x->finance_account_id : null),
        ];
    }

    /** Trial balance rows grouped by account, ordered by chart code, with totals. */
    public function trialBalance(iterable $transactions): Collection
    {
        return collect($transactions)
            ->flatMap(fn (FinancialTransaction $x) => $this->lines($x))
            ->groupBy(fn ($line) => $line['code'].'|'.$line['account'])
            ->map(function (Collection $group) {
                $first = $group->first();
                $debit = round($group->sum('debit'), 2);
                $credit = round($group->sum('credit'), 2);
                // Show each account's net balance on its natural side, as a trial balance does.
                $net = $debit - $credit;
                return ['label' => $first['code'] ? $first['account'].' ('.$first['code'].')' : $first['account'],
                    'code' => $first['code'], 'debit' => $net > 0 ? $net : 0.0, 'credit' => $net < 0 ? -$net : 0.0];
            })
            ->sortBy(fn ($row) => ($row['code'] ?? 'zzz').$row['label'])
            ->values();
    }

    private function line(?string $name, float $debit, float $credit, ?int $accountId): array
    {
        $name = $name ?: 'Unclassified';
        $account = $this->account($accountId ?: ChartOfAccounts::resolveId($name));

        return ['account' => $account?->name ?? $name, 'code' => $account?->code, 'debit' => $debit, 'credit' => $credit];
    }

    private function account(?int $id): ?FinanceAccount
    {
        if (! $id) return null;

        return $this->accountCache[$id] ??= FinanceAccount::find($id);
    }
}
