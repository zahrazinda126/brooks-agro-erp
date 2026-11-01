<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 | Issuing a login is deliberately separate from registering an employee.
 |
 | Most casual workers never need one. Making it a distinct action means
 | nobody gets an account by accident, and every account has a moment in
 | the audit trail where a named person decided to grant it.
 |
 | Safeguard: you cannot create an account more senior than your own.
 | Roles carry a `rank` (Director 1, HR Manager 20, Casual Worker 90), and
 | the list here is filtered to ranks below yours. Without this an HR
 | Manager could quietly mint themselves a Director login.
 */
class EmployeeAccountController extends Controller
{
    public function create(Request $request, Employee $employee): View
    {
        abort_if($employee->hasAccount(), 409, 'This employee already has a login.');

        return view('hr.account', [
            'employee'    => $employee,
            'roles'       => $this->assignableRoles($request->user()),
            'departments' => Department::where('level', 'department')
                                ->where('is_active', true)->orderBy('name')->get(),
            'suggested'   => $this->suggestUsername($employee),
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        abort_if($employee->hasAccount(), 409, 'This employee already has a login.');

        $assignable = $this->assignableRoles($request->user())->pluck('id');

        $data = $request->validate([
            'email'         => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'role_id'       => ['required', Rule::in($assignable)],
            'department_id' => ['nullable', 'exists:departments,id'],
        ], [
            'role_id.in'      => 'You cannot assign a role at or above your own level.',
        ]);

        // The login name is the staff number (set by the User model); it cannot be typed differently.
        abort_unless($employee->staff_no, 422, 'Give this employee a staff number before creating a login.');
        abort_if(User::where('username', $employee->staff_no)->exists(), 422, 'Another login already uses staff number '.$employee->staff_no.'.');

        $temporary = $this->temporaryPassword();

        $user = User::create([
            'username'             => $employee->staff_no,
            'name'                 => $employee->fullName(),
            'email'                => $data['email'],
            'password'             => $temporary,   // hashed by the model cast
            'employee_id'          => $employee->id,
            'role_id'              => $data['role_id'],
            'department_id'        => $data['department_id'] ?: $employee->department_id,
            'is_active'            => true,
            'must_change_password' => true,
        ]);

        AuditLog::record(
            'account_created', $user,
            "Issued login '{$user->username}' to {$employee->fullName()} as "
                . Role::find($data['role_id'])->name . '.',
            null, ['username' => $user->username, 'role_id' => $data['role_id']],
            'hr'
        );

        /*
         | The password is shown once, on the next screen. It is never
         | stored in readable form and cannot be retrieved later — if it
         | is lost, HR resets it.
         */
        return redirect()
            ->route('employees.show', $employee)
            ->with('credentials', [
                'username' => $user->username,
                'password' => $temporary,
            ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        $user = $employee->user;
        abort_unless($user, 404, 'This employee has no login.');
        $this->assertOutranks($request->user(), $user);

        return view('hr.account-edit', [
            'employee' => $employee,
            'account' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'departments' => Department::where('level', 'department')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;
        abort_unless($user, 404, 'This employee has no login.');
        $this->assertOutranks($request->user(), $user);
        $assignable = $this->assignableRoles($request->user())->pluck('id');
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', Rule::in($assignable)],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);
        $before = $user->only(['username', 'email', 'role_id', 'department_id']);
        $data['email'] = $data['email'] ?: null;
        $data['department_id'] = $data['department_id'] ?: $employee->department_id;
        $user->update($data);

        AuditLog::record('worker_account_updated', $user, "Updated login '{$user->username}' for {$employee->fullName()}.", $before, $user->only(array_keys($before)), 'hr');

        return redirect()->route('employees.show', $employee)->with('status', 'Worker login details updated.');
    }

    public function reset(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        abort_unless($user, 404, 'This employee has no login.');
        $this->assertOutranks($request->user(), $user);

        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password'              => Hash::make($temporary),
            'must_change_password'  => true,
            'password_changed_at'   => null,
            'password_expires_at'   => null,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ])->save();

        AuditLog::record('password_reset', $user,
            "Reset the password for '{$user->username}'.", null, null, 'hr');

        return redirect()
            ->route('employees.show', $employee)
            ->with('credentials', [
                'username' => $user->username,
                'password' => $temporary,
            ]);
    }

    /** Switch an account off without deleting the employment record. */
    public function toggle(Request $request, Employee $employee): RedirectResponse
    {
        $user = $employee->user;

        abort_unless($user, 404, 'This employee has no login.');
        $this->assertOutranks($request->user(), $user);

        abort_if($user->id === $request->user()->id, 403, 'You cannot disable your own account.');

        $user->forceFill([
            'is_active'             => ! $user->is_active,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ])->save();

        $state = $user->is_active ? 'enabled' : 'disabled';

        AuditLog::record('account_' . $state, $user,
            "Account '{$user->username}' {$state}.", null, null, 'hr');

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', "Login {$state}.");
    }

    /* ---------------------------------------------------------------- */

    /** Roles strictly junior to the signed-in user. Superusers see all. */
    private function assignableRoles(User $actor)
    {
        $roles = Role::where('is_active', true)->orderBy('rank')->orderBy('name');

        if ($actor->isSuperuser()) {
            return $roles->get();
        }

        $myRank = $actor->role?->rank ?? 999;

        // HR normally issues junior accounts only. The CEO account is the
        // one explicit exception: HR owns employee onboarding, while the
        // CEO remains a distinct senior role with its own permissions.
        if ($actor->role?->code === 'hr-manager') {
            return $roles->where(function ($query) use ($myRank) {
                $query->where('rank', '>', $myRank)->orWhere('code', 'ceo');
            })->get();
        }

        return $roles->where('rank', '>', $myRank)->get();
    }

    private function assertOutranks(User $actor, User $target): void
    {
        if ($actor->isSuperuser()) {
            return;
        }

        $mine   = $actor->role?->rank ?? 999;
        $theirs = $target->role?->rank ?? 999;

        abort_if($theirs <= $mine, 403,
            'You cannot change an account at or above your own level.');
    }

    private function suggestUsername(Employee $employee): string
    {
        $base = Str::lower(Str::ascii($employee->first_name));
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'user';

        if (! User::where('username', $base)->exists()) {
            return $base;
        }

        $withSurname = $base . '.' . preg_replace('/[^a-z0-9]/', '',
            Str::lower(Str::ascii($employee->last_name)));

        if (! User::where('username', $withSurname)->exists()) {
            return $withSurname;
        }

        $n = 2;
        while (User::where('username', $base . $n)->exists()) {
            $n++;
        }

        return $base . $n;
    }

    /**
     * A temporary password that can be read aloud over a phone without
     * confusion: no letter O or digit 0, no letter l or digit 1.
     */
    private function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';

        $word = '';
        for ($i = 0; $i < 6; $i++) {
            $word .= $letters[random_int(0, strlen($letters) - 1)];
        }

        $number = '';
        for ($i = 0; $i < 3; $i++) {
            $number .= $digits[random_int(0, strlen($digits) - 1)];
        }

        return ucfirst($word) . $number;
    }
}
