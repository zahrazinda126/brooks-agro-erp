<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Temporary workers (builders, helpers, short-term casuals): a short registration instead of
 * the full employee form. They get a staff number, no login, are not on payroll, and can be
 * given a room at the same time. HR and the PA can register them; HR assigns rooms.
 */
class TemporaryWorkerController extends Controller
{
    public const ROLES = ['Lead constructor', 'Builder', 'Helper', 'Casual worker'];

    public function index(Request $request): View
    {
        $this->authorizeHr($request->user());
        $people = Employee::where('on_payroll', false)->where('employment_type', 'temporary')->where('status', 'active')
            ->orderBy('first_name')->get()
            ->map(function (Employee $e) {
                $e->stay = DB::table('staff_accommodation_allocations as a')->join('staff_hostel_rooms as r', 'r.id', '=', 'a.staff_hostel_room_id')
                    ->join('staff_hostels as h', 'h.id', '=', 'r.staff_hostel_id')->where('a.employee_id', $e->id)->whereNull('a.checked_out_on')
                    ->select('a.id', 'a.checked_in_on', 'r.room_number', 'h.name as hostel')->first();
                $e->project = DB::table('construction_project_workers')->join('construction_projects', 'construction_projects.id', '=', 'construction_project_workers.construction_project_id')
                    ->where('construction_project_workers.employee_id', $e->id)->where('construction_projects.status', 'active')->value('construction_projects.name');
                return $e;
            });

        return view('hr.temporary-workers', ['people' => $people, 'rooms' => self::rooms(), 'roles' => self::ROLES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHr($request->user());
        $data = $this->validated($request);
        $room = $request->validate(['staff_hostel_room_id' => ['nullable', 'exists:staff_hostel_rooms,id']])['staff_hostel_room_id'] ?? null;
        $employee = self::register($data, $request->user());
        if ($room) $this->checkIn($employee, (int) $room, $data['arrived_on'], $data['expected_end_date'] ?? null, $request->user());

        return back()->with('status', "{$employee->fullName()} registered ({$employee->staff_no})".($room ? ' and given a room.' : '.'));
    }

    public function assignRoom(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeHr($request->user());
        $data = $request->validate(['staff_hostel_room_id' => ['required', 'exists:staff_hostel_rooms,id']]);
        abort_if(DB::table('staff_accommodation_allocations')->where('employee_id', $employee->id)->whereNull('checked_out_on')->exists(), 422, 'This person already has a room.');
        $this->checkIn($employee, (int) $data['staff_hostel_room_id'], today()->toDateString(), ($employee->expected_end_date ? \Illuminate\Support\Carbon::parse($employee->expected_end_date)->toDateString() : null), $request->user());

        return back()->with('status', "Room assigned to {$employee->fullName()}.");
    }

    /** They have left the farm: out of the room, and their stay ends today. */
    public function checkout(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeHr($request->user());
        DB::transaction(function () use ($employee, $request) {
            DB::table('staff_accommodation_allocations')->where('employee_id', $employee->id)->whereNull('checked_out_on')
                ->update(['checked_out_on' => today(), 'checkout_reason' => 'Temporary work ended', 'updated_at' => now()]);
            $employee->forceFill(['end_date' => today(), 'status' => 'terminated'])->save();
            AuditLog::record('temporary_worker_left', $employee, "{$employee->fullName()} left the farm.", null, null, 'hr');
        });

        return back()->with('status', "{$employee->fullName()} checked out.");
    }

    /* ---------- shared with the PA's project page ---------- */

    /** Register a temporary worker (no login, not on payroll). */
    public static function register(array $data, User $by): Employee
    {
        $highest = Employee::withTrashed()->where('staff_no', 'like', 'BAF %')->pluck('staff_no')
            ->filter(fn ($n) => preg_match('/^BAF [0-9]+$/', $n))->map(fn ($n) => (int) substr($n, 4))->max() ?? 0;
        $employee = Employee::create([
            'staff_no' => sprintf('BAF %03d', $highest + 1), 'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'phone' => $data['phone'], 'national_id' => $data['national_id'] ?? null, 'job_title' => $data['role'],
            'employment_type' => 'temporary', 'status' => 'active', 'hire_date' => $data['arrived_on'], 'expected_end_date' => $data['expected_end_date'] ?? null,
            'base_salary' => 0, 'pay_frequency' => 'daily', 'on_payroll' => false, 'created_by' => $by->id,
        ]);
        AuditLog::record('temporary_worker_registered', $employee, "Temporary worker {$employee->fullName()} ({$data['role']}) registered.", null, null, 'hr');

        return $employee;
    }

    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'first_name' => ['required', 'string', 'max:80'], $prefix.'last_name' => ['required', 'string', 'max:80'],
            $prefix.'phone' => ['required', 'string', 'max:30'], $prefix.'national_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9 ]{14,20}$/'],
            $prefix.'role' => ['required', Rule::in(self::ROLES)],
            $prefix.'arrived_on' => ['required', 'date'], $prefix.'expected_end_date' => ['nullable', 'date', 'after_or_equal:'.$prefix.'arrived_on'],
        ];
    }

    /** Rooms with a free bed. */
    public static function rooms()
    {
        return DB::table('staff_hostel_rooms as r')->join('staff_hostels as h', 'h.id', '=', 'r.staff_hostel_id')->where('r.is_active', true)
            ->select('r.id', 'r.room_number', 'r.bed_spaces', 'h.name as hostel')
            ->selectSub(fn ($q) => $q->from('staff_accommodation_allocations')->whereColumn('staff_hostel_room_id', 'r.id')->whereNull('checked_out_on')->selectRaw('count(*)'), 'occupied')
            ->orderBy('h.name')->orderBy('r.room_number')->get()->filter(fn ($r) => $r->occupied < max(1, $r->bed_spaces))->values();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(self::rules(), ['national_id.regex' => 'A National ID (NIN) has 14 letters and numbers.']);
        if (! empty($data['national_id'])) $data['national_id'] = strtoupper(preg_replace('/\s+/', '', $data['national_id']));
        return $data;
    }

    private function checkIn(Employee $employee, int $roomId, string $from, ?string $until, User $by): void
    {
        abort_unless(self::rooms()->contains('id', $roomId), 422, 'That room has no free bed.');
        DB::table('staff_accommodation_allocations')->insert(['staff_hostel_room_id' => $roomId, 'employee_id' => $employee->id, 'checked_in_on' => $from,
            'expected_checkout_on' => $until, 'recorded_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function authorizeHr(User $user): void
    {
        abort_unless($user->canAccess('hr') || $user->roles->pluck('code')->push($user->role?->code)->contains('hr-manager'), 403);
    }
}
