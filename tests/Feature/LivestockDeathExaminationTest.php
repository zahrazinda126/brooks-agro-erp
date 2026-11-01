<?php

namespace Tests\Feature;

use App\Models\{Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LivestockDeathExaminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_goat_deaths_wait_for_examination_and_are_examined(): void
    {
        foreach (['goats' => 'Goats', 'mortality-assess' => 'Assess deaths'] as $key => $name) {
            Module::create(['key' => $key, 'name' => $name, 'group' => 'Farm', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        }
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'exam-test', 'name' => 'Manager', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $group = DB::table('livestock_groups')->insertGetId(['unit_code' => 'goats', 'group_code' => 'G-01', 'source' => 'purchased', 'acquired_on' => today(), 'initial_quantity' => 10, 'current_quantity' => 10, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->post(route('operations.livestock.store', ['unit' => 'goats', 'section' => 'mortality']), [
            'livestock_group_id' => $group, 'occurred_on' => today()->toDateString(), 'quantity' => 2, 'suspected_cause' => 'Bloat',
        ])->assertSessionHasNoErrors();
        $death = DB::table('livestock_mortality_records')->first();
        $this->assertSame('pending_assessment', $death->status);

        $this->actingAs($user)->get(route('monitor.health', 'deaths'))->assertOk()
            ->assertSee('Which deaths are you examining?')->assertSee('Goat deaths')->assertSee('1 waiting')->assertSee('Waiting for examination');
        $this->actingAs($user)->get(route('operations.livestock.examine', 'goats'))->assertOk()->assertSee('Bloat');

        $this->actingAs($user)->put(route('operations.livestock.examine.update', ['unit' => 'goats', 'death' => $death->id]), [
            'established_cause' => 'Bloat from wet pasture', 'action_taken' => 'Moved to dry grazing', 'vet_called' => 1, 'vet_name' => 'Dr Okello',
        ])->assertSessionHasNoErrors();

        $death = DB::table('livestock_mortality_records')->first();
        $this->assertSame('assessed', $death->status);
        $this->assertSame('Bloat from wet pasture', $death->established_cause);
        $this->assertSame((int) $user->id, (int) $death->assessed_by);
        $this->actingAs($user)->get(route('monitor.health', 'deaths'))->assertSee('Bloat from wet pasture')->assertSee('Nothing waiting');
    }
}
