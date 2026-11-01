<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, Module, PoultryHouse, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** What was missing compared with Farm Hub (25 Oct 2026): worker water / sick birds / problem reports and farm registers. */
class HouseReportsAndRegistersTest extends TestCase
{
    use RefreshDatabase;

    private function farm(): array
    {
        Module::create(['key' => 'mortality-entry', 'name' => 'Deaths', 'group' => 'Poultry', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'boss', 'name' => 'Boss', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id, 'purpose' => 'layer', 'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 100, 'status' => 'active']);
        $house = PoultryHouse::create(['house_number' => 'Test House 77', 'house_type' => 'standard', 'animal_group_id' => $batch->id, 'bird_quantity' => 100, 'is_active' => true]);

        return [$user, $batch, $house];
    }

    public function test_water_sick_birds_and_problems_are_saved_for_the_supervisor_to_check(): void
    {
        [$user, $batch, $house] = $this->farm();

        $this->actingAs($user)->get(route('daily.water', $batch))->assertOk()->assertSee('How many litres of water');
        $this->actingAs($user)->post(route('daily.water.store', $batch), ['poultry_house_id' => $house->id, 'quantity' => 240])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($user)->post(route('daily.sick.store', $batch), ['poultry_house_id' => $house->id, 'quantity' => 500, 'details' => 'weak'])
            ->assertSessionHasErrors('quantity'); // more than the 100 birds in the house
        $this->actingAs($user)->post(route('daily.sick.store', $batch), ['poultry_house_id' => $house->id, 'quantity' => 3, 'details' => 'sitting alone, not eating'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('daily.problem.store', $batch), ['poultry_house_id' => $house->id, 'category' => 'drinkers', 'details' => 'two drinkers leaking'])
            ->assertSessionHasNoErrors();

        $rows = DB::table('poultry_house_reports')->orderBy('id')->get();
        $this->assertSame(['water', 'sick_birds', 'problem'], $rows->pluck('report_type')->all());
        $this->assertSame(['submitted'], $rows->pluck('verification_status')->unique()->values()->all(), 'all wait for the supervisor');
        $this->assertSame(240.0, (float) $rows[0]->quantity);
    }

    public function test_house_preparation_is_ready_only_when_every_check_is_done_and_faults_are_closed_by_a_manager(): void
    {
        [$user, , $house] = $this->farm();
        $checks = array_keys(\App\Http\Controllers\Operations\FarmRegisterController::REGISTERS['house_preparation']['checks']);

        $all = array_fill_keys($checks, 'yes');
        $this->actingAs($user)->post(route('farm-registers.store'), ['register' => 'house_preparation', 'entry_date' => today()->toDateString(),
            'poultry_house_id' => $house->id, 'answers' => $all])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('farm-registers.store'), ['register' => 'house_preparation', 'entry_date' => today()->toDateString(),
            'poultry_house_id' => $house->id, 'answers' => ['dried' => 'no'] + $all])->assertSessionHasNoErrors();
        $this->assertSame(['ready', 'not_ready'], DB::table('farm_register_entries')->orderBy('id')->pluck('status')->all());

        $this->actingAs($user)->post(route('farm-registers.store'), ['register' => 'fault', 'entry_date' => today()->toDateString(),
            'location' => 'Borehole', 'category' => 'water', 'severity' => 'critical', 'details' => 'Pump not starting'])->assertSessionHasNoErrors();
        $fault = DB::table('farm_register_entries')->where('register', 'fault')->first();
        $this->assertSame('open', $fault->status);
        $this->actingAs($user)->post(route('farm-registers.close', $fault->id), ['closing_note' => 'Pump repaired'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', DB::table('farm_register_entries')->where('id', $fault->id)->value('status'));
        $this->actingAs($user)->get(route('farm-registers.index', ['register' => 'fault']))->assertOk()->assertSee('Pump not starting');
    }
}
