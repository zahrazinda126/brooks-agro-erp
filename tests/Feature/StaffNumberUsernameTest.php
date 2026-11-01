<?php

namespace Tests\Feature;

use App\Http\Controllers\HR\EmployeeAccountController;
use App\Models\{Employee, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class StaffNumberUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_logins_use_the_staff_number_and_follow_it_when_it_changes(): void
    {
        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->shouldReceive('isSuperuser')->andReturn(true);
        $role = Role::create(['code' => 'test-worker', 'name' => 'Worker', 'rank' => 90, 'is_active' => true]);
        $employee = Employee::create(['staff_no' => 'BAF 007', 'first_name' => 'Jane', 'last_name' => 'Doe']);

        // A different username typed on the form is ignored: the login is the staff number.
        $request = Request::create('/', 'POST', ['username' => 'jane', 'email' => 'jane@example.com', 'role_id' => $role->id, 'department_id' => null]);
        $request->setUserResolver(fn () => $actor);
        app(EmployeeAccountController::class)->store($request, $employee);
        $user = User::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('BAF 007', $user->username);

        // Direct attempts to change it are overridden too.
        $user->update(['username' => 'something-else']);
        $this->assertSame('BAF 007', $user->fresh()->username);

        // Changing the staff number renames the login.
        $employee->update(['staff_no' => 'BAF 031']);
        $this->assertSame('BAF 031', $user->fresh()->username);

        // The admin account (no staff record) keeps its own username.
        $admin = User::create(['username' => 'sysadmin', 'name' => 'System Administrator', 'password' => 'password', 'is_active' => true, 'must_change_password' => false]);
        $admin->update(['name' => 'Admin']);
        $this->assertSame('sysadmin', $admin->fresh()->username);
    }
}
