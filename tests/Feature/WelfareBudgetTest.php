<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelfareBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_loads_welfare_template_and_finance_combines_it(): void
    {
        Module::create(['key'=>'budget','name'=>'Budget','group'=>'Finance','sort_order'=>1,'is_worker_module'=>false,'is_active'=>true]);
        $role=Role::create(['code'=>'hr-manager','name'=>'HR Manager','is_superuser'=>true,'rank'=>20,'is_active'=>true]);
        $hr=User::create(['username'=>'hr-budget-test','name'=>'HR Manager','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);
        $week=now()->startOfWeek()->toDateString();

        $this->actingAs($hr)->post(route('hr.welfare-budget.template'),['week'=>$week])
            ->assertRedirect(route('hr.welfare-budget.index',['week'=>$week]));

        $this->assertSame(7, Budget::where('category','Staff Welfare')->whereDate('period_start',$week)->count());
        $this->actingAs($hr)->get(route('hr.welfare-budget.index',['week'=>$week]))
            ->assertOk()->assertSee('Staff meals and refreshments')->assertSee('Finance can see these lines immediately');
        $this->actingAs($hr)->get(route('finance.budgets.index',['unit'=>'staff-welfare','week'=>$week]))
            ->assertOk()->assertSee('Staff Welfare')->assertSee('Staff meals and refreshments');
    }
}
