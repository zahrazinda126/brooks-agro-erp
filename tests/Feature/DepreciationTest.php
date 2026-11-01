<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\FixedAsset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_depreciation_posts_once_and_stops_at_cost(): void
    {
        $role = Role::updateOrCreate(['code'=>'finance-manager'], ['name'=>'Finance Manager','is_superuser'=>false,'rank'=>3,'is_active'=>true]);
        $user = User::create(['username'=>'fm-test','name'=>'Finance','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);
        FixedAsset::create(['name'=>'Feed mixer','category'=>'Plant & Machinery','purchase_date'=>'2026-01-15','purchase_cost'=>1200000,'useful_life_years'=>1,'status'=>'active','recorded_by'=>$user->id]);
        FixedAsset::create(['name'=>'Farm land','category'=>'Land','purchase_date'=>'2026-01-15','purchase_cost'=>50000000,'useful_life_years'=>50,'status'=>'active','recorded_by'=>$user->id]);

        $this->artisan('finance:post-depreciation', ['month'=>'2026-02'])->assertSuccessful();
        $this->artisan('finance:post-depreciation', ['month'=>'2026-02'])->assertSuccessful();

        $entry = FinancialTransaction::sole();
        $this->assertSame('100000.00', $entry->amount);
        $this->assertSame('Depreciation - Machinery', $entry->entry_account);
        $this->assertSame('Machinery Accumulated Depreciation', $entry->account);
        $this->assertSame('2026-02-28', $entry->transaction_date->toDateString());

        foreach (range(3, 14) as $m) $this->artisan('finance:post-depreciation', ['month'=>now()->setDate(2026, 1, 1)->addMonths($m - 1)->format('Y-m')]);
        $this->assertSame(1200000.0, (float) FinancialTransaction::sum('amount'), 'never more than cost');
    }
}
