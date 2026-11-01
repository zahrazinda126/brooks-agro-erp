<?php

namespace Tests\Feature;

use App\Models\{Budget, Department, Module, Role, User};
use App\Services\WeeklyBudgetDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionUnitBudgetDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_production_unit_gets_its_own_starting_draft_once(): void
    {
        Module::create(['key' => 'budget', 'name' => 'Budget', 'group' => 'Finance', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $department = Department::firstOrCreate(['code' => 'production'], ['name' => 'Production']);
        $role = Role::create(['code' => 'production-manager', 'name' => 'Production Manager', 'is_superuser' => true, 'rank' => 3, 'is_active' => true]);
        $manager = User::create(['username' => 'pm-budget', 'name' => 'Production', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);

        $this->actingAs($manager)->get(route('department-budget.index'))->assertOk()
            ->assertSee('Production weekly budget')->assertSee('Layers mash')->assertSee('Dairy meal / concentrates')->assertSee('Passion fruit seedlings');

        foreach (WeeklyBudgetDraftService::UNIT_STARTER_LINES as $unit => $lines) {
            $this->assertSame(count($lines), Budget::where('department_id', $department->id)->where('category', $unit)->count(), $unit);
        }

        // A unit view shows only that unit; a removed starter line is not added back.
        $this->actingAs($manager)->get(route('department-budget.index', ['section' => 'Dogs Unit']))->assertSee('Dog food')->assertDontSee('Layers mash');
        $line = Budget::where('item', 'Egg trays')->firstOrFail();
        $this->actingAs($manager)->delete(route('department-budget.destroy', $line))->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('department-budget.index'))->assertOk();
        $this->assertFalse(Budget::where('item', 'Egg trays')->exists());
    }
}
