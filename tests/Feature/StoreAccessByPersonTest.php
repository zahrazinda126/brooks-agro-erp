<?php

namespace Tests\Feature;

use App\Http\Controllers\InventoryController;
use App\Models\InventoryStore;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreAccessByPersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_store_manager_can_be_given_specific_stores_and_returned_to_role_access(): void
    {
        $keeper = Role::updateOrCreate(['code' => 'store-keeper'], ['name' => 'Store Keeper', 'is_superuser' => false, 'rank' => 30, 'is_active' => true]);
        $director = Role::updateOrCreate(['code' => 'director'], ['name' => 'Director', 'is_superuser' => false, 'rank' => 1, 'is_active' => true]);
        $stores = collect(['Feed Store', 'Veterinary Store', 'Kitchen & Food Store'])->map(fn ($name, $i) => InventoryStore::create(['code' => 'test-'.$i, 'name' => $name, 'section' => 'Test', 'is_active' => true]));
        foreach ($stores as $store) DB::table('inventory_store_role')->insert(['inventory_store_id' => $store->id, 'role_id' => $keeper->id, 'can_view' => true, 'can_receive' => true, 'can_issue' => true, 'can_adjust' => false, 'can_view_value' => false, 'created_at' => now(), 'updated_at' => now()]);
        $sarah = User::create(['username' => 'sarah', 'name' => 'Sarah', 'password' => 'password', 'role_id' => $keeper->id, 'is_active' => true, 'must_change_password' => false]);
        $boss = User::create(['username' => 'boss', 'name' => 'Director', 'password' => 'password', 'role_id' => $director->id, 'is_active' => true, 'must_change_password' => false]);

        $this->assertCount(3, InventoryController::storesFor($sarah->fresh()), 'role access: every store');

        $this->actingAs($boss)->put(route('inventory.store-access.person', $sarah), ['stores' => [
            $stores[0]->id => ['can_view' => 1, 'can_receive' => 1, 'can_issue' => 1],
            $stores[1]->id => ['can_view' => 1, 'can_issue' => 1],
        ]])->assertSessionHasNoErrors();
        $mine = InventoryController::storesFor($sarah->fresh());
        $this->assertEqualsCanonicalizing(['Feed Store', 'Veterinary Store'], $mine->pluck('name')->all());
        $this->assertFalse($mine->firstWhere('name', 'Veterinary Store')->access['can_receive']);

        $this->actingAs($boss)->put(route('inventory.store-access.person', $sarah), ['stores' => []]);
        $this->assertCount(3, InventoryController::storesFor($sarah->fresh()), 'cleared: back to role access');

        $this->actingAs($sarah)->put(route('inventory.store-access.person', $sarah), ['stores' => []])->assertForbidden();
    }
}
