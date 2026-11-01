<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\ChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function codeFor(string $name): ?string
    {
        $id = ChartOfAccounts::resolveId($name);
        return $id ? FinanceAccount::find($id)->code : null;
    }

    public function test_workbook_chart_is_loaded_with_hierarchy_and_original_spelling(): void
    {
        $feeds = FinanceAccount::where('code', '5011001')->first();
        $this->assertSame('Feeds', $feeds->name);
        $this->assertSame('5010000', $feeds->parent->code);
        $this->assertSame('Internet_or_Email', FinanceAccount::where('code', '6017001')->value('workbook_label'));
        $this->assertTrue(FinanceAccount::where('code', '1000')->exists(), 'original top-level accounts are kept');
    }

    public function test_names_resolve_despite_spelling_differences(): void
    {
        $this->assertSame('6017001', $this->codeFor('Internet / Email'));
        $this->assertSame('6020004', $this->codeFor('Legal & Licence Fees'));
        $this->assertSame('1022001', $this->codeFor('cash on hand'));
        $this->assertSame('5011001', $this->codeFor(' FEEDS '));
        $this->assertSame('4012010', $this->codeFor('Other Income'));
        $this->assertNull($this->codeFor('Not an account'));
    }

    public function test_transactions_are_linked_on_save_without_changing_their_text(): void
    {
        $user = User::create(['username'=>'finance-test','name'=>'Finance Test','password'=>'password','is_active'=>true,'must_change_password'=>false]);
        $transaction = FinancialTransaction::create(['transaction_date'=>'2026-09-01','type'=>'money_paid','entry_account'=>'Electricity','account'=>'Cash','amount'=>120000,'reference'=>'E-1','description'=>'Power bill','recorded_by'=>$user->id]);

        $this->assertSame('6014001', $transaction->financeAccount->code);
        $this->assertSame('Electricity', $transaction->fresh()->entry_account);
    }
}
