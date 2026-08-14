<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetFundingDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function workflow(): array
    {
        Module::create(['key'=>'approvals','name'=>'Approvals','group'=>'Operations','sort_order'=>1,'is_worker_module'=>false,'is_active'=>true]);
        Module::create(['key'=>'budget','name'=>'Budget','group'=>'Finance','sort_order'=>2,'is_worker_module'=>false,'is_active'=>true]);
        $ceoRole = Role::create(['code'=>'ceo','name'=>'CEO','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        $ceo = User::create(['username'=>'ceo-test','name'=>'CEO Test','password'=>'password','role_id'=>$ceoRole->id,'is_active'=>true,'must_change_password'=>false]);
        $submitter = User::create(['username'=>'finance-test','name'=>'Finance Test','password'=>'password','is_active'=>true,'must_change_password'=>false]);
        $budget = Budget::create(['category'=>'Poultry','item'=>'Feeds','unit'=>'bags','quantity'=>10,'unit_price'=>100000,'planned_amount'=>1000000,'period_start'=>'2026-08-10','period_end'=>'2026-08-16','status'=>'submitted','recorded_by'=>$submitter->id]);
        $approval = Approval::create(['approvable_type'=>Budget::class,'approvable_id'=>$budget->id,'module_key'=>'budget','submitted_by'=>$submitter->id,'submitted_at'=>now(),'status'=>'pending','level'=>2,'required_levels'=>3]);
        $approval->steps()->createMany([
            ['stage'=>1,'role_code'=>'finance-manager','status'=>'approved','decided_by'=>$submitter->id,'decided_at'=>now()],
            ['stage'=>2,'role_code'=>'ceo','status'=>'pending'],
            ['stage'=>3,'role_code'=>'director','status'=>'waiting'],
        ]);

        return compact('ceo', 'budget', 'approval');
    }

    public function test_ceo_can_mark_a_budget_line_unfunded_with_an_audit_record(): void
    {
        ['ceo'=>$ceo, 'budget'=>$budget, 'approval'=>$approval] = $this->workflow();

        $this->actingAs($ceo)->post(route('approvals.budget-funding.update', $approval), [
            'lines'=>[$budget->id=>['decision'=>'unfunded','note'=>'Cash is not yet available']],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('budgets', ['id'=>$budget->id,'funding_status'=>'unfunded','funded_amount'=>0]);
        $this->assertDatabaseHas('budget_funding_decisions', ['budget_id'=>$budget->id,'approval_id'=>$approval->id,'decision'=>'unfunded','decided_by'=>$ceo->id]);
    }

    public function test_ceo_can_render_budget_approval_details(): void
    {
        ['ceo'=>$ceo, 'approval'=>$approval] = $this->workflow();

        $this->actingAs($ceo)->get(route('approvals.show',$approval))
            ->assertOk()->assertSee('Detailed unit budgets')->assertSee('Poultry')
            ->assertSee('Feeds')->assertSee('Quantity')->assertSee('Unit price')
            ->assertSee('Save funding exceptions');
    }

    public function test_cancelled_or_unfunded_lines_require_a_reason(): void
    {
        ['ceo'=>$ceo, 'budget'=>$budget, 'approval'=>$approval] = $this->workflow();

        $this->actingAs($ceo)->from(route('approvals.show',$approval))->post(route('approvals.budget-funding.update', $approval), [
            'lines'=>[$budget->id=>['decision'=>'cancelled','note'=>'']],
        ])->assertRedirect(route('approvals.show',$approval))->assertSessionHasErrors("lines.{$budget->id}.note");

        $this->assertDatabaseHas('budgets', ['id'=>$budget->id,'funding_status'=>'pending']);
    }

    public function test_ceo_approval_automatically_funds_undecided_lines(): void
    {
        ['ceo'=>$ceo, 'approval'=>$approval] = $this->workflow();

        $this->actingAs($ceo)->from(route('approvals.show',$approval))->post(route('approvals.decide',$approval), [
            'decision'=>'approved', 'decision_note'=>'Proceed',
        ])->assertRedirect(route('approvals.show',$approval))->assertSessionHas('status');

        $this->assertDatabaseHas('budgets', ['id'=>$approval->approvable_id,'funding_status'=>'funded','funded_amount'=>1000000]);
        $this->assertDatabaseHas('budget_funding_decisions', ['budget_id'=>$approval->approvable_id,'approval_id'=>$approval->id,'decision'=>'funded','decided_by'=>$ceo->id]);
        $this->assertDatabaseHas('approvals', ['id'=>$approval->id,'status'=>'pending','level'=>3]);
    }

    public function test_director_can_cancel_a_line_after_final_approval(): void
    {
        ['budget'=>$budget, 'approval'=>$approval] = $this->workflow();
        $directorRole = Role::create(['code'=>'director','name'=>'Director','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        $director = User::create(['username'=>'director-test','name'=>'Director Test','password'=>'password','role_id'=>$directorRole->id,'is_active'=>true,'must_change_password'=>false]);
        $approval->steps()->update(['status'=>'approved']);
        $approval->update(['status'=>'approved','level'=>3,'decided_by'=>$director->id,'decided_at'=>now()]);

        $this->actingAs($director)->post(route('approvals.budget-funding.update', $approval), [
            'lines'=>[$budget->id=>['decision'=>'cancelled','note'=>'No longer operationally required']],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('budgets', ['id'=>$budget->id,'funding_status'=>'cancelled','funded_amount'=>0]);
        $this->assertDatabaseHas('budget_funding_decisions', ['budget_id'=>$budget->id,'decision'=>'cancelled','decided_by'=>$director->id]);
    }

    public function test_finance_manager_submission_goes_directly_to_ceo_queue(): void
    {
        Module::create(['key'=>'approvals','name'=>'Approvals','group'=>'Operations','sort_order'=>1,'is_worker_module'=>false,'is_active'=>true]);
        Module::create(['key'=>'budget','name'=>'Budget','group'=>'Finance','sort_order'=>2,'is_worker_module'=>false,'is_active'=>true]);
        $role = Role::create(['code'=>'finance-manager','name'=>'Finance Manager','is_superuser'=>true,'rank'=>3,'is_active'=>true]);
        $manager = User::create(['username'=>'finance-manager-test','name'=>'Finance Manager','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);
        $budget = Budget::create(['category'=>'Poultry','item'=>'Feeds','unit'=>'bags','quantity'=>2,'unit_price'=>100000,'planned_amount'=>200000,'period_start'=>now()->startOfWeek()->toDateString(),'period_end'=>now()->endOfWeek()->toDateString(),'status'=>'draft','recorded_by'=>$manager->id]);

        $this->actingAs($manager)->post(route('finance.budgets.submit'), ['week'=>$budget->period_start->toDateString()])
            ->assertRedirect()->assertSessionHas('status','Weekly budget sent to the CEO for review.');

        $approval = Approval::where('module_key','budget')->latest('id')->firstOrFail();
        $this->assertSame(2, $approval->level);
        $this->assertDatabaseHas('approval_steps', ['approval_id'=>$approval->id,'stage'=>1,'role_code'=>'finance-manager','status'=>'approved','decided_by'=>$manager->id]);
        $this->assertDatabaseHas('approval_steps', ['approval_id'=>$approval->id,'stage'=>2,'role_code'=>'ceo','status'=>'pending']);
    }
}
