<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Department;
use App\Services\WeeklyBudgetDraftService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentBudgetController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('budget'), 403);
        $department = $this->department($request);
        $start = $request->date('week')?->startOfWeek() ?? now()->startOfWeek();
        if ($start->isSameDay(now()->startOfWeek())) {
            app(WeeklyBudgetDraftService::class)->ensureForWeek($start, $request->user()->id, $department->id);
        }

        return view('budgets.department-draft', [
            'department' => $department,
            'start' => $start,
            'end' => $start->copy()->endOfWeek(),
            'lines' => Budget::where('department_id', $department->id)->whereDate('period_start', $start)->orderBy('category')->orderBy('id')->get(),
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
        $data = $request->validate(['quantity'=>['required','numeric','min:0'],'unit_price'=>['required','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);
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

    private function department(Request $request): Department
    {
        $department = $request->user()->department ?? $request->user()->departments->first();
        abort_unless($department, 422, 'Your account must be assigned to a department before preparing its budget.');
        return $department;
    }
}
