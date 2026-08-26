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
        $eggs = $scope(EggCollection::with(['batch','house','lines.grade','recorder'])->where('status','approved'),'collected_on')->latest('collected_on')->latest('collected_at')->get();
        $feed = $scope(FeedConsumption::with(['batch','type','recorder'])->where('status','approved'),'used_on')->latest('used_on')->get();
        $mortality = $scope(MortalityRecord::with(['batch','cause','recorder']),'occurred_on')->latest('occurred_on')->get();
        $health = $scope(HealthEvent::with(['batch','medication','recorder']),'occurred_on')->latest('occurred_on')->get();
        $showSales = $this->canViewPoultrySales($request);
        $sales = $showSales ? $this->poultrySales($from,$to) : collect();
        $active = AnimalGroup::where('status','active')->when($batchId,fn($q)=>$q->whereKey($batchId))->get();
        $grades = EggGrade::where('is_active', true)->ordered()->get();

        return view('poultry.report.index', compact('from','to','batchId','eggs','feed','mortality','health','sales','active','grades','showSales') + [
            'batches'=>AnimalGroup::orderBy('code')->get(),
            'pendingEggs'=>$scope(EggCollection::where('status','submitted'),'collected_on')->count(),
            'pendingFeed'=>$scope(FeedConsumption::where('status','submitted'),'used_on')->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReport($request);
        [$from,$to,$batchId] = $this->filters($request);
        $scope = fn ($query,$date) => $query->whereBetween($date,[$from,$to])->when($batchId,fn($q)=>$q->where('animal_group_id',$batchId));
        $eggs=$scope(EggCollection::with(['batch','house','lines.grade','recorder'])->where('status','approved'),'collected_on')->get();
        $feed=$scope(FeedConsumption::with(['batch','type'])->where('status','approved'),'used_on')->get();
        $mortality=$scope(MortalityRecord::with(['batch','cause']),'occurred_on')->get();
        $health=$scope(HealthEvent::with(['batch','medication']),'occurred_on')->get();
        $sales=$this->canViewPoultrySales($request) ? $this->poultrySales($from,$to) : collect();
        $grades=EggGrade::where('is_active',true)->ordered()->get();

        $rows=collect();
        $emptyGrades=array_fill(0,$grades->count()*4,'');
        foreach($eggs as $x){$byGrade=$x->lines->keyBy('egg_grade_id');$gradeCells=$grades->flatMap(function($grade)use($byGrade){$total=(int)($byGrade->get($grade->id)?->total_eggs??0);$perTray=max(1,(int)($grade->eggs_per_tray?:30));return [intdiv($total,$perTray),$total%$perTray,$total,round($total/$perTray,2)];})->all();$rows->push(array_merge([$x->collected_on->format('Y-m-d').' '.($x->collected_at?\Illuminate\Support\Carbon::parse($x->collected_at)->format('H:i'):''),'Egg collection',$x->batch?->code,$x->house?->displayNumber(),$x->recorder?->displayName()],$gradeCells,['',$x->total_eggs,'eggs','Approved']));}
        foreach($sales as $x)$rows->push(array_merge([$x->sale->sale_date->format('Y-m-d'),'Sale',$x->sale->invoice_number,'—',$x->sale->seller?->displayName()??'System'],$emptyGrades,[$x->description.' - '.$x->sale->customer_name,$x->quantity,$x->unit,$x->line_total]));
        foreach($feed as $x)$rows->push(array_merge([$x->used_on->format('Y-m-d'),'Feed',$x->batch?->code,'—',''],$emptyGrades,[$x->label(),$x->quantity_kg,'kg','Approved']));
        foreach($mortality as $x)$rows->push(array_merge([$x->occurred_on->format('Y-m-d'),'Mortality',$x->batch?->code,'—',''],$emptyGrades,[$x->cause?->name??$x->worker_note,$x->totalLost(),'birds',ucfirst(str_replace('_',' ',$x->status))]));
        foreach($health as $x)$rows->push(array_merge([$x->occurred_on->format('Y-m-d'),'Health',$x->batch?->code,'—',''],$emptyGrades,[ucfirst($x->event_type).' - '.($x->medication?->name??$x->reason),$x->birds_treated,'birds',$x->findings]));
        $gradeHeaders=$grades->flatMap(fn($grade)=>[$grade->name.' full trays',$grade->name.' loose eggs',$grade->name.' total eggs',$grade->name.' tray equivalent'])->all();
        return app(BrandedReportExport::class)->download('Poultry Unit Report',$from.' to '.$to,
            array_merge(['Date and time','Category','Batch / receipt','House number','Recorded by'],$gradeHeaders,['Description','Quantity','Unit','Value (UGX) / status']),
            $rows->sortBy(0)->values(),'poultry-report-'.$from.'-'.$to.'.xls',$request->user()->displayName());
    }

    private function poultrySales(string $from,string $to)
    {
        return SaleLine::with(['sale.seller','product'])
            ->whereHas('sale',fn($q)=>$q->whereBetween('sale_date',[$from,$to])->where('status','!=','void'))
            ->where(fn($q)=>$q->whereIn('category',['eggs','poultry'])->orWhereHas('product',fn($p)=>$p->whereIn('category',['eggs','poultry'])))
            ->get()->sortByDesc(fn($line)=>$line->sale->sale_date->format('Y-m-d').str_pad((string)$line->id,10,'0',STR_PAD_LEFT))->values();
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
