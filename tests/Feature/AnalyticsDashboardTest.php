<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string $code): User
    {
        $role = Role::updateOrCreate(['code' => $code], ['name' => $code, 'is_superuser' => false, 'rank' => 5, 'is_active' => true]);
        return User::create(['username' => $code.'-dash', 'name' => ucfirst($code), 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
    }

    public function test_each_role_gets_its_own_dashboard_limited_to_what_it_may_see(): void
    {
        $admin = $this->userWith('system-admin');
        DB::table('sales')->insert(['invoice_number' => 'T-1', 'sale_date' => today(), 'customer_name' => 'Kampala Fresh Mart', 'payment_type' => 'cash', 'payment_method' => 'cash',
            'subtotal' => 500000, 'discount' => 0, 'total' => 500000, 'amount_paid' => 500000, 'balance' => 0, 'status' => 'completed', 'approval_status' => 'approved',
            'is_staff_consumption' => false, 'sold_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);

        // CEO: business performance, with profit and top customers.
        $this->actingAs($this->userWith('ceo'))->get(route('dashboard'))->assertOk()
            ->assertSee('Business performance')->assertSee('Profit')->assertSee('Top 5 customers')->assertSee('Kampala Fresh Mart')->assertSee('Sales monthly trend');

        // Director is not the CEO: oversight, with spending.
        $this->actingAs($this->userWith('director'))->get(route('dashboard'))->assertOk()
            ->assertSee('Oversight')->assertSee('Spending monthly trend')->assertDontSee('Business performance');

        // Sales sees sales, never profit or spending.
        $this->actingAs($this->userWith('sales-manager'))->get(route('dashboard'))->assertOk()
            ->assertSee('Number of sales')->assertSee('Sales by payment method')->assertDontSee('>Profit<', false)->assertDontSee('Spending');

        // Finance has its own dashboard (Finance Dashboard design): filters by month and by quarter.
        $this->actingAs($this->userWith('finance-manager'))->get(route('dashboard', ['month' => today()->month]))->assertOk()
            ->assertSee('Finance Dashboard')->assertSee('Total Income')->assertSee(today()->startOfMonth()->format('d M Y'))->assertDontSee('Money in and out');
        $this->actingAs(User::where('username', 'finance-manager-dash')->first())->get(route('dashboard', ['quarter' => today()->quarter]))->assertOk()
            ->assertSee(today()->startOfQuarter()->format('d M Y'))->assertSee('Top 5 Spending Categories');
    }
}
