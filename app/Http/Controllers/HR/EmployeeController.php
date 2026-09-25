<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
 | The staff register.
 |
 | An employee here is a person on the farm's payroll. Whether they can
 | sign in is a separate question, handled by EmployeeAccountController —
 | most casual workers never get a login at all.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Employee::with(['department', 'user'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'ilike', "%{$term}%")
                  ->orWhere('last_name', 'ilike', "%{$term}%")
                  ->orWhere('staff_no', 'ilike', "%{$term}%")
                  ->orWhere('phone', 'ilike', "%{$term}%");
            });
        }

        if ($dept = $request->query('department')) {
            $query->where('department_id', $dept);
        }

        // Filter by kind of worker — permanent, casual, intern and so on.
        if ($type = $request->query('type')) {
            $query->where('employment_type', $type);
        }
        $hiredFrom = $request->query('from');
        $hiredTo = $request->query('to');
        if ($hiredFrom) $query->whereDate('hire_date', '>=', $hiredFrom);
        if ($hiredTo) $query->whereDate('hire_date', '<=', $hiredTo);

        // Default to active staff. Leavers are still here, just not shown.
        $status = $request->query('status', 'active');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('hr.index', [
            'employees'   => $query->paginate(25)->withQueryString(),
            'departments' => Department::where('level', 'department')
                                ->where('is_active', true)
                                ->orderBy('name')->get(),
            'filters'     => [
                'q'          => $request->query('q'),
                'department' => $dept,
                'status'     => $status,
                'type'       => $type,
                'from'       => $hiredFrom,
                'to'         => $hiredTo,
            ],
            'counts'      => [
                'active'     => Employee::where('status', 'active')->count(),
                'suspended'  => Employee::where('status', 'suspended')->count(),
                'terminated' => Employee::where('status', 'terminated')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('hr.create', [
            'departments' => $this->departments(),
            'roles'       => $this->roles(),
            // Shown as a guide; a blank number is allocated on save.
            'nextStaffNo' => $this->nextStaffNo(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['staff_no'] = $data['staff_no'] ?: $this->nextStaffNo();
        $data['created_by'] = $request->user()->id;

        $employee = Employee::create($data);

        AuditLog::record(
            'created', $employee,
            "Registered {$employee->fullName()} ({$employee->staff_no}).",
            null, $employee->only(['staff_no', 'first_name', 'last_name', 'department_id']),
            'hr'
        );

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', "{$employee->fullName()} has been added to the register.");
    }

    public function show(Employee $employee): View
    {
        $employee->load(['department', 'role', 'user.role']);

        return view('hr.show', ['employee' => $employee]);
    }

    public function edit(Employee $employee): View
    {
        return view('hr.edit', [
            'employee'    => $employee,
            'departments' => $this->departments(),
            'roles'       => $this->roles(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $before = $employee->only(array_keys($request->validated()));

        $employee->update($request->validated());

        AuditLog::record(
            'updated', $employee,
            "Updated {$employee->fullName()}.",
            $before, $employee->only(array_keys($request->validated())),
            'hr'
        );

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', 'Changes saved.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->canDo('hr', 'delete'), 403);
        $employee->load('user.role');
        $account = $employee->user;

        abort_if($account?->id === $request->user()->id, 403, 'You cannot delete your own worker record or login.');
        $targetRank = $account?->effectiveRank() ?? ($employee->role?->rank ?? 999);
        abort_if($targetRank <= $request->user()->effectiveRank(), 403, 'You cannot delete a worker at or above your own access level.');

        DB::transaction(function () use ($employee, $account) {
            if ($account) {
                $account->forceFill(['is_active' => false, 'failed_login_attempts' => 0, 'locked_until' => null])->save();
                $account->delete();
            }
            $employee->update(['status' => 'terminated', 'end_date' => $employee->end_date ?: today()]);
            $employee->delete();
        });

        AuditLog::record('worker_removed', $employee, "Removed {$employee->fullName()} ({$employee->staff_no}) and disabled their login.", null, ['login_disabled' => (bool) $account], 'hr');

        return redirect()->route('employees.index')->with('status', 'Worker removed. Their historical records have been retained.');
    }

    /* ---------------------------------------------------------------- */

    private function departments()
    {
        return Department::where('level', 'department')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function roles()
    {
        return Role::where('is_active', true)->orderBy('rank')->orderBy('name')->get();
    }

    /** One BAF series for all staff, including previously deleted records. */
    private function nextStaffNo(): string
    {
        $highest = Employee::withTrashed()->where('staff_no', 'like', 'BAF %')->pluck('staff_no')
            ->filter(fn ($number) => preg_match('/^BAF [0-9]+$/', $number))
            ->map(fn ($number) => (int) substr($number, 4))->max() ?? 0;

        return sprintf('BAF %03d', $highest + 1);
    }
}
