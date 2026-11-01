<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryRequest;
use App\Models\InventoryStore;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Inventory on the Farm Hub warehouse model (24 Oct 2026): requests, expiry batches, Finance-approved adjustments. */
class InventoryWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private InventoryStore $store;
    private InventoryItem $item;

    private function role(string $code, int $rank): Role
    {
        return Role::updateOrCreate(['code' => $code], ['name' => ucwords(str_replace('-', ' ', $code)), 'is_superuser' => false, 'rank' => $rank, 'is_active' => true]);
    }

    private function person(string $username, Role $role): User
    {
        return User::create(['username' => $username, 'name' => ucfirst($username), 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
    }

    private function grantStore(Role $role, array $rights): void
    {
        DB::table('inventory_store_role')->updateOrInsert(['inventory_store_id' => $this->store->id, 'role_id' => $role->id],
            array_merge(['can_view' => true, 'can_receive' => false, 'can_issue' => false, 'can_adjust' => false, 'can_view_value' => false], $rights) + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function grantModule(Role $role, bool $create): void
    {
        $module = Module::updateOrCreate(['key' => 'inventory'], ['name' => 'Inventory', 'group' => 'Stock', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        DB::table('module_role')->updateOrInsert(['role_id' => $role->id, 'module_id' => $module->id],
            ['can_view' => true, 'can_create' => $create, 'can_edit' => false, 'can_delete' => false, 'can_approve' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Three batches of a vaccine: one expired, one expiring first, one later. */
    private function stockWithBatches(): array
    {
        $this->store = InventoryStore::updateOrCreate(['code' => 'veterinary'], ['name' => 'Veterinary Store', 'section' => 'Production', 'is_active' => true]);
        $setup = $this->person('setup', $this->role('inventory-officer', 45));
        $this->item = InventoryItem::create(['created_by' => $setup->id,'inventory_store_id' => $this->store->id, 'code' => 'VAC-1', 'name' => 'Newcastle vaccine', 'category' => 'vaccine',
            'unit' => 'vial', 'reorder_level' => 2, 'default_location' => 'Veterinary Store', 'is_active' => true]);
        $batches = [];
        foreach ([['OLD', -5, 4], ['SOON', 10, 6], ['LATER', 90, 20]] as [$code, $days, $qty]) {
            $batch = InventoryBatch::create(['inventory_item_id' => $this->item->id, 'inventory_store_id' => $this->store->id, 'batch_code' => $code,
                'expiry_date' => today()->addDays($days), 'received_on' => today()->subDays(30), 'quantity_received' => $qty]);
            InventoryMovement::create(['inventory_item_id' => $this->item->id, 'inventory_store_id' => $this->store->id, 'inventory_batch_id' => $batch->id,
                'movement_date' => today()->subDays(30), 'movement_type' => 'received', 'receipt_status' => 'confirmed', 'location' => 'Veterinary Store', 'quantity' => $qty, 'recorded_by' => $setup->id]);
            $batches[$code] = $batch;
        }

        return $batches;
    }

    public function test_issues_take_the_first_batch_to_expire_and_never_expired_stock(): void
    {
        $batches = $this->stockWithBatches();

        $parts = InventoryStock::allocate($this->item, 10);
        $this->assertSame([[$batches['SOON']->id, 6.0], [$batches['LATER']->id, 4.0]],
            array_map(fn ($p) => [$p['batch_id'], (float) $p['quantity']], $parts));

        // 30 vials are on the books, but 4 are expired: only 26 can be issued.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        InventoryStock::allocate($this->item, 27);
    }

    public function test_a_request_goes_through_every_step_by_the_right_people(): void
    {
        $batches = $this->stockWithBatches();
        $vetRole = $this->role('veterinary-officer', 40);
        $pmRole = $this->role('production-manager', 20);
        $keeperRole = $this->role('store-keeper', 50);
        $this->grantStore($keeperRole, ['can_receive' => true, 'can_issue' => true]);
        $vet = $this->person('vet', $vetRole);
        $pm = $this->person('pm', $pmRole);
        $keeper = $this->person('keeper', $keeperRole);

        $this->actingAs($vet)->post(route('inventory.requests.store'), ['inventory_store_id' => $this->store->id, 'purpose' => 'Vaccinate House 4',
            'lines' => [['inventory_item_id' => $this->item->id, 'quantity' => 8]]])->assertSessionHasNoErrors();
        $req = InventoryRequest::firstOrFail();
        $this->assertSame('production-manager', $req->approver_role, 'the Production Manager approves the vet');
        $this->assertSame('requested', $req->status);

        $this->actingAs($keeper)->post(route('inventory.requests.step', [$req, 'approve']))->assertForbidden();
        $this->actingAs($pm)->post(route('inventory.requests.step', [$req, 'approve']))->assertSessionHasNoErrors();
        $this->actingAs($vet)->post(route('inventory.requests.step', [$req, 'prepare']), ['issue' => []])->assertForbidden();
        $line = $req->lines()->first();
        $this->actingAs($keeper)->post(route('inventory.requests.step', [$req, 'prepare']), ['issue' => [$line->id => 8]])->assertSessionHasNoErrors();
        $this->actingAs($keeper)->post(route('inventory.requests.step', [$req, 'dispatch']))->assertSessionHasNoErrors();

        // Stock left the store at dispatch, first to expire first: 6 from SOON, 2 from LATER.
        $this->assertSame(22.0, InventoryStock::itemBalance($this->item->id));
        $issued = InventoryMovement::where('inventory_request_id', $req->id)->pluck('quantity', 'inventory_batch_id')->map(fn ($q) => (float) $q)->all();
        $this->assertSame([$batches['SOON']->id => 6.0, $batches['LATER']->id => 2.0], $issued);

        $this->actingAs($vet)->post(route('inventory.requests.step', [$req, 'receive']), ['received' => [$line->id => 8]])->assertSessionHasNoErrors();
        $this->actingAs($keeper)->post(route('inventory.requests.step', [$req, 'acknowledge']))->assertSessionHasNoErrors();
        $this->assertSame('acknowledged', $req->fresh()->status);
    }

    public function test_adjustments_change_the_balance_only_after_finance_approves(): void
    {
        $this->stockWithBatches();
        $officerRole = $this->role('inventory-officer', 45);
        $financeRole = $this->role('finance-manager', 20);
        $this->grantStore($officerRole, ['can_receive' => true, 'can_issue' => true, 'can_adjust' => true]);
        $this->grantModule($officerRole, true);
        $officer = $this->person('officer', $officerRole);
        $finance = $this->person('finance', $financeRole);

        $this->actingAs($officer)->post(route('inventory.movements.store'), ['inventory_item_id' => $this->item->id, 'movement_date' => today()->toDateString(),
            'movement_type' => 'adjustment_out', 'location' => 'Veterinary Store', 'quantity' => 3, 'adjustment_reason' => 'count_difference'])
            ->assertSessionHasNoErrors();
        $adjustment = InventoryMovement::where('movement_type', 'adjustment_out')->firstOrFail();
        $this->assertSame('awaiting_approval', $adjustment->receipt_status);
        $this->assertSame(30.0, InventoryStock::itemBalance($this->item->id), 'not counted before approval');

        $this->actingAs($officer)->post(route('inventory.adjustments.decide', $adjustment), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($finance)->post(route('inventory.adjustments.decide', $adjustment), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(27.0, InventoryStock::itemBalance($this->item->id), 'counted once Finance approves');
    }

    public function test_a_receipt_into_an_expiry_store_needs_its_batch_and_expiry_date(): void
    {
        $this->stockWithBatches();
        $officerRole = $this->role('inventory-officer', 45);
        $this->grantStore($officerRole, ['can_receive' => true, 'can_issue' => true]);
        $this->grantModule($officerRole, true);
        $officer = $this->person('officer', $officerRole);
        $receipt = ['inventory_item_id' => $this->item->id, 'movement_date' => today()->toDateString(), 'movement_type' => 'received',
            'location' => 'Veterinary Store', 'quantity' => 5];

        $this->actingAs($officer)->post(route('inventory.movements.store'), $receipt)->assertSessionHasErrors(['batch_code', 'expiry_date']);
        $this->actingAs($officer)->post(route('inventory.movements.store'), $receipt + ['batch_code' => 'NEW-7', 'expiry_date' => today()->addYear()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('inventory_batches', ['batch_code' => 'NEW-7', 'inventory_item_id' => $this->item->id]);
    }

    public function test_the_production_manager_cannot_open_finance_hr_or_sales_reports(): void
    {
        $pm = $this->person('pm', $this->role('production-manager', 20));
        $module = Module::updateOrCreate(['key' => 'reports'], ['name' => 'Reports', 'group' => 'Reports', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        DB::table('module_role')->updateOrInsert(['role_id' => $pm->role_id, 'module_id' => $module->id], ['can_view' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertTrue($pm->fresh()->seesOnlyProductionReports());
        $this->actingAs($pm)->get(route('finance.reports.index'))->assertForbidden();
        $this->actingAs($pm)->get(route('hr.reports.index'))->assertForbidden();
        $this->actingAs($pm)->get(route('sales.index', ['screen' => 'reports']))->assertForbidden();
    }
}
