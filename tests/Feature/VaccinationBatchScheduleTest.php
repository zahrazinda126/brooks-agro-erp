<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, Medication, VaccinationPlan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationBatchScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_schedule_does_not_apply_to_another_batch_of_the_same_breed(): void
    {
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $attributes = ['department_id' => $department->id, 'purpose' => 'layer',
            'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 100];
        $first = AnimalGroup::create($attributes + ['code' => 'B-001']);
        $second = AnimalGroup::create($attributes + ['code' => 'B-002']);
        $vaccine = Medication::create(['code' => 'TEST', 'name' => 'Test vaccine', 'type' => 'vaccine']);
        $general = VaccinationPlan::create(['medication_id' => $vaccine->id, 'purpose' => 'layer', 'age_days' => 7]);
        $specific = VaccinationPlan::create(['animal_group_id' => $first->id,
            'medication_id' => $vaccine->id, 'purpose' => 'layer', 'age_days' => 14]);

        $this->assertEquals([$general->id, $specific->id], VaccinationPlan::for($first)->pluck('id')->all());
        $this->assertEquals([$general->id], VaccinationPlan::for($second)->pluck('id')->all());

        $user = \App\Models\User::factory()->create(['username' => 'vaccination-filter-test']);
        $viewer = \Mockery::mock(\App\Models\User::class)->makePartial();
        $viewer->shouldReceive('isSuperuser')->andReturn(true);
        foreach ([[$first, '2026-09-19'], [$first, '2026-09-20'], [$second, '2026-09-20']] as [$batch, $date]) {
            \App\Models\HealthEvent::create([
                'animal_group_id' => $batch->id, 'department_id' => $department->id,
                'event_type' => 'vaccination', 'medication_id' => $vaccine->id,
                'occurred_on' => $date, 'reason' => 'Scheduled vaccination',
                'recorded_by' => $user->id, 'recorded_at' => now(),
            ]);
        }
        $request = \Illuminate\Http\Request::create('/vaccination-schedule', 'GET', [
            'from' => '2026-09-20', 'to' => '2026-09-20', 'batch_id' => $first->id,
            'vaccine_id' => $vaccine->id, 'schedule_status' => 'active',
        ]);
        $request->setUserResolver(fn () => $viewer);
        $data = app(\App\Http\Controllers\VaccinationScheduleController::class)->index($request)->getData();
        $this->assertSame(1, $data['records']->total());
        $this->assertEquals([$general->id, $specific->id], $data['plans']->pluck('id')->all());

        $request->merge(['schedule_status' => 'inactive']);
        $data = app(\App\Http\Controllers\VaccinationScheduleController::class)->index($request)->getData();
        $this->assertCount(0, $data['plans']);
        $this->assertSame(1, $data['records']->total());

        $request->merge(['from' => '2026-09-21']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Http\Controllers\VaccinationScheduleController::class)->index($request);
    }
}
