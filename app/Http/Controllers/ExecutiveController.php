<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Role;
use App\Models\Module;
use App\Models\Communication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ExecutiveController extends Controller
{
    private const TITLES = [
        'kpis' => 'KPIs & Analytics',
        'reports' => 'Financial & Executive Reports',
        'messages' => 'Messages & Announcements',
        'notifications' => 'Executive Notifications',
        'security' => 'Users & Permissions',
        'settings' => 'Company & System Settings',
    ];

    public function index(Request $request, string $screen): View
    {
        abort_unless(isset(self::TITLES[$screen]), 404);
        $this->authorizeExecutive($request, $screen);

        $month = FinancialTransaction::whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])->get();
        $trend = collect(range(5, 0))->map(function (int $monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $rows = FinancialTransaction::whereBetween('transaction_date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->get();
            return ['label'=>$date->format('M'), 'income'=>(float)$rows->filter(fn ($row) => ($row->type === 'money_received' && $row->transaction_category !== 'customer_prepayment') || $row->type === 'revenue_recognized')->sum('amount'), 'spending'=>(float)$rows->where('type','money_paid')->sum('amount')];
        });
        $data = match ($screen) {
            'kpis' => [
                'income' => (float) $month->filter(fn ($row) => ($row->type === 'money_received' && $row->transaction_category !== 'customer_prepayment') || $row->type === 'revenue_recognized')->sum('amount'),
                'spending' => (float) $month->where('type', 'money_paid')->sum('amount'),
                'pending' => Approval::where('status', 'pending')->count(),
                'overdue' => Approval::where('status', 'pending')->where('due_at', '<', now())->count(),
                'employees' => Employee::where('status', 'active')->count(),
                'assets' => DB::table('fixed_assets')->count(),
                'purchases' => (float) DB::table('purchases')->whereIn('status', ['submitted', 'finance_reviewed', 'ceo_approved'])->sum('total_cost'),
                'trend' => $trend,
            ],
            'messages' => DB::table('executive_communications')->join('users', 'users.id', '=', 'executive_communications.created_by')->latest('executive_communications.created_at')->select('executive_communications.*', 'users.name as sender')->paginate(30),
            'notifications' => [
                'approvals' => Approval::with('steps')->where('status', 'pending')->latest()->limit(25)->get(),
                'overdue' => Approval::where('status', 'pending')->where('due_at', '<', now())->count(),
                'rejected' => Approval::with(['decisionMaker', 'steps.decisionMaker'])->where('status', 'rejected')->latest('decided_at')->limit(15)->get(),
                'communications' => DB::table('executive_communications')->latest()->limit(10)->get(),
            ],
            'security' => User::with(['role', 'department'])->orderBy('name')->get(),
            'settings' => DB::table('settings')->orderBy('key')->get(),
            default => [],
        };

        $permissionData = null;
        if ($screen === 'security' && $this->isDirector($request)) {
            $roles = Role::where('is_active', true)->where('rank', '>', $request->user()->effectiveRank())
                ->where('is_superuser', false)->orderBy('rank')->orderBy('name')->get();
            $selectedRole = $roles->firstWhere('id', (int) $request->query('role')) ?: $roles->first();
            $modules = Module::where('is_active', true)->where('is_container', false)
                ->orderBy('group')->orderBy('sort_order')->orderBy('name')->get();
            $grants = $selectedRole
                ? DB::table('module_role')->where('role_id', $selectedRole->id)->get()->keyBy('module_id')
                : collect();
            $permissionData = compact('roles', 'selectedRole', 'modules', 'grants');
        }

        return view('executive.index', compact('screen', 'data', 'permissionData') + ['title' => self::TITLES[$screen]]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        abort_unless($this->isDirector($request), 403, 'Only the Director can change access permissions.');
        abort_if($role->is_superuser || $role->rank <= $request->user()->effectiveRank(), 403, 'This role is protected and cannot be changed here.');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['in:1'],
        ]);
        $modules = Module::where('is_active', true)->where('is_container', false)->get();
        $before = DB::table('module_role')->where('role_id', $role->id)->get()
            ->mapWithKeys(fn ($row) => [$row->module_id => ['view'=>(bool)$row->can_view,'create'=>(bool)$row->can_create,'edit'=>(bool)$row->can_edit,'delete'=>(bool)$row->can_delete,'approve'=>(bool)$row->can_approve]])->all();

        DB::transaction(function () use ($modules, $role, $data) {
            foreach ($modules as $module) {
                $given = $data['permissions'][$module->id] ?? [];
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $role->id, 'module_id' => $module->id],
                    [
                        'can_view' => isset($given['view']), 'can_create' => isset($given['create']),
                        'can_edit' => isset($given['edit']), 'can_delete' => isset($given['delete']),
                        'can_approve' => isset($given['approve']), 'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
        $after = DB::table('module_role')->where('role_id', $role->id)->get()
            ->mapWithKeys(fn ($row) => [$row->module_id => ['view'=>(bool)$row->can_view,'create'=>(bool)$row->can_create,'edit'=>(bool)$row->can_edit,'delete'=>(bool)$row->can_delete,'approve'=>(bool)$row->can_approve]])->all();
        AuditLog::record('role_permissions_updated', $role, "Updated access permissions for {$role->name}.", $before, $after, 'security');

        return redirect()->route('executive.index', ['screen'=>'security', 'role'=>$role->id])->with('status', "{$role->name} permissions updated.");
    }

    /** Reset an account even when it has no linked employee record. */
    public function resetAccountPassword(Request $request, User $account): RedirectResponse
    {
        abort_unless($this->isSystemAdministrator($request), 403,
            'Only the System Administrator can reset senior account passwords.');
        abort_if($account->id === $request->user()->id, 403,
            'Use Edit account to change your own password.');

        $temporary = $this->temporaryPassword();
        $account->forceFill([
            'password' => Hash::make($temporary),
            'must_change_password' => true,
            'password_changed_at' => null,
            'password_expires_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'is_active' => true,
        ])->save();

        AuditLog::record('password_reset', $account,
            "System Administrator reset the password for '{$account->username}'.",
            null, ['account_id' => $account->id, 'account_role' => $account->role?->code], 'security');

        return redirect()->route('executive.index', ['screen' => 'security'])
            ->with('credentials', ['username' => $account->username, 'password' => $temporary]);
    }

    public function storeMessage(Request $request): RedirectResponse
    {
        $this->authorizeExecutive($request);
        $data = $request->validate([
            'kind' => ['required', 'in:message,announcement'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', 'in:management,all_staff,finance,hr,production,sales,individual'],
            'recipient_id' => ['nullable', 'required_if:audience,individual', 'exists:users,id'],
        ]);
        Communication::create($data + ['created_by'=>$request->user()->id]);
        AuditLog::record('executive_communication_sent', null, $data['subject'], null, ['audience'=>$data['audience']], 'messages');
        return back()->with('status', ucfirst($data['kind']).' published.');
    }

    private function authorizeExecutive(Request $request, ?string $screen = null): void
    {
        $codes = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        $isExecutive = $codes->contains(fn ($code) => in_array($code, ['ceo', 'director'], true));
        $isSystemAdministrator = $codes->contains('system-admin');
        $isAssistant = $codes->contains('personal-assistant');
        $assistantScreen = $screen === null || in_array($screen, ['reports', 'messages', 'notifications'], true);
        $administratorScreen = in_array($screen, ['security', 'settings'], true);
        abort_unless($isExecutive || ($isAssistant && $assistantScreen) || ($isSystemAdministrator && $administratorScreen), 403);
    }

    private function isDirector(Request $request): bool
    {
        return $request->user()->roles->contains('code', 'director') || $request->user()->role?->code === 'director';
    }

    private function isSystemAdministrator(Request $request): bool
    {
        return $request->user()->roles->contains('code', 'system-admin')
            || $request->user()->role?->code === 'system-admin';
    }

    private function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $password = '';

        for ($i = 0; $i < 6; $i++) $password .= $letters[random_int(0, strlen($letters) - 1)];
        for ($i = 0; $i < 3; $i++) $password .= $digits[random_int(0, strlen($digits) - 1)];

        return ucfirst($password);
    }
}
