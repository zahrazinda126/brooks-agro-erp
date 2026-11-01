<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_book_carries_the_opening_balance_and_counts_transfers(): void
    {
        Module::create(['key'=>'cash-book','name'=>'Cash Book','group'=>'Finance','sort_order'=>1,'is_worker_module'=>false,'is_active'=>true]);
        $role = Role::create(['code'=>'director','name'=>'Director','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        $user = User::create(['username'=>'cash-test','name'=>'Cash','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);
        $entry = fn (array $a) => FinancialTransaction::create($a + ['reference'=>'T','description'=>'test','recorded_by'=>$user->id]);
        $entry(['transaction_date'=>'2026-08-20','type'=>'money_received','account'=>'Cash on Hand','payment_method'=>'cash','amount'=>500000]);
        $entry(['transaction_date'=>'2026-09-05','type'=>'money_paid','account'=>'Cash on Hand','payment_method'=>'cash','entry_account'=>'Feeds','amount'=>100000]);
        $entry(['transaction_date'=>'2026-09-06','type'=>'transfer','account'=>'Cash on Hand','counterparty'=>'Centenary Bank - Operations','amount'=>250000]);

        $data = $this->actingAs($user)->get('/finance/cash-book?from=2026-09-01&to=2026-09-30')->assertOk()->viewData('charts');
        $response = $this->actingAs($user)->get('/finance/cash-book?from=2026-09-01&to=2026-09-30');

        $this->assertSame(500000.0, $response->viewData('opening'), 'August cash is brought forward');
        $this->assertSame(150000.0, (float) $response->viewData('balance'), '500k - 100k feeds - 250k deposited at the bank');
        $this->assertEqualsCanonicalizing(['Feeds', 'Moved to other accounts'], array_column($data['out'], 'label'));
        $this->assertCount(1, $this->actingAs($user)->get('/finance/cash-book?from=2026-09-01&to=2026-09-30&direction=transfer')->viewData('rows'));
    }
}
