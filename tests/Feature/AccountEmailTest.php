<?php

namespace Tests\Feature;

use App\Http\Controllers\HR\EmployeeAccountController;
use App\Models\{Employee, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_creation_requires_valid_email_and_saves_the_entered_address(): void
    {
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->shouldReceive('isSuperuser')->andReturn(true);
        $role = Role::create(['code' => 'test-worker', 'name' => 'Worker', 'rank' => 90, 'is_active' => true]);
        $employee = Employee::create(['staff_no' => 'BAF 004', 'first_name' => 'Test', 'last_name' => 'Worker']);
        $controller = app(EmployeeAccountController::class);
        $base = ['username' => 'BAF 004', 'role_id' => $role->id, 'department_id' => null];

        foreach ([[], ['email' => ''], ['email' => 'invalid-address']] as $input) {
            $request = Request::create('/', 'POST', $base + $input);
            $request->setUserResolver(fn () => $actor);
            try {
                $controller->store($request, $employee);
                $this->fail('An account must have a valid email.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('email', $exception->errors());
            }
        }
        $this->assertDatabaseMissing('users', ['username' => 'BAF 004']);
        $request = Request::create('/', 'POST', $base + ['email' => 'worker@example.com']);
        $request->setUserResolver(fn () => $actor);
        $controller->store($request, $employee);
        $this->assertDatabaseHas('users', ['username' => 'BAF 004', 'email' => 'worker@example.com', 'employee_id' => $employee->id]);
    }
}
