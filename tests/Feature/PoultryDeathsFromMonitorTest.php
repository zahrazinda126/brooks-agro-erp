<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, Module, MortalityRecord, PoultryHouse, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoultryDeathsFromMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_poultry_deaths_form_lists_houses_and_saves_back_to_monitor(): void
    {
        Module::create(['key' => 'mortality-entry', 'name' => 'Deaths', 'group' => 'Poultry', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'deaths-test', 'name' => 'Manager', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id, 'purpose' => 'layer', 'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 100, 'status' => 'active']);
        $house = PoultryHouse::create(['house_number' => 'Test House 99', 'house_type' => 'standard', 'animal_group_id' => $batch->id, 'bird_quantity' => 100, 'is_active' => true]);

        $this->actingAs($user)->get(route('monitor.health.poultry-deaths'))->assertOk()
            ->assertSee('Add mortality record')->assertSee('Test House 99 · B-001 · 100 birds', false);

        $this->actingAs($user)->from(route('monitor.health.poultry-deaths'))
            ->post(route('daily.deaths.store', $batch), ['poultry_house_id' => $house->id, 'quantity_died' => 3, 'from' => 'monitor'])
            ->assertRedirect(route('monitor.health', 'deaths'));

        $this->assertSame(3, (int) MortalityRecord::firstOrFail()->quantity_died);
        $this->assertSame(97, (int) $house->fresh()->bird_quantity);
        $this->assertSame(97, (int) $batch->fresh()->current_quantity);
    }
}
