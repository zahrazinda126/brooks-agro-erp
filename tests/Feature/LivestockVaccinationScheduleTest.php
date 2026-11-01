<?php

namespace Tests\Feature;

use App\Models\{Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LivestockVaccinationScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_goat_schedule_item_is_due_then_moves_on_after_a_dose(): void
    {
        Module::create(['key' => 'goats', 'name' => 'Goats', 'group' => 'Farm', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'vacc-test', 'name' => 'Vet', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $group = DB::table('livestock_groups')->insertGetId(['unit_code' => 'goats', 'group_code' => 'G-01', 'source' => 'purchased', 'acquired_on' => today(), 'initial_quantity' => 10, 'current_quantity' => 10, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->post(route('operations.livestock.plans.store', 'goats'), [
            'vaccine' => 'PPR', 'first_due_on' => today()->subDays(3)->toDateString(), 'repeat_every_months' => 12, 'is_essential' => 1,
        ])->assertSessionHasNoErrors();
        $plan = DB::table('livestock_vaccination_plans')->first();

        $page = fn () => $this->actingAs($user)->get(route('operations.livestock.section', ['unit' => 'goats', 'section' => 'vaccination']))->assertOk();
        $page()->assertSee('+ Add schedule item')->assertSee('+ Record vaccination')->assertSee('PPR')->assertSee('Overdue');

        $this->actingAs($user)->post(route('operations.livestock.store', ['unit' => 'goats', 'section' => 'vaccination']), [
            'livestock_group_id' => $group, 'livestock_vaccination_plan_id' => $plan->id, 'recorded_on' => today()->toDateString(),
            'condition_or_vaccine' => 'PPR', 'animals_affected' => 10,
        ])->assertSessionHasNoErrors();

        $dose = DB::table('livestock_health_records')->first();
        $this->assertSame($plan->id, (int) $dose->livestock_vaccination_plan_id);
        $this->assertSame(today()->addYear()->toDateString(), substr((string) $dose->next_due_on, 0, 10), 'next dose set from the repeat');
        $page()->assertSee('Scheduled')->assertSee(today()->addYear()->format('d M Y'))->assertDontSee('Overdue');
    }
}
