<?php

namespace Tests\Feature;

use App\Models\{Employee, Module, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeNationalIdPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_id_picture_is_optional_stored_privately_and_viewable_by_hr(): void
    {
        Storage::fake('local');
        config(['filesystems.uploads' => null]);
        Module::create(['key' => 'hr', 'name' => 'Employees', 'group' => 'HR', 'sort_order' => 1, 'is_worker_module' => false, 'is_active' => true]);
        $role = Role::create(['code' => 'director', 'name' => 'Director', 'is_superuser' => true, 'rank' => 1, 'is_active' => true]);
        $hr = User::create(['username' => 'id-photo-test', 'name' => 'HR', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $person = ['first_name' => 'Amos', 'last_name' => 'Kato', 'phone' => '0700000001', 'employment_type' => 'casual', 'status' => 'active', 'base_salary' => 0, 'pay_frequency' => 'daily'];

        // Optional: an employee can be saved without a picture.
        $this->actingAs($hr)->post(route('employees.store'), $person)->assertSessionHasNoErrors();
        $without = Employee::where('first_name', 'Amos')->firstOrFail();
        $this->assertNull($without->national_id_photo);
        $this->actingAs($hr)->get(route('employees.national-id-photo', $without))->assertNotFound();

        // With a picture: stored on the private disk and opened through the HR-only link.
        $this->actingAs($hr)->post(route('employees.store'), ['first_name' => 'Ruth', 'phone' => '0700000002', 'national_id_photo' => UploadedFile::fake()->image('id.jpg')] + $person)
            ->assertSessionHasNoErrors();
        $with = Employee::where('first_name', 'Ruth')->firstOrFail();
        $this->assertNotNull($with->national_id_photo);
        Storage::disk('local')->assertExists($with->national_id_photo);
        $this->actingAs($hr)->get(route('employees.national-id-photo', $with))->assertOk();
        $this->actingAs($hr)->get(route('employees.show', $with))->assertSee('View ID picture');

        // Replacing it removes the old file; a non-image is refused.
        $old = $with->national_id_photo;
        $this->actingAs($hr)->put(route('employees.update', $with), ['national_id_photo' => UploadedFile::fake()->image('new.png')] + $with->only(array_merge(array_keys($person), ['staff_no'])))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->actingAs($hr)->put(route('employees.update', $with), ['national_id_photo' => UploadedFile::fake()->create('id.exe', 10)] + $with->only(array_merge(array_keys($person), ['staff_no'])))
            ->assertSessionHasErrors('national_id_photo');

        // National ID (NIN): exactly 14 letters/numbers; spaces and lower case are tidied.
        $edit = $with->only(array_merge(array_keys($person), ['staff_no']));
        $this->actingAs($hr)->put(route('employees.update', $with), ['national_id' => 'cm 1234 5678 abcd'] + $edit)->assertSessionHasNoErrors();
        $this->assertSame('CM12345678ABCD', $with->fresh()->national_id);
        $this->actingAs($hr)->put(route('employees.update', $with), ['national_id' => 'CM12345678ABC'] + $edit)->assertSessionHasErrors('national_id');
        $this->assertSame('CM12345678ABCD', $with->fresh()->national_id);
    }
}
