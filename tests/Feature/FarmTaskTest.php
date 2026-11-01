<?php

namespace Tests\Feature;

use App\Models\{Employee, Module, Role, User};
use App\Services\SellableStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FarmTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_manager_assigns_worker_records_supervisor_confirms_and_manure_reaches_sales(): void
    {
        foreach (['batches' => 'Poultry', 'sales' => 'Sales'] as $key => $name) {
            Module::create(['key' => $key, 'name' => $name, 'group' => 'Farm', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        }
        $pmRole = Role::create(['code' => 'production-manager', 'name' => 'Production Manager', 'rank' => 3, 'is_active' => true]);
        $workerRole = Role::create(['code' => 'casual-worker', 'name' => 'Farm Worker', 'rank' => 50, 'is_active' => true]);
        $pmEmployee = Employee::create(['staff_no' => 'BAF 101', 'first_name' => 'Henry', 'last_name' => 'T', 'phone' => '0700000101', 'employment_type' => 'permanent', 'status' => 'active', 'base_salary' => 0, 'pay_frequency' => 'monthly']);
        $workerEmployee = Employee::create(['staff_no' => 'BAF 102', 'first_name' => 'Amos', 'last_name' => 'K', 'phone' => '0700000102', 'employment_type' => 'casual', 'status' => 'active', 'base_salary' => 0, 'pay_frequency' => 'daily']);
        $pm = User::create(['username' => 'x', 'name' => 'Henry T', 'password' => 'password', 'role_id' => $pmRole->id, 'employee_id' => $pmEmployee->id, 'is_active' => true, 'must_change_password' => false]);
        $worker = User::create(['username' => 'y', 'name' => 'Amos K', 'password' => 'password', 'role_id' => $workerRole->id, 'employee_id' => $workerEmployee->id, 'is_active' => true, 'must_change_password' => false]);

        $this->assertNotNull(DB::table('farm_task_types')->where('name', 'Poultry manure collection')->value('sale_product_id'), 'manure is linked to its sale product');
        $manure = DB::table('farm_task_types')->where('name', 'Poultry manure collection')->value('id');
        $spraying = DB::table('farm_task_types')->where('name', 'Spraying')->value('id');

        // The manager is not offered as a worker; the worker is.
        $this->actingAs($pm)->get(route('farm-tasks.index'))->assertOk()->assertSee('Amos K');
        $this->actingAs($pm)->post(route('farm-tasks.assign'), ['farm_task_type_id' => $manure, 'user_id' => $pm->id, 'unit_code' => 'poultry', 'starts_on' => today()->toDateString()])
            ->assertSessionHasErrors('user_id');

        // Assign manure and spraying to the worker.
        foreach ([$manure, $spraying] as $type) {
            $this->actingAs($pm)->post(route('farm-tasks.assign'), ['farm_task_type_id' => $type, 'user_id' => $worker->id, 'unit_code' => 'poultry', 'starts_on' => today()->toDateString()])
                ->assertSessionHasNoErrors();
        }
        [$manureTask, $sprayTask] = DB::table('farm_task_assignments')->orderBy('id')->pluck('id')->all();

        // The buttons appear in the worker's account; the worker records the work.
        $this->actingAs($worker)->get(route('daily.index'))->assertOk()->assertSee('My tasks')->assertSee('Poultry manure collection')->assertSee('Spraying');
        $this->actingAs($worker)->post(route('my-tasks.store', $manureTask), ['done_on' => today()->toDateString()])->assertSessionHasErrors('quantity');
        $this->actingAs($worker)->post(route('my-tasks.store', $manureTask), ['done_on' => today()->toDateString(), 'quantity' => 40])->assertRedirect(route('daily.index'));
        $this->actingAs($worker)->post(route('my-tasks.store', $sprayTask), ['done_on' => today()->toDateString()])->assertSessionHasErrors('details');
        $this->actingAs($worker)->post(route('my-tasks.store', $sprayTask), ['done_on' => today()->toDateString(), 'details' => 'Sprayed houses 1-4'])->assertSessionHasNoErrors();

        // Not in the inventory until a supervisor confirms it.
        $stock = fn () => (new SellableStock)->onHand(today())->keyBy('item');
        $this->assertArrayNotHasKey('Poultry manure', $stock()->all());
        $entry = DB::table('farm_task_entries')->where('task_name', 'Poultry manure collection')->value('id');
        $this->actingAs($pm)->post(route('supervision.records.review', ['task', $entry]), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(40.0, $stock()['Poultry manure']['quantity']);
        $this->assertCount(1, $stock()->filter(fn ($row) => str_contains($row['item'], 'manure')), 'spraying adds nothing for sale');

        // Ending the task removes it from the worker's account.
        $this->actingAs($pm)->post(route('farm-tasks.end', $manureTask))->assertSessionHasNoErrors();
        $this->actingAs($worker)->get(route('my-tasks.record', $manureTask))->assertForbidden();
        $this->actingAs($worker)->get(route('daily.index'))->assertDontSee(route('my-tasks.record', $manureTask), false)->assertSee(route('my-tasks.record', $sprayTask), false);
    }
}
