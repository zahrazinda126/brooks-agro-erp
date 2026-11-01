<?php

namespace Tests\Feature;

use App\Models\{AnimalGroup, Department, HealthEvent, Medication, Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewMedicineFromHealthRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_medicine_typed_on_the_health_form_joins_the_list(): void
    {
        Module::create(['key' => 'health', 'name' => 'Flock Health', 'group' => 'Poultry', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $user = User::create(['username' => 'vet-test', 'name' => 'Vet', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $department = Department::create(['code' => 'poultry', 'name' => 'Poultry']);
        $batch = AnimalGroup::create(['code' => 'B-001', 'department_id' => $department->id, 'purpose' => 'layer', 'received_date' => today(), 'quantity_received' => 100, 'current_quantity' => 100, 'status' => 'active']);
        $form = ['animal_group_id' => $batch->id, 'event_type' => 'treatment', 'occurred_on' => today()->toDateString(), 'days_of_course' => 5, 'birds_treated' => 100,
            'medication_id' => 'new', 'new_medication_name' => 'Doxycycline', 'new_medication_type' => 'antibiotic', 'new_withdrawal_days' => 6];

        $this->actingAs($user)->post(route('health.store'), $form)->assertSessionHasNoErrors();

        $medicine = Medication::where('name', 'Doxycycline')->firstOrFail();
        $this->assertSame(6, (int) $medicine->withdrawal_days_eggs);
        $this->assertTrue($medicine->is_active, 'available on the list for the next record');
        $this->assertSame($medicine->id, HealthEvent::firstOrFail()->medication_id);

        // Typing the same name again reuses it instead of duplicating.
        $this->actingAs($user)->post(route('health.store'), ['new_medication_name' => 'doxycycline'] + $form)->assertSessionHasNoErrors();
        $this->assertSame(1, Medication::whereRaw('lower(name) = ?', ['doxycycline'])->count());
        $this->assertSame(2, HealthEvent::where('medication_id', $medicine->id)->count());
    }
}
