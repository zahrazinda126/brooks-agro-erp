<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, HealthEvent, Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthMonitorAllUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatments_and_deaths_list_every_animal_unit_with_a_unit_filter(): void
    {
        foreach (['health' => 'Flock Health', 'goats' => 'Goats', 'dogs' => 'Dogs', 'batches' => 'Batches'] as $key => $name) {
            Module::create(['key' => $key, 'name' => $name, 'group' => 'Farm', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        }
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'monitor-test', 'name' => 'Monitor', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id, 'purpose' => 'layer', 'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 100, 'status' => 'active']);
        HealthEvent::create(['animal_group_id' => $batch->id, 'department_id' => $department->id, 'event_type' => 'treatment', 'occurred_on' => today(), 'birds_treated' => 40, 'reason' => 'Coryza', 'recorded_by' => $user->id]);

        $group = fn (string $unit, string $code) => DB::table('livestock_groups')->insertGetId(['unit_code' => $unit, 'group_code' => $code, 'source' => 'purchased', 'acquired_on' => today(), 'initial_quantity' => 10, 'current_quantity' => 10, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('livestock_health_records')->insert(['livestock_group_id' => $group('goats', 'G-01'), 'recorded_on' => today(), 'event_type' => 'deworming', 'condition_or_vaccine' => 'Worms', 'animals_affected' => 6, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('livestock_mortality_records')->insert(['livestock_group_id' => $group('dogs', 'D-01'), 'occurred_on' => today(), 'quantity' => 1, 'suspected_cause' => 'Snake bite', 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->get(route('monitor.health', 'treatments'))->assertOk()
            ->assertSee('Coryza')->assertSee('Worms')->assertSee('G-01')->assertSee('All units');
        $this->actingAs($user)->get(route('monitor.health', ['task' => 'treatments', 'unit' => 'goats']))->assertOk()
            ->assertSee('Worms')->assertDontSee('Coryza');
        $this->actingAs($user)->get(route('monitor.health', 'deaths'))->assertOk()
            ->assertSee('Snake bite')->assertDontSee('Worms');
    }
}
