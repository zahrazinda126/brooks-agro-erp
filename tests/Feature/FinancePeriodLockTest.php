<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancePeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $status): void
    {
        DB::table('finance_periods')->insert(['name'=>'July 2026','starts_on'=>'2026-07-01','ends_on'=>'2026-07-31','status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function entry(string $date): array
    {
        $user = User::first() ?: User::create(['username'=>'finance-test','name'=>'Finance Test','password'=>'password','is_active'=>true,'must_change_password'=>false]);
        return ['recorded_by'=>$user->id,'transaction_date'=>$date,'type'=>'money_paid','account'=>'Cash on Hand','amount'=>50000,'reference'=>'T-1','description'=>'Test entry'];
    }

    public function test_entries_in_a_closed_period_are_rejected(): void
    {
        $this->period('closed');

        $this->expectException(ValidationException::class);
        FinancialTransaction::create($this->entry('2026-07-15'));
    }

    public function test_entries_in_open_or_unlisted_periods_are_accepted(): void
    {
        $this->period('open');

        FinancialTransaction::create($this->entry('2026-07-15'));
        FinancialTransaction::create($this->entry('2026-08-02'));

        $this->assertSame(2, FinancialTransaction::count());
    }

    public function test_existing_entries_cannot_be_moved_out_of_a_closed_period(): void
    {
        $transaction = FinancialTransaction::create($this->entry('2026-07-15'));
        $this->period('closed');

        $this->expectException(ValidationException::class);
        $transaction->update(['transaction_date'=>'2026-08-02']);
    }
}
