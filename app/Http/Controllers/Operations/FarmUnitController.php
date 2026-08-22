<?php

namespace App\Http\Controllers\Operations;

use App\Services\FeedStockService;
use App\Services\BrandedReportExport;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Breed;
use App\Models\FarmUnitRecord;
use App\Models\LivestockAnimal;
use App\Models\EggCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmUnitController extends Controller
{
    public const UNITS = [
        'goats' => ['name'=>'Goat Unit','icon'=>'🐐','types'=>['animal_register'=>'Animal Register','breeding'=>'Breeding','kidding'=>'Kidding & Births','milk'=>'Milk Production','feeding'=>'Feeding','weight'=>'Weight Check','treatment'=>'Treatment','vaccination'=>'Vaccination','mortality'=>'Mortality','sale'=>'Sale']],
        'rabbits' => ['name'=>'Rabbit Unit','icon'=>'🐇','types'=>['stock_register'=>'Rabbit Register','breeding'=>'Breeding','kindling'=>'Kindling & Births','feeding'=>'Feeding','weight'=>'Weight Check','treatment'=>'Treatment','vaccination'=>'Vaccination','mortality'=>'Mortality','sale'=>'Sale']],
        'dogs' => ['name'=>'Dog Unit','icon'=>'🐕','types'=>['dog_register'=>'Dog Register','feeding'=>'Feeding & Care','training'=>'Training','breeding'=>'Breeding','whelping'=>'Whelping & Puppies','treatment'=>'Treatment','vaccination'=>'Vaccination','weight'=>'Weight Check','incident'=>'Incident','mortality'=>'Mortality','sale'=>'Sale']],
        'garden' => ['name'=>'Garden Unit','icon'=>'🌱','types'=>['plot'=>'Plot Register','planting'=>'Planting','field_activity'=>'Field Activity','input_application'=>'Input Application','irrigation'=>'Irrigation','harvest'=>'Harvest','loss'=>'Crop Loss','sale'=>'Produce Sale']],
    ];

    public function index(Request $request, string $unit): View
    {
        $config = self::UNITS[$unit] ?? abort(404);
        abort_unless($request->user()->canAccessFarmUnit($unit), 403);
        if (in_array($unit,['goats','rabbits','dogs'],true) && ! $request->filled('type') && ! $request->filled('view')) return $this->livestockOverview($request,$unit,$config);
        $type = $request->string('type')->toString();
        $query = FarmUnitRecord::with('recorder')->where('unit_code',$unit)->latest('record_date')->latest('id');
        if ($type && isset($config['types'][$type])) $query->where('record_type',$type);
        if ($request->filled('view')) $query->whereIn('record_type', $this->viewTypes($unit, $request->string('view')->toString()));
        $all = FarmUnitRecord::where('unit_code',$unit);
        $animals=in_array($unit,['goats','rabbits','dogs'],true) ? LivestockAnimal::where('unit_code',$unit)->latest('acquired_on')->get() : collect();
        return view('operations.units.index', ['unit'=>$unit,'config'=>$config,'type'=>$type,'records'=>$query->paginate(30)->withQueryString(),'recordCount'=>(clone $all)->count(),'monthCount'=>(clone $all)->whereBetween('record_date',[now()->startOfMonth(),now()->endOfMonth()])->count(),'monthValue'=>(float)(clone $all)->whereBetween('record_date',[now()->startOfMonth(),now()->endOfMonth()])->sum('amount'),'animals'=>$animals]);
    }

    private function livestockOverview(Request $request,string $unit,array $config): View
    {
        $groups=DB::table('livestock_groups')->where('unit_code',$unit)->orderBy('group_code')->get()->map(function($group){$group->feed30=(float)DB::table('livestock_feed_records')->where('livestock_group_id',$group->id)->where('recorded_on','>=',now()->subDays(30))->whereIn('action',['issued','fed'])->sum('quantity_kg');$group->mortality=(int)DB::table('livestock_mortality_records')->where('livestock_group_id',$group->id)->sum('quantity');$group->births=(float)DB::table('livestock_production_records')->where('livestock_group_id',$group->id)->where('production_type','births')->sum('quantity');$group->lastHealth=DB::table('livestock_health_records')->where('livestock_group_id',$group->id)->max('recorded_on');return $group;});$ids=$groups->pluck('id');
        return view('operations.livestock.overview',['unit'=>$unit,'config'=>$config,'groups'=>$groups,'currentAnimals'=>$groups->where('status','active')->sum('current_quantity'),'feed30'=>(float)DB::table('livestock_feed_records')->whereIn('livestock_group_id',$ids)->where('recorded_on','>=',now()->subDays(30))->whereIn('action',['issued','fed'])->sum('quantity_kg'),'births30'=>(float)DB::table('livestock_production_records')->whereIn('livestock_group_id',$ids)->where('production_type','births')->where('recorded_on','>=',now()->subDays(30))->sum('quantity'),'mortality30'=>(int)DB::table('livestock_mortality_records')->whereIn('livestock_group_id',$ids)->where('occurred_on','>=',now()->subDays(30))->sum('quantity'),'vaccinesDue'=>(int)DB::table('livestock_health_records')->whereIn('livestock_group_id',$ids)->where('event_type','vaccination')->whereNotNull('next_due_on')->where('next_due_on','<=',now()->addDays(7)->toDateString())->count(),'tagged'=>LivestockAnimal::where('unit_code',$unit)->where('status','active')->count()]);
    }

    public function export(Request $request, string $unit): StreamedResponse
    {
        $config = self::UNITS[$unit] ?? abort(404);
        abort_unless($request->user()->canAccess('reports') || $request->user()->canAccessFarmUnit($unit), 403);
        $from = $request->date('from');
        $to = $request->date('to');

        if (in_array($unit,['goats','rabbits','dogs'],true)) {
            $period = fn ($query, string $column) => $query
                ->when($from, fn ($q) => $q->whereDate($column, '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->whereDate($column, '<=', $to->toDateString()));
            $base = fn (string $table) => DB::table($table)->join('livestock_groups','livestock_groups.id','=',$table.'.livestock_group_id')->where($table.'.verification_status','approved')->where('livestock_groups.unit_code',$unit);
            $rows = collect()
                ->merge($period($base('livestock_feed_records'),'recorded_on')->selectRaw("recorded_on as report_date, group_code, 'Feed' as section, feed_name as subject, quantity_kg as quantity, 'kg' as measure_unit, cost as amount, action as detail")->get())
                ->merge($period($base('livestock_health_records'),'recorded_on')->selectRaw("recorded_on as report_date, group_code, 'Health' as section, condition_or_vaccine as subject, animals_affected as quantity, 'animals' as measure_unit, cost as amount, event_type as detail")->get())
                ->merge($period($base('livestock_mortality_records'),'occurred_on')->selectRaw("occurred_on as report_date, group_code, 'Mortality' as section, suspected_cause as subject, quantity, 'animals' as measure_unit, null as amount, disposal_method as detail")->get())
                ->merge($period($base('livestock_production_records'),'recorded_on')->selectRaw("recorded_on as report_date, group_code, 'Production' as section, production_type as subject, quantity, measure_unit, value as amount, notes as detail")->get())
                ->sortBy('report_date');
            return app(BrandedReportExport::class)->download($config['name'].' Report',
                $this->exportPeriodLabel($from,$to),
                ['Date','Group','Section','Record','Quantity','Unit','Value or cost (UGX)','Details'],
                $rows->map(fn($row)=>[$row->report_date,$row->group_code,$row->section,$row->subject,$row->quantity,$row->measure_unit,$row->amount,$row->detail]),
                $unit.'-unit-report-'.now()->format('Y-m-d').'.xls',$request->user()->displayName());
        }

        $query = FarmUnitRecord::with('recorder')->where('verification_status','approved')->where('unit_code',$unit)->orderBy('record_date')->orderBy('id');
        $query->when($from,fn($q)=>$q->whereDate('record_date','>=',$from->toDateString()))->when($to,fn($q)=>$q->whereDate('record_date','<=',$to->toDateString()));
        if ($request->filled('type') && isset($config['types'][$request->string('type')->toString()])) $query->where('record_type',$request->string('type')->toString());
        if ($request->filled('view')) $query->whereIn('record_type',$this->viewTypes($unit,$request->string('view')->toString()));
        $records=$query->get();
        return app(BrandedReportExport::class)->download($config['name'].' Report',
            $this->exportPeriodLabel($from,$to),
            ['Date','Record type','Subject','Quantity','Unit','Value or cost (UGX)','Status','Notes','Recorded by'],
            $records->map(fn($row)=>[$row->record_date->format('Y-m-d'),$config['types'][$row->record_type]??$row->record_type,$row->subject,$row->quantity,$row->measure_unit,$row->amount,$row->status,$row->notes,$row->recorder?->displayName()]),
            $unit.'-unit-report-'.now()->format('Y-m-d').'.xls',$request->user()->displayName());
    }

    public function unitReport(Request $request, string $unit): View
    {
        $config = self::UNITS[$unit] ?? abort(404);
        abort_unless($request->user()->canAccess('reports') || $request->user()->canAccessFarmUnit($unit), 403);

        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        abort_if($from->gt($to), 422, 'The report start date must be before the end date.');

        if (in_array($unit, ['goats', 'rabbits', 'dogs'], true)) {
            $groups = DB::table('livestock_groups')->where('unit_code', $unit)->orderBy('group_code')->get();
            $ids = $groups->pluck('id');
            $feed = DB::table('livestock_feed_records')->join('livestock_groups','livestock_groups.id','=','livestock_feed_records.livestock_group_id')->where('livestock_feed_records.verification_status','approved')->where('livestock_groups.unit_code',$unit)->whereBetween('recorded_on',[$from->toDateString(),$to->toDateString()])->select('livestock_feed_records.*','livestock_groups.group_code')->latest('recorded_on')->get();
            $health = DB::table('livestock_health_records')->join('livestock_groups','livestock_groups.id','=','livestock_health_records.livestock_group_id')->where('livestock_health_records.verification_status','approved')->where('livestock_groups.unit_code',$unit)->whereBetween('recorded_on',[$from->toDateString(),$to->toDateString()])->select('livestock_health_records.*','livestock_groups.group_code')->latest('recorded_on')->get();
            $mortality = DB::table('livestock_mortality_records')->join('livestock_groups','livestock_groups.id','=','livestock_mortality_records.livestock_group_id')->where('livestock_mortality_records.verification_status','approved')->where('livestock_groups.unit_code',$unit)->whereBetween('occurred_on',[$from->toDateString(),$to->toDateString()])->select('livestock_mortality_records.*','livestock_groups.group_code')->latest('occurred_on')->get();
            $production = DB::table('livestock_production_records')->join('livestock_groups','livestock_groups.id','=','livestock_production_records.livestock_group_id')->where('livestock_production_records.verification_status','approved')->where('livestock_groups.unit_code',$unit)->whereBetween('recorded_on',[$from->toDateString(),$to->toDateString()])->select('livestock_production_records.*','livestock_groups.group_code')->latest('recorded_on')->get();

            return view('operations.units.report', compact('unit','config','from','to','groups','feed','health','mortality','production'));
        }

        $records = FarmUnitRecord::where('verification_status','approved')->where('unit_code',$unit)->whereBetween('record_date',[$from->toDateString(),$to->toDateString()])->latest('record_date')->get();
        return view('operations.units.report', compact('unit','config','from','to','records'));
    }

    public function report(Request $request): View
    {
        abort_unless($request->user()->canAccess('production') || $request->user()->canAccess('reports'),403);
        $view=$request->string('view')->toString() ?: 'overall';
        abort_unless(in_array($view,['overall','livestock','health','inputs','production'],true),404);
        $query=FarmUnitRecord::with('recorder')->where('verification_status','approved')->latest('record_date')->latest('id');
        if($view==='livestock') $query->whereIn('unit_code',['goats','rabbits','dogs']);
        if($view==='health') $query->whereIn('record_type',['treatment','vaccination','weight','mortality','incident','loss']);
        if($view==='inputs') $query->whereIn('record_type',['feeding','input_application','irrigation']);
        if($view==='production') $query->whereIn('record_type',['milk','kidding','kindling','whelping','breeding','harvest','sale']);
        $units=collect([
            ['label'=>'Poultry','icon'=>'🐔','value'=>(int)\App\Models\AnimalGroup::where('status','active')->sum('current_quantity'),'measure'=>'birds'],
            ['label'=>'Goats','icon'=>'🐐','value'=>LivestockAnimal::where('unit_code','goats')->where('status','active')->count(),'measure'=>'animals'],
            ['label'=>'Rabbits','icon'=>'🐇','value'=>LivestockAnimal::where('unit_code','rabbits')->where('status','active')->count(),'measure'=>'animals'],
            ['label'=>'Dogs','icon'=>'🐕','value'=>LivestockAnimal::where('unit_code','dogs')->where('status','active')->count(),'measure'=>'animals'],
            ['label'=>'Garden','icon'=>'🌱','value'=>FarmUnitRecord::where('unit_code','garden')->where('record_type','plot')->distinct('subject')->count('subject'),'measure'=>'plots'],
        ]);
        $showEggs=in_array($view,['overall','production'],true);
        $eggs=$showEggs?EggCollection::with(['batch','lines.grade','recorder'])->where('status','approved')->latest('collected_on')->latest('collected_at')->limit(100)->get():collect();
        $pendingEggs=$showEggs?EggCollection::where('status','submitted')->count():0;
        $activity30=FarmUnitRecord::where('verification_status','approved')->where('record_date','>=',now()->subDays(30))->count()+($showEggs?EggCollection::where('status','approved')->where('collected_on','>=',now()->subDays(30)->toDateString())->count():0);
        return view('operations.reports.index',['view'=>$view,'units'=>$units,'rows'=>$query->paginate(40)->withQueryString(),'eggs'=>$eggs,'pendingEggs'=>$pendingEggs,'activity30'=>$activity30,'attention'=>FarmUnitRecord::where('verification_status','approved')->where('status','needs_attention')->count(),'recordedValue'=>(float)FarmUnitRecord::where('verification_status','approved')->sum('amount')]);
    }

    public function reportExport(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canAccess('production') || $request->user()->canAccess('reports'),403);
        $view=$request->string('view')->toString() ?: 'overall';
        abort_unless(in_array($view,['overall','livestock','health','inputs','production'],true),404);
        $farm=FarmUnitRecord::query()->where('verification_status','approved')->orderBy('record_date')->orderBy('id');
        if($view==='livestock')$farm->whereIn('unit_code',['goats','rabbits','dogs']);
        if($view==='health')$farm->whereIn('record_type',['treatment','vaccination','weight','mortality','incident','loss']);
        if($view==='inputs')$farm->whereIn('record_type',['feeding','input_application','irrigation']);
        if($view==='production')$farm->whereIn('record_type',['milk','kidding','kindling','whelping','breeding','harvest','sale']);
        $include=match($view){'health'=>['health','mortality'],'inputs'=>['feed'],'production'=>['production','eggs'],default=>['feed','health','mortality','production','eggs']};
        $rows=collect($farm->get())->map(fn($r)=>[$r->record_date->format('Y-m-d'),ucfirst($r->unit_code),str_replace('_',' ',$r->record_type),$r->subject,$r->notes,$r->quantity,$r->measure_unit,$r->amount,str_replace('_',' ',$r->status)]);
        if(in_array('eggs',$include,true))foreach(EggCollection::with(['batch','lines.grade'])->where('status','approved')->orderBy('collected_on')->orderBy('collected_at')->get() as $r){$breakdown=$r->lines->map(fn($line)=>($line->grade?->name??'Grade').': '.$line->total_eggs)->implode('; ');$rows->push([$r->collected_on->format('Y-m-d'),'Poultry','Egg collection',$r->batch?->code??'Flock',ucfirst($r->session).' - '.$breakdown,$r->total_eggs,'eggs',null,'Approved']);}
        $units=['goats','rabbits','dogs'];
        if(in_array('feed',$include,true))foreach(DB::table('livestock_feed_records')->join('livestock_groups','livestock_groups.id','=','livestock_feed_records.livestock_group_id')->where('livestock_feed_records.verification_status','approved')->whereIn('livestock_groups.unit_code',$units)->select('livestock_feed_records.*','livestock_groups.unit_code','livestock_groups.group_code')->orderBy('recorded_on')->cursor() as $r)$rows->push([$r->recorded_on,ucfirst($r->unit_code),'Feed',$r->group_code,$r->feed_name,$r->quantity_kg,'kg',$r->cost,$r->action]);
        if(in_array('health',$include,true))foreach(DB::table('livestock_health_records')->join('livestock_groups','livestock_groups.id','=','livestock_health_records.livestock_group_id')->where('livestock_health_records.verification_status','approved')->whereIn('livestock_groups.unit_code',$units)->select('livestock_health_records.*','livestock_groups.unit_code','livestock_groups.group_code')->orderBy('recorded_on')->cursor() as $r)$rows->push([$r->recorded_on,ucfirst($r->unit_code),ucfirst($r->event_type),$r->group_code,$r->condition_or_vaccine,$r->animals_affected,'animals',$r->cost,$r->medicine]);
        if(in_array('mortality',$include,true))foreach(DB::table('livestock_mortality_records')->join('livestock_groups','livestock_groups.id','=','livestock_mortality_records.livestock_group_id')->where('livestock_mortality_records.verification_status','approved')->whereIn('livestock_groups.unit_code',$units)->select('livestock_mortality_records.*','livestock_groups.unit_code','livestock_groups.group_code')->orderBy('occurred_on')->cursor() as $r)$rows->push([$r->occurred_on,ucfirst($r->unit_code),'Mortality',$r->group_code,$r->suspected_cause,$r->quantity,'animals',null,$r->disposal_method]);
        if(in_array('production',$include,true))foreach(DB::table('livestock_production_records')->join('livestock_groups','livestock_groups.id','=','livestock_production_records.livestock_group_id')->where('livestock_production_records.verification_status','approved')->whereIn('livestock_groups.unit_code',$units)->select('livestock_production_records.*','livestock_groups.unit_code','livestock_groups.group_code')->orderBy('recorded_on')->cursor() as $r)$rows->push([$r->recorded_on,ucfirst($r->unit_code),'Production',$r->group_code,$r->production_type,$r->quantity,$r->measure_unit,$r->value,$r->notes]);
        return app(BrandedReportExport::class)->download('Overall Production Report',ucfirst($view).' view',
            ['Date','Unit','Category','Group / subject','Description','Quantity','Measure','Value / cost (UGX)','Status / details'],
            $rows->sortBy(0)->values(),'overall-production-'.$view.'-'.now()->format('Y-m-d').'.xls',$request->user()->displayName());
    }

    private function viewTypes(string $unit, string $view): array
    {
        return match($view) {
            'health' => ['treatment','vaccination','weight','mortality','incident'],
            'production' => match($unit) {'goats'=>['milk','kidding','breeding','sale'],'rabbits'=>['kindling','breeding','sale'],'dogs'=>['whelping','breeding','sale'],default=>['harvest','sale']},
            'inputs' => ['feeding','input_application','irrigation'],
            'activity' => ['plot','planting','field_activity'],
            default => array_keys(self::UNITS[$unit]['types']),
        };
    }

    private function exportPeriodLabel($from, $to): string
    {
        if (! $from && ! $to) return 'All approved records';
        return 'Reporting period: '.($from?->format('d M Y') ?? 'Beginning').' to '.($to?->format('d M Y') ?? 'Today');
    }

    public function store(Request $request, string $unit): RedirectResponse
    {
        $config = self::UNITS[$unit] ?? abort(404);
        abort_unless($request->user()->canAccessFarmUnit($unit), 403);
        abort_unless($request->user()->canDo($unit,'create') || $request->user()->canDo($unit,'edit'), 403);
        $data=$request->validate(['record_type'=>['required',Rule::in(array_keys($config['types']))],'record_date'=>['required','date'],'subject'=>['required','string','max:180'],'quantity'=>['nullable','numeric','min:0'],'measure_unit'=>['nullable','string','max:40'],'amount'=>['nullable','numeric','min:0'],'status'=>['required','string','max:40'],'notes'=>['nullable','string','max:2000']]);
        $record=FarmUnitRecord::create($data+['unit_code'=>$unit,'recorded_by'=>$request->user()->id]);
        AuditLog::record('farm_unit_record_created',$record,$config['name'].': '.$data['subject'],null,$data,$unit);
        return back()->with('status',$config['types'][$data['record_type']].' recorded.');
    }

    public function storeAnimal(Request $request, string $unit): RedirectResponse
    {
        abort_unless(in_array($unit,['goats','rabbits','dogs'],true),404);
        abort_unless($request->user()->canAccessFarmUnit($unit),403);
        abort_unless($request->user()->canDo($unit,'create') || $request->user()->canDo($unit,'edit'),403);
        $data=$request->validate(['animal_tag'=>['required','string','max:80',Rule::unique('livestock_animals')->where(fn($q)=>$q->where('unit_code',$unit))],'name'=>['nullable','string','max:120'],'sex'=>['required','in:male,female'],'breed'=>['nullable','string','max:120'],'date_of_birth'=>['nullable','date','before_or_equal:today'],'acquired_on'=>['required','date','before_or_equal:today'],'acquisition_type'=>['required','in:born,purchased,donated,transferred'],'dam_tag'=>['nullable','string','max:80'],'sire_tag'=>['nullable','string','max:80'],'acquisition_cost'=>['nullable','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);
        $animal=LivestockAnimal::create($data+['unit_code'=>$unit,'status'=>'active','recorded_by'=>$request->user()->id]);
        AuditLog::record('livestock_registered',$animal,ucfirst($unit).' animal '.$animal->animal_tag.' registered as '.$animal->acquisition_type,null,$data,$unit);
        return back()->with('status','Animal '.$animal->animal_tag.' added to the '.self::UNITS[$unit]['name'].' register.');
    }

    public function livestock(Request $request, string $unit, string $section): View
    {
        abort_unless(in_array($unit,['goats','rabbits','dogs'],true),404);
        abort_unless(in_array($section,['register','feed','breeding','vaccination','deworming','spraying','treatment','mortality','production'],true),404);
        abort_unless($request->user()->canAccessFarmUnit($unit),403);
        $groups=DB::table('livestock_groups')->where('unit_code',$unit)->orderBy('group_code')->get();
        $table=match($section){'register'=>'livestock_groups','feed'=>'livestock_feed_records','breeding'=>'livestock_breeding_records','vaccination','deworming','spraying','treatment'=>'livestock_health_records','mortality'=>'livestock_mortality_records','production'=>'livestock_production_records'};
        $rows=DB::table($table)->when($section!=='register',fn($q)=>$q->join('livestock_groups','livestock_groups.id','=',$table.'.livestock_group_id')->where('livestock_groups.unit_code',$unit))->when($section==='register',fn($q)=>$q->where('unit_code',$unit))->when(in_array($section,['vaccination','deworming','spraying','treatment']),fn($q)=>$q->where('event_type',$section))->select($table.'.*',...($section==='register'?[]:['livestock_groups.group_code']))->orderByDesc($section==='mortality'?'occurred_on':($section==='register'?'acquired_on':($section==='breeding'?'service_date':'recorded_on')))->paginate(30);
        $availableFeeds=$section==='feed'?DB::table('feed_stock_movements')->where('unit_code',$unit)->select('feed_name',DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance_kg"))->groupBy('feed_name')->havingRaw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) > 0")->orderBy('feed_name')->get():collect();
        $species = ['goats'=>'goat', 'rabbits'=>'rabbit', 'dogs'=>'dog'][$unit];
        $breeds = $section === 'register'
            ? Breed::where('species', $species)->where('is_active', true)->orderBy('name')->get()
            : collect();
        return view('operations.livestock.section',['unit'=>$unit,'config'=>self::UNITS[$unit],'section'=>$section,'groups'=>$groups,'rows'=>$rows,'availableFeeds'=>$availableFeeds,'nextCode'=>$section==='register'?$this->nextLivestockCode($unit):null,'breeds'=>$breeds]);
    }

    public function storeLivestock(Request $request,string $unit,string $section): RedirectResponse
    {
        abort_unless(in_array($unit,['goats','rabbits','dogs'],true),404);abort_unless(in_array($section,['register','feed','breeding','vaccination','deworming','spraying','treatment','mortality','production'],true),404);abort_unless($request->user()->canAccessFarmUnit($unit),403);abort_unless($request->user()->canDo($unit,'create')||$request->user()->canDo($unit,'edit'),403);
        if($section==='register'){
            $next=$request->string('next')->toString();
            $d=$request->validate([
                'breed'=>['nullable','string','max:120'],
                'source'=>['required','in:born,purchased,donated,transferred'],
                'acquired_on'=>['required','date'],
                'initial_quantity'=>['required','integer','min:1'],
                'unit_price'=>['nullable','required_without:acquisition_cost','numeric','min:0'],
                'acquisition_cost'=>['nullable','required_without:unit_price','numeric','min:0'],
                'notes'=>['nullable','string','max:2000'],
            ]);
            $d['group_code']=$this->nextLivestockCode($unit);
            if (isset($d['acquisition_cost'])) {
                $d['unit_price']=round((float)$d['acquisition_cost']/(float)$d['initial_quantity'],2);
            } else {
                $d['acquisition_cost']=round($d['initial_quantity']*(float)$d['unit_price'],2);
            }
            $groupId=DB::table('livestock_groups')->insertGetId($d+['unit_code'=>$unit,'current_quantity'=>$d['initial_quantity'],'status'=>'active','recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            if(in_array($next,['feed','vaccination','treatment','mortality','production'],true))return redirect()->route('operations.livestock.section',['unit'=>$unit,'section'=>$next,'group'=>$groupId,'create'=>1])->with('status','Group '.$d['group_code'].' registered. Complete the first '.strtolower($next).' record.');
            return back()->with('status','New '.$unit.' group '.$d['group_code'].' registered.');
        }
        $d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id']]);$group=DB::table('livestock_groups')->where('id',$d['livestock_group_id'])->where('unit_code',$unit)->first();abort_unless($group,422,'Choose a group from this unit.');
        if($section==='feed'){$d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id'],'recorded_on'=>['required','date'],'action'=>['required','in:mixed,issued,fed'],'feed_name'=>['required','string','max:160'],'quantity_kg'=>['required','numeric','min:0.01'],'source_reference'=>['nullable','string','max:160'],'cost'=>['nullable','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);DB::transaction(function()use($d,$request,$unit){$id=DB::table('livestock_feed_records')->insertGetId($d+['recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);if(in_array($d['action'],['issued','fed'],true))app(FeedStockService::class)->issue(['unit_code'=>$unit,'movement_date'=>$d['recorded_on'],'movement_type'=>$d['action'],'feed_name'=>$d['feed_name'],'quantity_kg'=>$d['quantity_kg'],'reference'=>'livestock-feed:'.$id,'notes'=>$d['notes']??null,'recorded_by'=>$request->user()->id]);});}
        elseif($section==='breeding'){$d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id'],'service_date'=>['required','date','before_or_equal:today'],'dam_identity'=>['required','string','max:120'],'dam_breed'=>['nullable','string','max:120'],'sire_identity'=>['required','string','max:120'],'sire_breed'=>['nullable','string','max:120'],'previous_inseminations'=>['required','integer','min:0'],'next_heat_on'=>['nullable','date','after_or_equal:service_date'],'pregnancy_diagnosis'=>['nullable','string','max:80'],'expected_delivery_on'=>['nullable','date','after:service_date'],'notes'=>['nullable','string','max:2000']]);DB::table('livestock_breeding_records')->insert($d+['recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);}
        elseif(in_array($section,['vaccination','deworming','spraying','treatment'])){$d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id'],'recorded_on'=>['required','date'],'condition_or_vaccine'=>['required','string','max:180'],'medicine'=>['nullable','string','max:180'],'application_route'=>['nullable','string','max:80'],'dose'=>['nullable','string','max:100'],'mix_ratio'=>['nullable','string','max:80'],'animals_affected'=>['required','integer','min:1'],'next_due_on'=>['nullable','date'],'batch_number'=>['nullable','string','max:100'],'manufactured_on'=>['nullable','date'],'expires_on'=>['nullable','date','after_or_equal:manufactured_on'],'camp_category'=>['nullable','string','max:100'],'breed'=>['nullable','string','max:120'],'withdrawal_ends_on'=>['nullable','date'],'cost'=>['nullable','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);DB::table('livestock_health_records')->insert($d+['event_type'=>$section,'recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);}
        elseif($section==='mortality'){$d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id'],'occurred_on'=>['required','date'],'quantity'=>['required','integer','min:1'],'suspected_cause'=>['nullable','string','max:180'],'disposal_method'=>['nullable','string','max:120'],'notes'=>['nullable','string','max:2000']]);abort_if($d['quantity']>$group->current_quantity,422,'Mortality cannot exceed the current group quantity.');DB::transaction(function()use($d,$request){DB::table('livestock_mortality_records')->insert($d+['recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);DB::table('livestock_groups')->where('id',$d['livestock_group_id'])->decrement('current_quantity',$d['quantity']);});}
        else{$d=$request->validate(['livestock_group_id'=>['required','exists:livestock_groups,id'],'recorded_on'=>['required','date'],'production_type'=>['required','in:milk,births,weight,sale,other'],'quantity'=>['required','numeric','min:0.01'],'measure_unit'=>['required','string','max:40'],'value'=>['nullable','numeric','min:0'],'notes'=>['nullable','string','max:2000']]);DB::transaction(function()use($d,$request){DB::table('livestock_production_records')->insert($d+['recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);if($d['production_type']==='births')DB::table('livestock_groups')->where('id',$d['livestock_group_id'])->increment('current_quantity',(int)$d['quantity']);});}
        AuditLog::record('livestock_'.$section.'_recorded',null,self::UNITS[$unit]['name'].' '.$section.' record saved.',null,['group'=>$group->group_code],$unit);return back()->with('status',ucfirst($section).' record saved.');
    }

    /** Next unit-specific livestock batch code: G-001/26, R-001/26, D-001/26... */
    private function nextLivestockCode(string $unit): string
    {
        $prefix = match ($unit) {
            'goats' => 'G',
            'rabbits' => 'R',
            'dogs' => 'D',
            default => throw new \InvalidArgumentException("Unsupported livestock unit: {$unit}"),
        };
        $year = now()->format('y');
        $last = DB::table('livestock_groups')
            ->where('unit_code', $unit)
            ->where('group_code', 'like', "{$prefix}-%/{$year}")
            ->orderByRaw('LENGTH(group_code) DESC, group_code DESC')
            ->value('group_code');
        $lastNumber = $last
            ? (int) substr($last, 2, strpos($last, '/') - 2)
            : 0;

        return sprintf('%s-%03d/%s', $prefix, $lastNumber + 1, $year);
    }
}
