<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryStore;
use App\Services\CommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedStockController extends Controller
{
    public const FEEDS=['Bran','Broken Maize','Sunflower','Soya','Lime','Hendrix'];

    /**
     * The feed list: the standard feeds plus any feed typed on a supplier weighing sheet. A new name
     * typed there becomes part of the list everywhere (mixing, reports, bill matching).
     */
    public static function feedNames(): array
    {
        $recorded = DB::table('feed_delivery_bags')->distinct()->pluck('feed_name')->filter()->all();
        $names = self::FEEDS;
        foreach ($recorded as $name) {
            if (! in_array(mb_strtolower($name), array_map('mb_strtolower', $names), true)) $names[] = $name;
        }

        return $names;
    }

    /** A typed feed name in the list's own spelling ("bran" → "Bran"), or tidied if it is new. */
    public static function canonicalFeedName(string $typed): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $typed));
        foreach (self::feedNames() as $name) {
            if (mb_strtolower($name) === mb_strtolower($clean)) return $name;
        }

        return mb_strtoupper(mb_substr($clean, 0, 1)).mb_substr($clean, 1);
    }
    /** Units that receive feed deliveries. */
    public const UNITS=['poultry'=>'Poultry','goats'=>'Goats','rabbits'=>'Rabbits','dogs'=>'Dogs'];

    public function index(Request $request): View
    {
        $user=$request->user();
        abort_unless($user->canAccess('feed-received')||$user->canAccess('inventory')||$user->canAccess('production'),403);
        $unit=array_key_exists((string)$request->query('unit'),self::UNITS)?$request->query('unit'):'poultry';
        $balances=DB::table('feed_stock_movements')->where('unit_code',$unit)->select('feed_name',DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance_kg"))->groupBy('feed_name')->orderBy('feed_name')->get();
        $deliveries=DB::table('feed_delivery_verifications')->leftJoin('users as supplier_user','supplier_user.id','=','feed_delivery_verifications.supplier_recorded_by')->leftJoin('users as farm_user','farm_user.id','=','feed_delivery_verifications.farm_received_by')->where('feed_delivery_verifications.unit_code',$unit)->select('feed_delivery_verifications.*','supplier_user.name as supplier_recorder','farm_user.name as farm_receiver')->latest('delivery_date')->latest('feed_delivery_verifications.id')->get()->map(function($delivery){
            $delivery->bags=DB::table('feed_delivery_bags')->where('feed_delivery_verification_id',$delivery->id)->orderBy('feed_name')->orderBy('bag_number')->get();
            $delivery->supplier_total_kg=(float)$delivery->bags->sum('supplier_weight_kg');
            $delivery->quantity_received_kg=(float)$delivery->bags->sum('farm_weight_kg');
            $delivery->delivery_variance_kg=$delivery->status==='received_at_farm'?round($delivery->quantity_received_kg-$delivery->supplier_total_kg,2):null;
            // The supplier bills that charge for this delivery, so the store and Finance see the same link.
            $delivery->bills=DB::table('payable_feed_delivery')->join('payables','payables.id','=','payable_feed_delivery.payable_id')->where('payable_feed_delivery.feed_delivery_verification_id',$delivery->id)->get(['payables.invoice_number','payables.id','payables.amount','payables.amount_paid']);
            return $delivery;
        });
        $canFarmReceive=$this->hasRole($user,'store-keeper')||$this->hasRole($user,'inventory-officer');
        $storeCards=$canFarmReceive ? InventoryStore::where('is_active',true)
            ->whereIn('id',DB::table('inventory_store_role')->whereIn('role_id',$user->roleIds())->where('can_view',true)->select('inventory_store_id'))
            ->orderBy('section')->orderBy('name')->get() : collect();
        return view('operations.feed-stock.index',[
            'feeds'=>self::feedNames(),'balances'=>$balances,'unit'=>$unit,'units'=>self::UNITS,'deliveries'=>$deliveries,'storeCards'=>$storeCards,
            'canDispatch'=>$this->hasRole($user,'production-manager'),
            'canFarmReceive'=>$canFarmReceive,
        ]);
    }

    /** Production Manager records the supplier's weights before the vehicle leaves. */
    public function store(Request $request, CommunicationService $communications): RedirectResponse
    {
        $user=$request->user(); abort_unless($this->hasRole($user,'production-manager'),403,'Only the Production Manager records supplier weights.');
        $data=$request->validate([
            'delivery_date'=>['required','date','before_or_equal:today',fn($a,$v,$fail)=>($day=(int) config('farm.feed_delivery_weekday')) && Carbon::parse($v)->dayOfWeekIso!==$day?$fail('Feed deliveries must be recorded on a '.Carbon::create()->isoWeekday($day)->format('l').'.'):null],
            'unit_code'=>['nullable',Rule::in(array_keys(self::UNITS))],
            'quantity_ordered_kg'=>['required','numeric','min:0.01'],'vehicle_number'=>['nullable','string','max:60'],'driver_name'=>['nullable','string','max:160'],'farm_representative'=>['nullable','string','max:160'],'supplier'=>['required','string','max:160'],'reference'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:2000'],
            'bags'=>['required','array','min:1'],'bags.*.feed_name'=>['required','string','max:80'],'bags.*.supplier_weight_kg'=>['required','numeric','min:0.01'],
        ]);
        DB::transaction(function()use($data,$user){
            $bags=collect($data['bags'])->map(fn($bag)=>['feed_name'=>self::canonicalFeedName($bag['feed_name'])]+$bag)->all(); unset($data['bags']); $data['unit_code']=$data['unit_code']??'poultry';
            $deliveryId=DB::table('feed_delivery_verifications')->insertGetId($data+['recorded_by'=>$user->id,'supplier_recorded_by'=>$user->id,'supplier_recorded_at'=>now(),'status'=>'awaiting_farm_receipt','created_at'=>now(),'updated_at'=>now()]);
            foreach(collect($bags)->groupBy('feed_name') as $feed=>$feedRows) foreach($feedRows->values() as $index=>$bag) DB::table('feed_delivery_bags')->insert(['feed_delivery_verification_id'=>$deliveryId,'feed_name'=>$feed,'bag_number'=>$index+1,'supplier_weight_kg'=>$bag['supplier_weight_kg'],'farm_weight_kg'=>null,'variance_kg'=>null,'created_at'=>now(),'updated_at'=>now()]);
            AuditLog::record('feed_dispatched_from_supplier',null,'Supplier feed weights recorded before dispatch.',null,['delivery_id'=>$deliveryId],'feed-received');
        });
        $communications->toRole('store-keeper','Feed delivery is on its way','Production recorded the supplier weights. Weigh and receive the delivery when it reaches the farm.',$user->id,route('operations.feed-stock.index',['unit'=>$data['unit_code']??'poultry'],false));
        return back()->with('status','Supplier weights saved. The Farm Store Manager has been notified to receive and weigh the delivery.');
    }

    /** Farm Store Manager records the independent arrival weights. */
    public function receive(Request $request, int $delivery, CommunicationService $communications): RedirectResponse
    {
        $user=$request->user(); abort_unless($this->hasRole($user,'store-keeper')||$this->hasRole($user,'inventory-officer'),403,'Only the Farm Store Manager can record farm receipt weights.');
        $header=DB::table('feed_delivery_verifications')->where('id',$delivery)->first(); abort_unless($header,404); abort_unless($header->status==='awaiting_farm_receipt',422,'This delivery has already been received at the farm.');
        $bagIds=DB::table('feed_delivery_bags')->where('feed_delivery_verification_id',$delivery)->pluck('id');
        $data=$request->validate(['farm_weights'=>['required','array','size:'.$bagIds->count()],'farm_weights.*'=>['required','numeric','min:0.01'],'farm_notes'=>['nullable','string','max:2000']]);
        abort_unless(collect(array_keys($data['farm_weights']))->map(fn($id)=>(int)$id)->sort()->values()->all()===$bagIds->map(fn($id)=>(int)$id)->sort()->values()->all(),422,'Farm weights must be recorded for every feed line in this delivery.');
        DB::transaction(function()use($delivery,$header,$bagIds,$data,$user){
            foreach($bagIds as $id){$supplier=(float)DB::table('feed_delivery_bags')->where('id',$id)->value('supplier_weight_kg');$farm=(float)$data['farm_weights'][$id];DB::table('feed_delivery_bags')->where('id',$id)->update(['farm_weight_kg'=>$farm,'variance_kg'=>round($farm-$supplier,2),'updated_at'=>now()]);}
            DB::table('feed_delivery_verifications')->where('id',$delivery)->update(['status'=>'received_at_farm','farm_received_by'=>$user->id,'farm_received_at'=>now(),'notes'=>trim((string)($header->notes??'').(($data['farm_notes']??null)?"\nFarm receipt: ".$data['farm_notes']:''))?:null,'updated_at'=>now()]);
            $received=DB::table('feed_delivery_bags')->where('feed_delivery_verification_id',$delivery)->get()->groupBy('feed_name');
            foreach($received as $feed=>$bags)DB::table('feed_stock_movements')->insert(['unit_code'=>$header->unit_code??'poultry','movement_date'=>$header->delivery_date,'movement_type'=>'received','feed_name'=>$feed,'quantity_kg'=>$bags->sum('farm_weight_kg'),'supplier'=>$header->supplier,'reference'=>$header->reference?:'FEED-'.str_pad($delivery,5,'0',STR_PAD_LEFT),'notes'=>'Farm Store Manager received and independently weighed the delivery.','recorded_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
            AuditLog::record('feed_received_at_farm',null,'Farm receipt weights recorded and variance calculated.',null,['delivery_id'=>$delivery],'feed-received');
        });
        if($header->supplier_recorded_by)$communications->toUser($header->supplier_recorded_by,'Feed delivery received at farm','The Farm Store Manager recorded arrival weights. Variances are now available for review.','workflow',$user->id,route('operations.feed-stock.index',[],false));
        return back()->with('status','Farm weights saved. Variances were calculated and the received quantities were added to feed stock.');
    }

    private function hasRole($user,string $code):bool{return $user->role?->code===$code||$user->roles->contains('code',$code);}
}
