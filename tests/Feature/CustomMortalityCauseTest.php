<?php

namespace Tests\Feature;

use App\Http\Controllers\Poultry\MortalityAssessmentController;
use App\Models\{AnimalGroup, Department, MortalityCause, MortalityRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomMortalityCauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_cause_is_saved_and_retained_when_reopening_assessment(): void
    {
        $user = User::factory()->create(['username' => 'custom-cause-test']);
        $manager = \Mockery::mock(User::class)->makePartial();
        $manager->id = $user->id;
        $manager->shouldReceive('isSuperuser')->andReturn(true);
        $manager->shouldReceive('canDo')->andReturn(true);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id,
            'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 99]);
        $record = MortalityRecord::create(['animal_group_id' => $batch->id,
            'department_id' => $department->id, 'occurred_on' => today(),
            'quantity_died' => 1, 'recorded_by' => $user->id, 'recorded_at' => now()]);
        $request = Request::create('/', 'PUT', ['cause_name' => '  Accidental injury during transfer  ']);
        $request->setUserResolver(fn () => $manager);
        $controller = app(MortalityAssessmentController::class);
        $controller->update($request, $record);

        $record->refresh();
        $this->assertSame('Accidental injury during transfer', $record->cause->name);
        $this->assertSame('assessed', $record->status);
        $this->assertFalse($record->cause->is_active);
        $this->assertTrue($controller->edit($request, $record)->getData()['causes']->flatten()->contains('id', $record->mortality_cause_id));

        $request->merge(['cause_name' => 'accidental injury during transfer']);
        $controller->update($request, $record);
        $this->assertSame(1, MortalityCause::count());

        $request->merge(['vet_called' => '1', 'vet_name' => '   ']);
        try {
            $controller->update($request, $record);
            $this->fail('A vet name must be provided when a vet was called.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('vet_name', $exception->errors());
        }
        $request->merge(['vet_name' => ' Dr. Jane ']);
        $controller->update($request, $record);
        $this->assertSame('Dr. Jane', $record->refresh()->vet_name);
        $this->assertTrue($record->vet_called);
        $request->merge(['vet_called' => '0']);
        $controller->update($request, $record);
        $this->assertNull($record->refresh()->vet_name);
        $this->assertFalse($record->vet_called);

        $request->merge(['cause_name' => '']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->update($request, $record);
    }
}
