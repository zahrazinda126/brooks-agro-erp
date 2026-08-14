<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Models\Communication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExecutiveController extends Controller
{
    private const TITLES = [
        'kpis' => 'KPIs & Analytics',
        'reports' => 'Executive Reports',
        'messages' => 'Messages & Announcements',
        'notifications' => 'Executive Notifications',
        'security' => 'Users & Permissions',
        'settings' => 'System Settings',
    ];

    public function index(Request $request, string $screen): View
    {
        abort_unless(isset(self::TITLES[$screen]), 404);
        $this->authorizeExecutive($request, $screen);

        $month = FinancialTransaction::whereBetween('transaction_date', [now()->startOfMonth(), now()->endOfMonth()])->get();
        $trend = collect(range(5, 0))->map(function (int $monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $rows = FinancialTransaction::whereBetween('transaction_date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->get();
            return ['label'=>$date->format('M'), 'income'=>(float)$rows->where('type','money_received')->sum('amount'), 'spending'=>(float)$rows->where('type','money_paid')->sum('amount')];
        });
        $data = match ($screen) {
            'kpis' => [
                'income' => (float) $month->where('type', 'money_received')->sum('amount'),
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

        return view('executive.index', compact('screen', 'data') + ['title' => self::TITLES[$screen]]);
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
        $isAssistant = $codes->contains('personal-assistant');
        $assistantScreen = $screen === null || in_array($screen, ['reports', 'messages', 'notifications'], true);
        abort_unless($isExecutive || ($isAssistant && $assistantScreen), 403);
    }
}
