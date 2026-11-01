<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionReversalTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        Module::create(['key'=>'transactions','name'=>'Transactions','group'=>'Finance','sort_order'=>1,'is_worker_module'=>false,'is_active'=>true]);
        $role = Role::create(['code'=>'director','name'=>'Director','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        return User::create(['username'=>'finance-test','name'=>'Finance Test','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);
    }

    private function entry(User $user, array $overrides = []): FinancialTransaction
    {
        return FinancialTransaction::create($overrides + ['transaction_date'=>'2026-09-10','type'=>'money_paid','transaction_category'=>'purchase','entry_account'=>'Electricity','account'=>'Cash on Hand','payment_method'=>'cash','amount'=>150000,'reference'=>'E-9','description'=>'Power bill','recorded_by'=>$user->id,'source'=>'finance_entry']);
    }

    public function test_reversal_adds_an_opposite_entry_and_keeps_the_original(): void
    {
        $user = $this->finance();
        $original = $this->entry($user);

        $this->actingAs($user)->post(route('finance.transactions.reverse', $original), ['reversal_reason'=>'Entered twice'])
            ->assertRedirect(route('finance.transactions.index'));

        $original->refresh();
        $reversal = $original->reversal;
        $this->assertNotNull($original->reversed_at);
        $this->assertSame('Entered twice', $original->reversal_reason);
        $this->assertSame('-150000.00', $reversal->amount);
        $this->assertSame('REV-E-9', $reversal->reference);
        $this->assertSame(0.0, (float) FinancialTransaction::sum('amount'));
        $this->assertFalse($original->isReversible(), 'an entry cannot be reversed twice');
        $this->assertFalse($reversal->isReversible(), 'a reversal cannot itself be reversed');
    }

    public function test_entries_posted_by_other_modules_are_protected(): void
    {
        $user = $this->finance();
        $linked = $this->entry($user, ['source'=>'finance_entry_linked']);

        $this->actingAs($user)->post(route('finance.transactions.reverse', $linked), ['reversal_reason'=>'Mistake'])->assertSessionHasErrors('reversal_reason');
        $this->assertSame(1, FinancialTransaction::count());
    }

    public function test_an_entry_in_a_closed_period_can_be_reversed_into_an_open_one(): void
    {
        $user = $this->finance();
        $original = $this->entry($user, ['transaction_date'=>'2026-07-10']);
        DB::table('finance_periods')->insert(['name'=>'July 2026','starts_on'=>'2026-07-01','ends_on'=>'2026-07-31','status'=>'closed','created_at'=>now(),'updated_at'=>now()]);

        $this->actingAs($user)->post(route('finance.transactions.reverse', $original), ['reversal_reason'=>'Wrong month','reversal_date'=>'2026-09-01'])->assertSessionHasNoErrors();

        $this->assertNotNull($original->fresh()->reversed_at);
        $this->assertSame('2026-09-01', $original->reversal->transaction_date->toDateString());
    }
}
