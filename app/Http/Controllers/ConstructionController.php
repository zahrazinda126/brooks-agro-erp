<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Construction projects, kept by the PA: builders (registered by HR) with their agreed pay,
 * materials needed. Materials issued come from the Building Materials Store; payments come
 * from Finance's normal entry point when Finance chooses the project.
 */
class ConstructionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request->user());
        $projects = DB::table('construction_projects')->orderByRaw("status = 'finished'")->orderByDesc('starts_on')->get()
            ->map(function ($p) {
                $p->builders = DB::table('construction_project_workers')->where('construction_project_id', $p->id)->count();
                $p->paid = (float) DB::table('financial_transactions')->where('construction_project_id', $p->id)->whereNull('reversed_at')->sum('amount');
                $p->materials_value = (float) $this->issued($p->id)->sum('total_cost');
                $p->owed = (float) DB::table('payables')->where('construction_project_id', $p->id)->selectRaw('coalesce(sum(amount - amount_paid), 0) as owed')->value('owed');
                return $p;
            });

        return view('construction.index', ['projects' => $projects, 'canManage' => $this->canManage($request->user()),
            'people' => Employee::where('status', 'active')->orderBy('on_payroll')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'staff_no', 'on_payroll'])]);
    }

    public function show(Request $request, int $project): View
    {
        $this->authorizeView($request->user());
        $p = DB::table('construction_projects')->find($project) ?? abort(404);
        $workers = DB::table('construction_project_workers')->join('employees', 'employees.id', '=', 'construction_project_workers.employee_id')
            ->where('construction_project_id', $p->id)
            ->select('construction_project_workers.*', 'employees.first_name', 'employees.last_name', 'employees.staff_no', 'employees.phone')
            ->orderByRaw("case construction_project_workers.role when 'lead' then 0 when 'builder' then 1 else 2 end")->orderBy('employees.first_name')->get()
            ->map(function ($w) use ($p) {
                $w->paid = (float) DB::table('financial_transactions')->where('construction_project_id', $p->id)->where('construction_worker_id', $w->employee_id)->whereNull('reversed_at')->sum('amount');
                return $w;
            });

        return view('construction.show', [
            'project' => $p,
            'workers' => $workers,
            // Materials bought on credit: supplier bills for this project and what is still owed.
            'bills' => DB::table('payables')->where('construction_project_id', $p->id)->orderByDesc('invoice_date')->get(),
            'needed' => DB::table('construction_materials_needed')->where('construction_project_id', $p->id)->orderBy('item')->get(),
            'issued' => $this->issued($p->id)->get(),
            'payments' => DB::table('financial_transactions')->where('construction_project_id', $p->id)->whereNull('reversed_at')
                ->leftJoin('employees', 'employees.id', '=', 'financial_transactions.construction_worker_id')
                ->select('financial_transactions.*', 'employees.first_name', 'employees.last_name')->orderByDesc('transaction_date')->get(),
            // Builders are people HR registered; those not on payroll come first.
            'people' => Employee::where('status', 'active')->orderBy('on_payroll')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'staff_no', 'on_payroll', 'employment_type']),
            'canManage' => $this->canManage($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'location' => ['nullable', 'string', 'max:160'], 'starts_on' => ['required', 'date'],
            'expected_end_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $lead = $request->validate(['person' => ['nullable', Rule::in(['none', 'existing', 'new'])]])['person'] ?? 'none';
        // The project and its lead constructor are saved together (nothing is saved if the lead's details are wrong).
        $id = DB::transaction(function () use ($data, $request, $lead) {
            $id = DB::table('construction_projects')->insertGetId($data + ['status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::record('construction_project_created', null, "Construction project \"{$data['name']}\" started.", null, ['project_id' => $id] + $data, 'construction');
            if ($lead !== 'none') $this->attachWorker($request, $id, $data['name'], 'lead', $data['starts_on'], $data['expected_end_on'] ?? null);
            return $id;
        });

        return redirect()->route('construction.show', $id)->with('status', 'Project created.'.($lead === 'new' ? ' HR has been asked to give the lead constructor a room.' : ''));
    }

    public function finish(Request $request, int $project): RedirectResponse
    {
        $this->authorizeManage($request->user());
        $status = DB::table('construction_projects')->where('id', $project)->value('status') ?? abort(404);
        DB::table('construction_projects')->where('id', $project)->update(['status' => $status === 'finished' ? 'active' : 'finished', 'updated_at' => now()]);

        return back()->with('status', $status === 'finished' ? 'Project reopened.' : 'Project marked finished.');
    }

    /**
     * Add a person to the project: someone already registered, or a new person the PA registers here
     * (HR then assigns them a room). The farm pays the lead constructor; the team is paid by the lead.
     */
    public function addWorker(Request $request, int $project): RedirectResponse
    {
        $this->authorizeManage($request->user());
        $projectName = DB::table('construction_projects')->where('id', $project)->value('name') ?? abort(404);
        $base = $request->validate([
            'person' => ['required', Rule::in(['existing', 'new'])],
            'role' => ['required', Rule::in(['lead', 'builder', 'helper'])],
            'from_on' => ['nullable', 'date'], 'to_on' => ['nullable', 'date', 'after_or_equal:from_on'],
        ]);
        $isNew = $this->attachWorker($request, $project, $projectName, $base['role'], $base['from_on'] ?? null, $base['to_on'] ?? null);

        return back()->with('status', ['lead' => 'Lead constructor', 'builder' => 'Builder', 'helper' => 'Helper'][$base['role']].' added to the project.'.($isNew ? ' HR has been asked to assign a room.' : ''));
    }

    /**
     * Put a person on the project: someone already registered (employee_id), or a new person
     * (new_* fields) registered as a temporary worker, after which HR is asked for a room.
     * The lead constructor has the agreed pay (pay_basis, agreed_amount); the team is paid by the lead.
     * Returns true when a new person was registered.
     */
    private function attachWorker(Request $request, int $project, string $projectName, string $role, ?string $from, ?string $to): bool
    {
        $user = $request->user();
        $person = $request->validate(['person' => ['required', Rule::in(['existing', 'new'])]])['person'];
        $pay = $role === 'lead'
            ? $request->validate(['pay_basis' => ['required', Rule::in(['day', 'job'])], 'agreed_amount' => ['required', 'numeric', 'min:0']], ['agreed_amount.required' => 'Enter the amount agreed with the lead constructor.'])
            : ['pay_basis' => 'by_lead', 'agreed_amount' => 0];

        if ($person === 'new') {
            $new = $request->validate(\App\Http\Controllers\HR\TemporaryWorkerController::rules('new_'), ['new_national_id.regex' => 'A National ID (NIN) has 14 letters and numbers.']);
            $fields = collect($new)->mapWithKeys(fn ($v, $k) => [substr($k, 4) => $v])->all();
            $employee = \App\Http\Controllers\HR\TemporaryWorkerController::register($fields, $user);
            $employeeId = $employee->id;
            // HR gives them a room.
            app(\App\Services\CommunicationService::class)->toRole('hr-manager', 'Room needed: '.$employee->fullName(),
                "{$user->displayName()} registered {$employee->fullName()} ({$fields['role']}) for the construction project \"{$projectName}\". Please assign a room.",
                $user->id, route('hr.temporary.index', [], false));
        } else {
            $employeeId = (int) $request->validate(['employee_id' => ['required', Rule::exists('employees', 'id')->where('status', 'active')]], ['employee_id.required' => 'Choose the person.'])['employee_id'];
        }
        abort_if(DB::table('construction_project_workers')->where('construction_project_id', $project)->where('employee_id', $employeeId)->exists(), 422, 'This person is already on the project.');

        DB::table('construction_project_workers')->insert([
            'construction_project_id' => $project, 'employee_id' => $employeeId, 'role' => $role,
            'pay_basis' => $pay['pay_basis'], 'agreed_amount' => (float) $pay['agreed_amount'],
            'from_on' => $from, 'to_on' => $to, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $person === 'new';
    }

    public function removeWorker(Request $request, int $project, int $worker): RedirectResponse
    {
        $this->authorizeManage($request->user());
        DB::table('construction_project_workers')->where('construction_project_id', $project)->where('id', $worker)->delete();

        return back()->with('status', 'Builder removed from the project.');
    }

    public function addMaterial(Request $request, int $project): RedirectResponse
    {
        $this->authorizeManage($request->user());
        abort_unless(DB::table('construction_projects')->where('id', $project)->exists(), 404);
        $data = $request->validate(['item' => ['required', 'string', 'max:160'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'unit' => ['nullable', 'string', 'max:40'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::table('construction_materials_needed')->insert($data + ['construction_project_id' => $project, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Material added to the list.');
    }

    public function removeMaterial(Request $request, int $project, int $material): RedirectResponse
    {
        $this->authorizeManage($request->user());
        DB::table('construction_materials_needed')->where('construction_project_id', $project)->where('id', $material)->delete();

        return back()->with('status', 'Material removed from the list.');
    }

    /** Active projects, for the store issue form and Finance's entry form. */
    public static function activeProjects()
    {
        return DB::table('construction_projects')->where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    /** Materials the Store Manager issued to the project. */
    private function issued(int $projectId)
    {
        return DB::table('inventory_movements')->join('inventory_items', 'inventory_items.id', '=', 'inventory_movements.inventory_item_id')
            ->where('inventory_movements.construction_project_id', $projectId)->whereIn('inventory_movements.movement_type', ['issued', 'farm_use'])
            ->select('inventory_movements.*', 'inventory_items.name as item_name', 'inventory_items.unit as item_unit')->orderByDesc('movement_date');
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperuser() || $user->isPersonalAssistant();
    }

    private function authorizeManage(User $user): void
    {
        abort_unless($this->canManage($user), 403);
    }

    private function authorizeView(User $user): void
    {
        abort_unless($this->canManage($user) || $user->roles->pluck('code')->push($user->role?->code)->intersect(['ceo', 'director', 'finance-manager'])->isNotEmpty(), 403);
    }
}
