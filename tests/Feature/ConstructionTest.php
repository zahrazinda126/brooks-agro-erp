<?php

namespace Tests\Feature;

use App\Models\{Employee, FinancialTransaction, Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_project_store_issue_payment_and_payroll(): void
    {
        foreach (['hr' => 'Employees', 'payroll' => 'Payroll', 'inventory' => 'Inventory'] as $key => $name) {
            Module::create(['key' => $key, 'name' => $name, 'group' => 'Admin', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        }
        $role = fn (string $code) => Role::updateOrCreate(['code' => $code], ['name' => $code, 'is_superuser' => true, 'rank' => 5, 'is_active' => true]);
        $user = fn (Role $r, string $name) => User::create(['username' => $name, 'name' => $name, 'password' => 'password', 'role_id' => $r->id, 'is_active' => true, 'must_change_password' => false]);
        $hr = $user($role('hr-manager'), 'hr');
        $pa = $user($role('personal-assistant'), 'pa');
        $storeRole = $role('store-keeper');
        $store = $user($storeRole, 'store');
        $buildingStore = DB::table('inventory_stores')->where('code', 'building')->value('id');
        $this->assertNotNull($buildingStore, 'Building Materials Store exists');
        DB::table('inventory_store_role')->insert(['inventory_store_id' => $buildingStore, 'role_id' => $storeRole->id, 'can_view' => 1, 'can_receive' => 1, 'can_issue' => 1, 'can_adjust' => 0, 'can_view_value' => 1, 'created_at' => now(), 'updated_at' => now()]);

        // HR registers a builder who is not paid through payroll, and gives him a room.
        $base = ['last_name' => 'Builder', 'employment_type' => 'temporary', 'status' => 'active', 'base_salary' => 0, 'pay_frequency' => 'monthly'];
        $this->actingAs($hr)->post(route('employees.store'), ['first_name' => 'Musa', 'phone' => '0700000201', 'on_payroll' => 0] + $base)->assertSessionHasNoErrors();
        $this->actingAs($hr)->post(route('employees.store'), ['first_name' => 'Staff', 'phone' => '0700000202', 'base_salary' => 300000] + $base)->assertSessionHasNoErrors();
        $builder = Employee::where('first_name', 'Musa')->firstOrFail();
        $this->assertFalse((bool) $builder->on_payroll);
        $this->assertTrue((bool) Employee::where('first_name', 'Staff')->value('on_payroll'), 'on payroll by default');
        $hostel = DB::table('staff_hostels')->insertGetId(['name' => 'Workers block', 'created_at' => now(), 'updated_at' => now()]);
        $room = DB::table('staff_hostel_rooms')->insertGetId(['staff_hostel_id' => $hostel, 'room_number' => '3', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('staff_accommodation_allocations')->insert(['staff_hostel_room_id' => $room, 'employee_id' => $builder->id, 'checked_in_on' => today(), 'recorded_by' => $hr->id, 'created_at' => now(), 'updated_at' => now()]);

        // The PA creates the project and adds the builder.
        $this->actingAs($pa)->post(route('construction.store'), ['name' => 'Poultry house 3', 'starts_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $project = DB::table('construction_projects')->value('id');
        $this->actingAs($pa)->post(route('construction.workers.store', $project), ['person' => 'existing', 'employee_id' => $builder->id, 'role' => 'lead', 'pay_basis' => 'job', 'agreed_amount' => 500000])->assertSessionHasNoErrors();
        // The PA registers a helper on the spot: temporary, no payroll, paid by the lead; HR is asked for a room.
        $this->actingAs($pa)->post(route('construction.workers.store', $project), ['person' => 'new', 'new_first_name' => 'Peter', 'new_last_name' => 'Helper', 'new_phone' => '0700000301',
            'new_role' => 'Helper', 'new_arrived_on' => today()->toDateString(), 'role' => 'helper'])->assertSessionHasNoErrors();
        $helper = Employee::where('first_name', 'Peter')->firstOrFail();
        $this->assertSame('temporary', $helper->employment_type);
        $this->assertFalse((bool) $helper->on_payroll);
        $this->assertSame('by_lead', DB::table('construction_project_workers')->where('employee_id', $helper->id)->value('pay_basis'));
        $this->actingAs($pa)->post(route('construction.materials.store', $project), ['item' => 'Cement', 'quantity' => 20, 'unit' => 'bags', 'estimated_cost' => 700000])->assertSessionHasNoErrors();

        // A project can be created with its lead constructor in one go; a lead with missing details saves nothing.
        $before = DB::table('construction_projects')->count();
        $this->actingAs($pa)->post(route('construction.store'), ['name' => 'Goat shed', 'starts_on' => today()->toDateString(), 'person' => 'new', 'pay_basis' => 'job', 'agreed_amount' => 800000])
            ->assertSessionHasErrors('new_first_name');
        $this->assertSame($before, DB::table('construction_projects')->count());
        $this->actingAs($pa)->post(route('construction.store'), ['name' => 'Goat shed', 'starts_on' => today()->toDateString(), 'person' => 'new', 'new_first_name' => 'Ali', 'new_last_name' => 'Mason',
            'new_phone' => '0700000303', 'new_role' => 'Lead constructor', 'new_arrived_on' => today()->toDateString(), 'pay_basis' => 'job', 'agreed_amount' => 800000])->assertSessionHasNoErrors();
        $shed = DB::table('construction_projects')->where('name', 'Goat shed')->value('id');
        $lead = DB::table('construction_project_workers')->where('construction_project_id', $shed)->first();
        $this->assertSame(['lead', 'job', 800000.0], [$lead->role, $lead->pay_basis, (float) $lead->agreed_amount]);

        // HR sees him on the farm, as a builder.
        $this->actingAs($hr)->get(route('hr.on-farm'))->assertOk()->assertSee('Musa Builder')->assertSee('Builder · Poultry house 3')->assertSee('Paid by Finance');

        // The Store Manager receives cement into the Building Materials Store and issues it to the project.
        $item = DB::table('inventory_items')->insertGetId(['inventory_store_id' => $buildingStore, 'code' => 'CEMENT', 'name' => 'Cement', 'category' => 'building', 'unit' => 'bags', 'default_location' => 'Main Store', 'created_by' => $store->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_movements')->insert(['inventory_item_id' => $item, 'inventory_store_id' => $buildingStore, 'movement_date' => today(), 'movement_type' => 'received', 'receipt_status' => 'confirmed', 'location' => 'Main Store', 'quantity' => 30, 'unit_cost' => 35000, 'total_cost' => 1050000, 'recorded_by' => $store->id, 'created_at' => now(), 'updated_at' => now()]);
        $issue = ['inventory_item_id' => $item, 'movement_date' => today()->toDateString(), 'movement_type' => 'issued', 'location' => 'Main Store', 'quantity' => 10, 'unit_cost' => 35000];
        $this->actingAs($store)->post(route('inventory.movements.store'), $issue)->assertSessionHasErrors('construction_project_id');
        $this->actingAs($store)->post(route('inventory.movements.store'), $issue + ['construction_project_id' => $project])->assertSessionHasNoErrors();

        // Finance pays the builder through its entry point, choosing the project.
        FinancialTransaction::create(['transaction_date' => today(), 'type' => 'expense', 'transaction_category' => 'expense', 'account_group' => 'Operating Expenses', 'entry_account' => 'Casual Labour', 'account' => 'Cash on Hand',
            'amount' => 200000, 'description' => 'Builder advance', 'reference' => 'PV-1', 'construction_project_id' => $project, 'construction_worker_id' => $builder->id, 'recorded_by' => $hr->id]);

        // The project shows the builder's pay, the cement issued and the payment.
        $this->actingAs($pa)->get(route('construction.show', $project))->assertOk()->assertSee('Musa Builder')->assertSee('Lead constructor')->assertSee('300,000 left to pay')->assertSee('Paid by the lead');

        // Materials bought on credit: the supplier bill shows on the project as still owed.
        DB::table('payables')->insert(['supplier_name' => 'Hardware Ltd', 'invoice_date' => today(), 'description' => 'Iron sheets', 'amount' => 900000, 'amount_paid' => 300000, 'status' => 'open',
            'recorded_by' => $hr->id, 'construction_project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($pa)->get(route('construction.show', [$project, 'tab' => 'payments']))->assertSee('Supplier bills (bought on credit)')->assertSee('600,000');

        // HR's Temporary Workers page: the helper is waiting for a room; HR assigns one, and later checks him out.
        $this->actingAs($hr)->get(route('hr.temporary.index'))->assertOk()->assertSee('Peter Helper')->assertSee('No room yet');
        $this->actingAs($hr)->post(route('hr.temporary.room', $helper), ['staff_hostel_room_id' => $room])->assertStatus(422); // Musa's room is full
        $room2 = DB::table('staff_hostel_rooms')->insertGetId(['staff_hostel_id' => $hostel, 'room_number' => '4', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($hr)->post(route('hr.temporary.room', $helper), ['staff_hostel_room_id' => $room2])->assertSessionHasNoErrors();
        $this->actingAs($hr)->post(route('hr.temporary.checkout', $helper))->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('staff_accommodation_allocations')->where('employee_id', $helper->id)->value('checked_out_on'));
        $this->actingAs($hr)->post(route('hr.temporary.store'), ['first_name' => 'Joan', 'last_name' => 'Casual', 'phone' => '0700000302', 'role' => 'Casual worker', 'arrived_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertFalse((bool) Employee::where('first_name', 'Joan')->value('on_payroll'));
        $this->actingAs($pa)->get(route('construction.show', [$project, 'tab' => 'materials']))->assertOk()->assertSee('Cement')->assertSee('350,000');
        $this->actingAs($pa)->get(route('construction.show', [$project, 'tab' => 'payments']))->assertOk()->assertSee('Builder advance')->assertSee('200,000');

        // Payroll leaves the builder out.
        $this->actingAs($hr)->post(route('payroll.generate'), ['frequency' => 'monthly', 'period_start' => today()->startOfMonth()->toDateString(), 'period_end' => today()->endOfMonth()->toDateString()]);
        $paid = DB::table('payroll_lines')->pluck('employee_id');
        $this->assertContains(Employee::where('first_name', 'Staff')->value('id'), $paid->all());
        $this->assertNotContains($builder->id, $paid->all());
    }
}
