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
        \App\Models\Department::firstOrCreate(['code'=>'hr'],['name'=>'HR']);

        $this->actingAs($hr)->post(route('hr.welfare-budget.template'),['week'=>$week])
            ->assertRedirect(route('hr.welfare-budget.index',['week'=>$week]));

        $this->assertSame(7, Budget::where('category','Staff Welfare')->whereDate('period_start',$week)->count());
        // HR's budget page is now the shared weekly department draft.
        $this->actingAs($hr)->get(route('hr.welfare-budget.index',['week'=>$week]))
            ->assertRedirect(route('department-budget.index',['week'=>$week]));
        $this->actingAs($hr)->get(route('department-budget.index',['week'=>$week]))
            ->assertOk()->assertSee('HR weekly budget')->assertSee('Staff meals and refreshments')->assertSee('Submit to Finance');

        // Submitting sends the draft to Finance; editing afterwards asks for a new submission.
        $this->actingAs($hr)->post(route('department-budget.submit'),['week'=>$week])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('weekly_budget_submissions',['line_count'=>7]);
        $this->actingAs($hr)->get(route('department-budget.index',['week'=>$week]))->assertSee('Submitted to Finance')->assertDontSee('Submit again');
        $line = Budget::where('category','Staff Welfare')->first();
        $this->actingAs($hr)->put(route('department-budget.update',$line),['quantity'=>3,'unit_price'=>5000])->assertSessionHasNoErrors();
        $this->actingAs($hr)->get(route('department-budget.index',['week'=>$week]))->assertSee('Changed since submitted')->assertSee('Submit again');
        $this->actingAs($hr)->get(route('finance.budgets.index',['unit'=>'staff-welfare','week'=>$week]))
            ->assertOk()->assertSee('Staff Welfare')->assertSee('Staff meals and refreshments');
    }
}
