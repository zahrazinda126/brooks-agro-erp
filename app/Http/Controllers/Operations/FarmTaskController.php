<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paid farm tasks. The Production Manager assigns a task (manure collection, spraying …)
 * to a worker for a period; while it lasts the worker records the work from their own
 * account; a supervisor confirms it in Daily Collection Approvals. Payment is made by
 * Finance through its normal entry point.
 */
class FarmTaskController extends Controller
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden'];

    /** Roles that manage rather than do paid tasks. */
    private const NOT_WORKERS = ['ceo', 'director', 'personal-assistant', 'pa', 'system-admin', 'store-keeper'];

    /* ---------------- Production Manager ---------------- */

    public function index(Request $request): View
    {
        $this->authorizeManager($request->user());
        $assignments = DB::table('farm_task_assignments')
            ->join('farm_task_types', 'farm_task_types.id', '=', 'farm_task_assignments.farm_task_type_id')
            ->join('users', 'users.id', '=', 'farm_task_assignments.user_id')
            ->select('farm_task_assignments.*', 'farm_task_types.name as task_name', 'users.name as worker_name', 'users.username as worker_username')
            ->orderByRaw('farm_task_assignments.ended_at is not null')->orderByDesc('farm_task_assignments.starts_on')->get()
            ->map(function ($a) { $a->active = self::isActive($a); return $a; });

        return view('operations.farm-tasks.index', [
            'show' => $request->query('show') === 'entries' ? 'entries' : 'assignments',
            'assignments' => $assignments,
            'entries' => DB::table('farm_task_entries')->join('users', 'users.id', '=', 'farm_task_entries.recorded_by')
                ->select('farm_task_entries.*', 'users.name as worker_name')->orderByDesc('done_on')->orderByDesc('farm_task_entries.id')->limit(200)->get(),
            'types' => DB::table('farm_task_types')->leftJoin('sale_products', 'sale_products.id', '=', 'farm_task_types.sale_product_id')
                ->select('farm_task_types.*', 'sale_products.name as product_name')->orderBy('farm_task_types.name')->get(),
            'workers' => $this->workers(),
            'products' => DB::table('sale_products')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
            'units' => self::UNITS,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManager($user);
        $data = $request->validate([
            'farm_task_type_id' => ['required', Rule::exists('farm_task_types', 'id')->where('is_active', true)],
            'user_id' => ['required', Rule::in($this->workers()->pluck('id')->all())],
            'unit_code' => ['required', Rule::in(array_keys(self::UNITS))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['user_id.in' => 'Choose a farm worker with an account. Managers are not given paid tasks.']);
        $id = DB::table('farm_task_assignments')->insertGetId($data + ['assigned_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $task = DB::table('farm_task_types')->where('id', $data['farm_task_type_id'])->value('name');
        $worker = User::find($data['user_id']);
        AuditLog::record('farm_task_assigned', null, "{$task} assigned to {$worker->displayName()}.", null, ['assignment_id' => $id] + $data, 'production');
        app(\App\Services\CommunicationService::class)->toUser($worker->id, "Task: {$task}", "You have been given the task \"{$task}\". Record the work from your account (My Work) when you do it.", 'workflow', $user->id, route('daily.index', [], false));

        return back()->with('status', "{$task} assigned to {$worker->displayName()}.");
    }

    public function end(Request $request, int $assignment): RedirectResponse
    {
        $this->authorizeManager($request->user());
        $row = DB::table('farm_task_assignments')->find($assignment);
        abort_unless($row && ! $row->ended_at, 404);
        DB::table('farm_task_assignments')->where('id', $assignment)->update(['ended_at' => now(), 'ended_by' => $request->user()->id, 'updated_at' => now()]);
        AuditLog::record('farm_task_ended', null, 'Paid task assignment ended.', null, ['assignment_id' => $assignment], 'production');

        return back()->with('status', 'Task ended. It no longer shows in the worker\'s account.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizeManager($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:farm_task_types,name'],
            'sale_product_id' => ['nullable', 'exists:sale_products,id'],
            'measure' => ['nullable', 'required_with:sale_product_id', 'string', 'max:40'],
        ], ['measure.required_with' => 'Say how the product is measured (e.g. kg or bags).']);
        DB::table('farm_task_types')->insert($data + ['is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', "Task \"{$data['name']}\" added.");
    }

    /* ---------------- Worker ---------------- */

    /** Active tasks for the signed-in worker (shown on My Work). */
    public static function activeFor(User $user): Collection
    {
        return DB::table('farm_task_assignments')->join('farm_task_types', 'farm_task_types.id', '=', 'farm_task_assignments.farm_task_type_id')
            ->where('farm_task_assignments.user_id', $user->id)->whereNull('farm_task_assignments.ended_at')
            ->whereDate('starts_on', '<=', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->select('farm_task_assignments.*', 'farm_task_types.name as task_name', 'farm_task_types.measure', 'farm_task_types.sale_product_id')->get();
    }

    public function record(Request $request, int $assignment): View
    {
        $task = $this->workerAssignment($request->user(), $assignment);

        return view('operations.farm-tasks.record', [
            'task' => $task, 'unitName' => self::UNITS[$task->unit_code] ?? $task->unit_code,
            'recent' => DB::table('farm_task_entries')->where('farm_task_assignment_id', $task->id)->orderByDesc('done_on')->limit(10)->get(),
        ]);
    }

    public function storeEntry(Request $request, int $assignment): RedirectResponse
    {
        $user = $request->user();
        $task = $this->workerAssignment($user, $assignment);
        $data = $request->validate([
            'done_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.substr((string) $task->starts_on, 0, 10)],
            'quantity' => [$task->measure ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'details' => [$task->measure ? 'nullable' : 'required', 'string', 'max:2000'],
        ], ['details.required' => 'Say what was done and where.', 'quantity.required' => 'Enter how much was collected.']);
        $id = DB::table('farm_task_entries')->insertGetId([
            'farm_task_assignment_id' => $task->id, 'farm_task_type_id' => $task->farm_task_type_id, 'task_name' => $task->task_name,
            'unit_code' => $task->unit_code, 'done_on' => $data['done_on'], 'quantity' => $task->measure ? $data['quantity'] : null,
            'measure' => $task->measure, 'details' => $data['details'] ?? null, 'recorded_by' => $user->id,
            'verification_status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::record('farm_task_recorded', null, "{$task->task_name} recorded by {$user->displayName()}.", null, ['entry_id' => $id] + $data, 'production');
        app(\App\Services\CommunicationService::class)->recordAwaitingReview($task->unit_code, $data['done_on'], $task->task_name.($task->measure ? ': '.$data['quantity'].' '.$task->measure : ''), $user->id);

        return redirect()->route('daily.index')->with('status', "{$task->task_name} recorded. A supervisor will confirm it.");
    }

    /* ---------------- helpers ---------------- */

    /** Active staff with a login who are not managers. */
    private function workers(): Collection
    {
        return User::with(['role', 'roles'])->where('is_active', true)->whereNotNull('employee_id')->orderBy('name')->get()
            ->reject(function (User $u) {
                $codes = $u->roles->pluck('code')->push($u->role?->code)->filter();
                return $u->isSuperuser() || $codes->contains(fn ($c) => str_contains($c, 'manager') || in_array($c, self::NOT_WORKERS, true));
            })->values();
    }

    private function workerAssignment(User $user, int $assignment): object
    {
        $task = self::activeFor($user)->firstWhere('id', $assignment);
        abort_unless($task, 403, 'This task is not assigned to you, or it has ended.');
        return $task;
    }

    private static function isActive(object $a): bool
    {
        return ! $a->ended_at && \Illuminate\Support\Carbon::parse($a->starts_on)->lte(today())
            && (! $a->ends_on || \Illuminate\Support\Carbon::parse($a->ends_on)->gte(today()));
    }

    private function authorizeManager(User $user): void
    {
        $codes = $user->roles->pluck('code')->push($user->role?->code);
        abort_unless($user->isSuperuser() || $codes->contains('production-manager'), 403);
    }
}
