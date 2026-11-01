<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Services\LedgerPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerPostingTest extends TestCase
{
    use RefreshDatabase;

    private function tx(array $attributes): FinancialTransaction
    {
        return new FinancialTransaction($attributes + ['transaction_date' => '2026-09-01', 'amount' => 100000]);
    }

    public function test_each_entry_type_posts_balanced_lines_to_the_right_sides(): void
    {
        $ledger = new LedgerPosting();
        $cases = [
            'purchase' => [$this->tx(['type' => 'money_paid', 'entry_account' => 'Feeds', 'account' => 'Cash on Hand']), 'Feeds', 'Cash'],
            'sale receipt' => [$this->tx(['type' => 'money_received', 'entry_account' => 'Sales Large Eggs', 'account' => 'Centenary Bank - Sales']), 'Centenary Bank UGX Account (Sales)', 'Sales Large Eggs'],
            'transfer' => [$this->tx(['type' => 'transfer', 'account' => 'Cash on Hand', 'counterparty' => 'Centenary Bank - Operations']), 'Centenary Bank UGX Account (Operations)', 'Cash'],
            'prepayment' => [$this->tx(['type' => 'money_received', 'transaction_category' => 'customer_prepayment', 'account' => 'Cash on Hand']), 'Cash', 'Customer Prepayments'],
            'liability journal' => [$this->tx(['type' => 'journal', 'transaction_category' => 'liabilities', 'entry_account' => 'Loans', 'counterparty' => 'Cash on Hand']), 'Cash', 'Loans'],
        ];
        foreach ($cases as $case => [$transaction, $debit, $credit]) {
            [$dr, $cr] = $ledger->lines($transaction);
            $this->assertSame([$debit, 100000.0], [$dr['account'], $dr['debit']], $case.' debit');
            $this->assertSame([$credit, 100000.0], [$cr['account'], $cr['credit']], $case.' credit');
        }
    }

    public function test_reversal_swaps_sides_and_trial_balance_nets_to_zero(): void
    {
        $ledger = new LedgerPosting();
        $original = $this->tx(['type' => 'money_paid', 'entry_account' => 'Electricity', 'account' => 'Cash on Hand']);
        $reversal = $this->tx(['type' => 'money_paid', 'entry_account' => 'Electricity', 'account' => 'Cash on Hand', 'amount' => -100000]);

        [$dr] = $ledger->lines($reversal);
        $this->assertSame('Cash', $dr['account']);

        $rows = $ledger->trialBalance([$original, $reversal, $this->tx(['type' => 'money_received', 'entry_account' => 'Other Income', 'account' => 'Cash on Hand'])]);
        $this->assertEqualsWithDelta($rows->sum('debit'), $rows->sum('credit'), 0.001);
        $electricity = $rows->firstWhere('code', '6014001');
        $this->assertSame([0.0, 0.0], [$electricity['debit'], $electricity['credit']], 'reversed expense nets to nil');
        $this->assertSame(100000.0, $rows->firstWhere('code', '1022001')['debit'], 'cash holds only the income');
    }
}
