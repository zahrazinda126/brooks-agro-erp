<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Department;
use App\Services\WeeklyBudgetDraftService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The one weekly budget draft format used by every account that prepares a budget
 * (Production, HR, Finance, Administration). The draft is always there for the current
 * week, stays editable, and is submitted to Finance, who combines all departments.
 */
class DepartmentBudgetController extends Controller
{
    /** Which department's budget a manager prepares, by role. */
    private const ROLE_DEPARTMENTS = ['production-manager' => 'production', 'hr-manager' => 'hr', 'finance-manager' => 'finance'];

    /** Suggested sections per department (Finance groups the combined budget by these). */
    private const SECTIONS = [
        'production' => ['Poultry', 'Goat Unit', 'Rabbits Unit', 'Dogs Unit', 'Kituntu Garden', 'Kituntu Tomato Garden', 'Kituntu Passion Fruits'],
        'hr' => ['Staff welfare', 'Administration', 'Facilities'],
        'finance' => ['Finance operations', 'Administration'],
        'administration' => ['Administration', 'Facilities'],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('budget'), 403);
        $department = $this->department($request);
        $start = $request->date('week')?->startOfWeek() ?? now()->startOfWeek();
        if ($start->isSameDay(now()->startOfWeek())) {
            app(WeeklyBudgetDraftService::class)->ensureForWeek($start, $request->user()->id, $department->id);
            if (! Budget::whereDate('period_start', $start)->where('status', '!=', 'draft')->exists()) {
                app(WeeklyBudgetDraftService::class)->ensureUnitStarters($start, $request->user()->id, $department->id);
            }
        }

        $lines = Budget::where('department_id', $department->id)->whereDate('period_start', $start)->get();
        $submission = DB::table('weekly_budget_submissions')->where('department_id', $department->id)->whereDate('week_start', $start)->first();

        return view('budgets.department-draft', [
            'department' => $department,
            'sections' => self::SECTIONS[$department->code] ?? [$department->name],
            'submission' => $submission,
            'changedSinceSubmit' => $submission && ($lines->max('updated_at')?->gt($submission->submitted_at) || $lines->count() !== (int) $submission->line_count
                || abs((float) $lines->sum('planned_amount') - (float) $submission->total) > 0.004),
            'start' => $start,
            'end' => $start->copy()->endOfWeek(),
            'lines' => Budget::where('department_id', $department->id)->whereDate('period_start', $start)
                ->orderByRaw("case when carryover_type = 'unfunded' then 0 else 1 end")
                ->orderBy('category')->orderBy('id')->get(),
            'periods' => Budget::where('department_id', $department->id)->select('period_start', 'period_end', 'status')->selectRaw('count(*) as line_count')->groupBy('period_start', 'period_end', 'status')->orderByDesc('period_start')->get(),
            'locked' => Budget::whereDate('period_start', $start)->where('status', '!=', 'draft')->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'create') || $request->user()->canDo('budget', 'edit'), 403);
        $department = $this->department($request);
        $data = $request->validate(['category'=>['nullable','string','max:120'],'item'=>['required','string','max:180'],'unit'=>['nullable','string','max:40'],'quantity'=>['required','numeric','min:0.01'],'unit_price'=>['required','numeric','min:0'],'notes'=>['nullable','string','max:2000'],'week'=>['required','date']]);
        $start = Carbon::parse($data['week'])->startOfWeek(); unset($data['week']);
        abort_if(Budget::whereDate('period_start', $start)->where('status', '!=', 'draft')->exists(), 422, 'Finance has already submitted this combined weekly budget.');
        Budget::create($data + ['department_id'=>$department->id,'category'=>$data['category'] ?: $department->name,'planned_amount'=>$data['quantity']*$data['unit_price'],'period_start'=>$start->toDateString(),'period_end'=>$start->copy()->endOfWeek()->toDateString(),'status'=>'draft','recorded_by'=>$request->user()->id]);
        return back()->with('status', 'Department budget item added.');
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'edit'), 403);
        abort_unless($budget->department_id === $this->department($request)->id && $budget->status === 'draft', 403);
        $data = $request->validate(['quantity'=>['required','numeric','min:0'],'unit_price'=>['required','numeric','min:0'],'notes'=>['nullable','string','max:2000'],'category'=>['nullable','string','max:120']]);
        if (empty($data['category'])) unset($data['category']); // "Move to…" left empty keeps the section
        $budget->update($data + ['planned_amount'=>$data['quantity']*$data['unit_price']]);
        return back()->with('status', 'Department draft updated.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget', 'delete') || $request->user()->canDo('budget', 'edit'), 403);
        abort_unless($budget->department_id === $this->department($request)->id && $budget->status === 'draft', 403);
        $budget->delete();
        return back()->with('status', 'Department budget item removed.');
    }

    /** Send this week's draft to Finance (again, after changes). */
    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canDo('budget', 'create') || $user->canDo('budget', 'edit'), 403);
        $department = $this->department($request);
        $start = Carbon::parse($request->validate(['week' => ['required', 'date']])['week'])->startOfWeek();
        abort_if(Budget::whereDate('period_start', $start)->where('status', '!=', 'draft')->exists(), 422, 'Finance has already submitted this combined weekly budget.');
        $lines = Budget::where('department_id', $department->id)->whereDate('period_start', $start)->get();
        if ($lines->isEmpty()) return back()->withErrors(['budget' => 'Add at least one requirement before submitting.']);

        $resubmit = DB::table('weekly_budget_submissions')->where('department_id', $department->id)->whereDate('week_start', $start)->exists();
        DB::table('weekly_budget_submissions')->updateOrInsert(
            ['department_id' => $department->id, 'week_start' => $start->toDateString()],
            ['line_count' => $lines->count(), 'total' => $lines->sum('planned_amount'), 'submitted_by' => $user->id, 'submitted_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        \App\Models\AuditLog::record($resubmit ? 'department_budget_resubmitted' : 'department_budget_submitted', null,
            "{$department->name} weekly budget for {$start->format('d M Y')} ".($resubmit ? 're' : '')."submitted to Finance.", null,
            ['department' => $department->code, 'week' => $start->toDateString(), 'lines' => $lines->count(), 'total' => $lines->sum('planned_amount')], 'budget');
        app(\App\Services\CommunicationService::class)->toRole('finance-manager', "{$department->name} weekly budget ".($resubmit ? 'resubmitted' : 'submitted'),
            $user->displayName()." ".($resubmit ? 'resubmitted' : 'submitted')." the {$department->name} budget for the week of {$start->format('d M Y')}: ".$lines->count().' line(s), UGX '.number_format($lines->sum('planned_amount')).'.',
            $user->id, route('finance.budgets.index', ['week' => $start->toDateString()], false));

        return back()->with('status', "{$department->name} budget ".($resubmit ? 'resubmitted' : 'submitted').' to Finance.');
    }

    private function department(Request $request): Department
    {
        $user = $request->user();
        $roleCodes = $user->roles->pluck('code')->push($user->role?->code)->filter();
        $byRole = collect(self::ROLE_DEPARTMENTS)->first(fn ($code, $role) => $roleCodes->contains($role));
        $department = ($byRole ? Department::where('code', $byRole)->first() : null) ?? $user->department ?? $user->departments->first();
        abort_unless($department, 422, 'Your account must be assigned to a department before preparing its budget.');
        return $department;
    }
}
