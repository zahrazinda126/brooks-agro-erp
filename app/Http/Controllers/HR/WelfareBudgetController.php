<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WelfareBudgetController extends Controller
{
    private const CATEGORY = 'Staff Welfare';

    private const TEMPLATE = [
        ['Staff meals and refreshments', 'people'],
        ['Staff medical and emergency support', 'allowance'],
        ['Protective clothing and PPE', 'sets'],
        ['Staff transport support', 'trips'],
        ['Staff accommodation and welfare supplies', 'items'],
        ['Training, meetings and staff engagement', 'sessions'],
        ['Other approved staff welfare requirement', 'item'],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('budget'), 403);
        $start = $request->date('week')?->startOfWeek() ?? now()->startOfWeek();
        $lines = Budget::where('category', self::CATEGORY)->whereDate('period_start', $start)->orderBy('id')->get();
        $periods = Budget::where('category', self::CATEGORY)->select('period_start', 'period_end', 'status')
            ->selectRaw('count(*) as line_count, sum(planned_amount) as planned_total')
            ->groupBy('period_start', 'period_end', 'status')->orderByDesc('period_start')->get();

        return view('hr.budgets.welfare', [
            'lines'=>$lines, 'periods'=>$periods, 'start'=>$start, 'end'=>$start->copy()->endOfWeek(),
            'locked'=>Budget::whereDate('period_start', $start)->where('status','!=','draft')->exists(),
        ]);
    }

    public function loadTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget','create') || $request->user()->canDo('budget','edit'), 403);
        $start = Carbon::parse($request->validate(['week'=>['required','date']])['week'])->startOfWeek();
        $this->ensureDraft($start);
        foreach (self::TEMPLATE as [$item, $unit]) {
            Budget::firstOrCreate(
                ['category'=>self::CATEGORY, 'item'=>$item, 'period_start'=>$start->toDateString()],
                ['unit'=>$unit, 'quantity'=>1, 'unit_price'=>0, 'planned_amount'=>0,
                 'period_end'=>$start->copy()->endOfWeek()->toDateString(), 'status'=>'draft', 'recorded_by'=>$request->user()->id]
            );
        }
        return redirect()->route('hr.welfare-budget.index',['week'=>$start->toDateString()])->with('status','Staff welfare template loaded. Enter only what is required this week.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget','create') || $request->user()->canDo('budget','edit'), 403);
        $data=$request->validate(['item'=>['required','string','max:180'],'unit'=>['nullable','string','max:40'],'quantity'=>['required','numeric','min:0.01'],'unit_price'=>['required','numeric','min:0'],'notes'=>['nullable','string','max:2000'],'week'=>['required','date']]);
        $start=Carbon::parse($data['week'])->startOfWeek(); unset($data['week']); $this->ensureDraft($start);
        Budget::create($data+['category'=>self::CATEGORY,'planned_amount'=>$data['quantity']*$data['unit_price'],'period_start'=>$start->toDateString(),'period_end'=>$start->copy()->endOfWeek()->toDateString(),'status'=>'draft','recorded_by'=>$request->user()->id]);
        return back()->with('status','Staff welfare budget item added.');
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget','edit'), 403);
        abort_unless($budget->category===self::CATEGORY && $budget->status==='draft', 403);
        $data=$request->validate(['quantity'=>['required','numeric','min:0'],'unit_price'=>['required','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);
        $budget->update($data+['planned_amount'=>$data['quantity']*$data['unit_price']]);
        return back()->with('status','Staff welfare budget saved.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($request->user()->canDo('budget','edit'), 403);
        abort_unless($budget->category===self::CATEGORY && $budget->status==='draft', 403);
        $budget->delete();
        return back()->with('status','Staff welfare budget item removed.');
    }

    private function ensureDraft(Carbon $start): void
    {
        abort_if(Budget::whereDate('period_start',$start)->where('status','!=','draft')->exists(), 422, 'Finance has already submitted this weekly budget.');
    }
}
