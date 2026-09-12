<?php

namespace App\Http\Controllers\Poultry;

use App\Services\BrandedReportExport;

use App\Http\Controllers\Controller;
use App\Models\{AnimalGroup, EggCollection, EggGrade, FeedConsumption, HealthEvent, MortalityRecord, SaleLine};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PoultryReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeReport($request);
        [$from,$to,$batchId] = $this->filters($request);
        $scope = fn ($query,$date) => $query->whereBetween($date,[$from,$to])->when($batchId,fn($q)=>$q->where('animal_group_id',$batchId));
        $eggs = $scope(EggCollection::with(['batch','house','lines.grade','recorder','approver'])->where('status','approved'),'collected_on')->latest('collected_on')->latest('collected_at')->get();
        $feed = $scope(FeedConsumption::with(['batch','type','recorder'])->where('status','approved'),'used_on')->latest('used_on')->get();
        $mortality = $scope(MortalityRecord::with(['batch','cause','recorder']),'occurred_on')->latest('occurred_on')->get();
        $health = $scope(HealthEvent::with(['batch','medication','recorder']),'occurred_on')->latest('occurred_on')->get();
        $showSales = $this->canViewPoultrySales($request);
        $sales = $showSales ? $this->poultrySales($from,$to) : collect();
        $active = AnimalGroup::where('status','active')->when($batchId,fn($q)=>$q->whereKey($batchId))->get();
        $grades = EggGrade::where('is_active', true)->ordered()->get();
        $eggRows = $this->summarizeEggs($eggs, $grades);

        return view('poultry.report.index', compact('from','to','batchId','eggs','eggRows','feed','mortality','health','sales','active','grades','showSales') + [
            'batches'=>AnimalGroup::orderBy('code')->get(),
            'pendingEggs'=>$scope(EggCollection::where('status','submitted'),'collected_on')->count(),
            'pendingFeed'=>$scope(FeedConsumption::where('status','submitted'),'used_on')->count(),
        ]);
    }

    public function eggHouseDetails(Request $request): View
    {
        $this->authorizeReport($request);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'house' => ['required', 'exists:poultry_houses,id'],
        ]);
        $collections = EggCollection::with(['batch','house','lines.grade','recorder','approver'])
            ->where('status', 'approved')->whereDate('collected_on', $data['date'])
            ->where('poultry_house_id', $data['house'])->orderBy('collected_at')->orderBy('id')->get();
        abort_if($collections->isEmpty(), 404, 'No approved egg collections were found for this house and date.');

        return view('poultry.report.egg-house-details', [
            'collections' => $collections,
            'house' => $collections->first()->house,
            'date' => $data['date'],
            'grades' => EggGrade::where('is_active', true)->ordered()->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReport($request);
        [$from,$to,$batchId] = $this->filters($request);
        $scope = fn ($query,$date) => $query->whereBetween($date,[$from,$to])->when($batchId,fn($q)=>$q->where('animal_group_id',$batchId));
        $eggs=$scope(EggCollection::with(['batch','house','lines.grade','recorder','approver'])->where('status','approved'),'collected_on')->get();
        $feed=$scope(FeedConsumption::with(['batch','type'])->where('status','approved'),'used_on')->get();
        $mortality=$scope(MortalityRecord::with(['batch','cause']),'occurred_on')->get();
        $health=$scope(HealthEvent::with(['batch','medication']),'occurred_on')->get();
        $sales=$this->canViewPoultrySales($request) ? $this->poultrySales($from,$to) : collect();
        $grades=EggGrade::where('is_active',true)->ordered()->get();
        $eggRows=$this->summarizeEggs($eggs,$grades);

        $rows=collect();
        // Non-egg rows have no worker-recorded or supervisor-approval audit
        // values; keep those three export columns empty before grade columns.
        $emptyGrades=array_merge(['','',''],array_fill(0,$grades->count()*4,''));
        foreach($eggRows as $x){$gradeCells=$grades->flatMap(function($grade)use($x){$total=(int)($x['grades'][$grade->id]??0);$perTray=max(1,(int)($grade->eggs_per_tray?:30));return [intdiv($total,$perTray),$total%$perTray,$total,round($total/$perTray,2)];})->all();$rows->push(array_merge([$x['date'],'Egg collection summary',$x['batch_code'],$x['house_number'],$x['recorded_by'],$x['first_recorded_at']?Carbon::parse($x['first_recorded_at'])->format('Y-m-d H:i:s'):'',$x['approved_by'],$x['first_approved_at']?Carbon::parse($x['first_approved_at'])->format('Y-m-d H:i:s'):''],$gradeCells,[$x['collections_count'].' collection(s)',$x['total_eggs'],'eggs','Approved']));}
        foreach($sales as $x)$rows->push(array_merge([$x->sale->sale_date->format('Y-m-d'),'Sale',$x->sale->invoice_number,'—',$x->sale->seller?->displayName()??'System'],$emptyGrades,[$x->description.' - '.$x->sale->customer_name,$x->quantity,$x->unit,$x->line_total]));
        foreach($feed as $x)$rows->push(array_merge([$x->used_on->format('Y-m-d'),'Feed',$x->batch?->code,'—',''],$emptyGrades,[$x->label(),$x->quantity_kg,'kg','Approved']));
        foreach($mortality as $x)$rows->push(array_merge([$x->occurred_on->format('Y-m-d'),'Mortality',$x->batch?->code,'—',''],$emptyGrades,[$x->cause?->name??$x->worker_note,$x->totalLost(),'birds',ucfirst(str_replace('_',' ',$x->status))]));
        foreach($health as $x)$rows->push(array_merge([$x->occurred_on->format('Y-m-d'),'Health',$x->batch?->code,'—',''],$emptyGrades,[ucfirst($x->event_type).' - '.($x->medication?->name??$x->reason),$x->birds_treated,'birds',$x->findings]));
        $gradeHeaders=$grades->flatMap(fn($grade)=>[$grade->name.' full trays',$grade->name.' loose eggs',$grade->name.' total eggs',$grade->name.' tray equivalent'])->all();
        return app(BrandedReportExport::class)->download('Poultry Unit Report',$from.' to '.$to,
            array_merge(['Date','Category','Batch / receipt','House number','Recorded by worker','Worker recorded at','Approved by supervisor','Supervisor approved at'],$gradeHeaders,['Description','Quantity','Unit','Value (UGX) / status']),
            $rows->sortBy(0)->values(),'poultry-report-'.$from.'-'.$to.'.xls',$request->user()->displayName());
    }

    private function poultrySales(string $from,string $to)
    {
        return SaleLine::with(['sale.seller','product'])
            ->whereHas('sale',fn($q)=>$q->whereBetween('sale_date',[$from,$to])->where('status','!=','void'))
            ->where(fn($q)=>$q->whereIn('category',['eggs','poultry'])->orWhereHas('product',fn($p)=>$p->whereIn('category',['eggs','poultry'])))
            ->get()->sortByDesc(fn($line)=>$line->sale->sale_date->format('Y-m-d').str_pad((string)$line->id,10,'0',STR_PAD_LEFT))->values();
    }

    private function summarizeEggs($collections, $grades)
    {
        return $collections->groupBy(fn ($collection) => $collection->collected_on->toDateString().'|'.($collection->poultry_house_id ?: 0))
            ->map(function ($entries) use ($grades) {
                $first = $entries->first();
                $gradeTotals = $grades->mapWithKeys(fn ($grade) => [
                    $grade->id => (int) $entries->sum(fn ($entry) => (int) $entry->lines->where('egg_grade_id', $grade->id)->sum('total_eggs')),
                ])->all();
                $weights = $entries->pluck('bird_weight_kg')->filter(fn ($weight) => $weight !== null);
                return [
                    'date' => $first->collected_on->toDateString(),
                    'house_id' => $first->poultry_house_id,
                    'house_number' => $first->house?->displayNumber() ?? 'Not recorded',
                    'batch_code' => $first->batch?->code ?? '—',
                    'grades' => $gradeTotals,
                    'total_eggs' => array_sum($gradeTotals),
                    'collections_count' => $entries->count(),
                    'first_time' => $entries->pluck('collected_at')->filter()->sort()->first(),
                    'last_time' => $entries->pluck('collected_at')->filter()->sort()->last(),
                    'bird_weight' => $weights->isNotEmpty() ? (float) $weights->avg() : null,
                    'recorded_by' => $entries->map(fn ($entry) => $entry->recorder?->displayName() ?? 'System')->unique()->join(', '),
                    'first_recorded_at' => $entries->pluck('recorded_at')->filter()->sort()->first(),
                    'last_recorded_at' => $entries->pluck('recorded_at')->filter()->sort()->last(),
                    'first_approved_at' => $entries->pluck('approved_at')->filter()->sort()->first(),
                    'last_approved_at' => $entries->pluck('approved_at')->filter()->sort()->last(),
                    'approved_by' => $entries->map(fn ($entry) => $entry->approver?->displayName() ?? 'System')->unique()->join(', '),
                ];
            })->sortByDesc(fn ($row) => $row['date'].'|'.str_pad((string)$row['house_number'],10,'0',STR_PAD_LEFT))->values();
    }

    private function filters(Request $request): array
    {
        $from=Carbon::parse($request->query('from',now()->startOfMonth()->toDateString()))->toDateString();
        $to=Carbon::parse($request->query('to',now()->toDateString()))->toDateString();
        abort_if($from>$to,422,'The start date must be before the end date.');
        return [$from,$to,$request->integer('batch')?:null];
    }

    private function authorizeReport(Request $request): void
    {
        abort_unless($request->user()->canViewAllReports()||$request->user()->canAccess('production')||$request->user()->canAccess('batches')||$request->user()->canAccess('egg-inventory'),403);
    }

    private function canViewPoultrySales(Request $request): bool
    {
        $roles = $request->user()->roles->pluck('code')->push($request->user()->role?->code)->filter();
        return $roles->intersect(['sales-manager', 'finance-manager', 'ceo', 'director', 'personal-assistant'])->isNotEmpty();
    }
}
