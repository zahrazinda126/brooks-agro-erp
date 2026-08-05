<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // Shown as a guide; the real number is allocated on save, once
            // the kind of worker is known.
            'nextStaffNo' => $this->nextStaffNo('permanent'),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /*
         | The number depends on what kind of worker this is, so it is
         | allocated here rather than on the form — a builder must not
         | consume a number from the permanent range.
         */
        $data['staff_no']   = $data['staff_no'] ?: $this->nextStaffNo($data['employment_type']);
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

    /**
     * Next staff number for a given kind of worker.
     *
     * Each kind has its own series — BAF for permanent, CAS for casual, TMP
     * for builders and other short engagements, INT for interns, VAC for
     * holiday workers.
     *
     * NUMBERS ARE NEVER REUSED. Not when someone leaves, not ever. The
     * moment BAF-0007 means two different people, every payroll line, task,
     * performance figure and record against that number becomes ambiguous —
     * and nobody discovers it until a payment is queried two years later.
     *
     * Separate series also keep a three-week builder from consuming a number
     * in the permanent range, which is what makes the register readable at a
     * glance: anyone can see INT-0004 is an intern without looking it up.
     *
     * The sequence reads the highest number ever issued in that series,
     * including soft-deleted records, so a deletion can never cause a
     * duplicate.
     */
    private function nextStaffNo(string $employmentType = 'permanent'): string
    {
        $prefix = $this->prefixFor($employmentType);

        $last = Employee::withTrashed()
            ->where('staff_no', 'like', "{$prefix}-%")
            ->orderByRaw('LENGTH(staff_no) DESC, staff_no DESC')
            ->value('staff_no');

        $next = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $next);
    }

    /** Which series a kind of worker belongs to. Editable in settings. */
    private function prefixFor(string $employmentType): string
    {
        $key = match ($employmentType) {
            'casual'    => 'hr.staff_no_prefix_casual',
            'temporary' => 'hr.staff_no_prefix_temporary',
            'intern'    => 'hr.staff_no_prefix_intern',
            'vacist'    => 'hr.staff_no_prefix_vacist',
            default     => 'hr.staff_no_prefix_permanent',
        };

        $fallback = match ($employmentType) {
            'casual'    => 'CAS',
            'temporary' => 'TMP',
            'intern'    => 'INT',
            'vacist'    => 'VAC',
            default     => 'BAF',
        };

        return Setting::get($key, $fallback);
    }
}