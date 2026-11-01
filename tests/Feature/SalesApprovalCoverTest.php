<?php

namespace Tests\Feature;

use App\Models\{Approval, ApprovalDelegation, Budget, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApprovalCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_cover_lets_the_delegate_approve_sales_only(): void
    {
        $ceoRole = Role::updateOrCreate(['code' => 'ceo'], ['name' => 'CEO', 'is_superuser' => false, 'rank' => 2, 'is_active' => true]);
        $staffRole = Role::updateOrCreate(['code' => 'sales-manager'], ['name' => 'Sales Manager', 'is_superuser' => false, 'rank' => 9, 'is_active' => true]);
        $ceo = User::create(['username' => 'ceo-cover', 'name' => 'CEO', 'password' => 'password', 'role_id' => $ceoRole->id, 'is_active' => true, 'must_change_password' => false]);
        $cover = User::create(['username' => 'cover-person', 'name' => 'Cover', 'password' => 'password', 'role_id' => $staffRole->id, 'is_active' => true, 'must_change_password' => false]);
        ApprovalDelegation::create(['delegator_id' => $ceo->id, 'delegate_id' => $cover->id, 'role_code' => 'ceo', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDays(5), 'is_active' => true]);

        $budget = Budget::create(['category' => 'Poultry', 'item' => 'Feed', 'quantity' => 1, 'unit_price' => 100, 'planned_amount' => 100,
            'period_start' => today()->startOfWeek(), 'period_end' => today()->endOfWeek(), 'status' => 'submitted', 'recorded_by' => $ceo->id]);
        $budgetApproval = Approval::create(['approvable_type' => Budget::class, 'approvable_id' => $budget->id, 'module_key' => 'budget', 'submitted_by' => $ceo->id, 'submitted_at' => now(), 'status' => 'pending']);
        $budgetApproval->steps()->create(['stage' => 1, 'role_code' => 'ceo', 'status' => 'pending']);
        $salesApproval = Approval::create(['approvable_type' => Budget::class, 'approvable_id' => $budget->id, 'module_key' => 'sales', 'submitted_by' => $ceo->id, 'submitted_at' => now(), 'status' => 'pending']);
        $salesApproval->steps()->create(['stage' => 1, 'role_code' => 'ceo', 'status' => 'pending']);

        // The person covering sees only the sales request.
        $list = $this->actingAs($cover)->get(route('approvals.index'))->assertOk()->viewData('approvals');
        $this->assertSame([$salesApproval->id], collect($list->items())->pluck('id')->all());

        // A budget still waits for the CEO.
        $this->actingAs($cover)->post(route('approvals.decide', $budgetApproval), ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $budgetApproval->fresh()->status);
    }
}
