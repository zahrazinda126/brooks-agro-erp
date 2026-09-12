<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog,InventoryItem,InventoryMovement,InventoryStore,PoultryHouse,Role};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const CATEGORIES=['medicine','vaccine','fuel','packaging','tools','equipment','cleaning','farm produce','food','kitchen','staff welfare','supplies','other'];
    private const OUT=['issued','farm_use','wastage','adjustment_out','transfer_out'];

    public function index(Request $request):View
    {
        $user=$request->user(); abort_unless($user->canAccess('inventory'),403);
        $isStoreManager=$this->isStoreManager($user);
        $stores=$this->storesFor($user); abort_if($stores->isEmpty(),403,'You have not been given access to any inventory store.');
        $selectedId=(int)$request->integer('store',$stores->first()->id); abort_unless($stores->contains('id',$selectedId),403);
        $selectedStore=$stores->firstWhere('id',$selectedId); $access=$selectedStore->access;
        $balance="sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end)";
        $items=InventoryItem::with('store')->where('inventory_store_id',$selectedId)->select('inventory_items.*')->selectSub(fn($q)=>$q->from('inventory_movements')->whereColumn('inventory_item_id','inventory_items.id')->selectRaw("coalesce($balance,0)"),'balance')->orderBy('category')->orderBy('name')->get();
        $byLocation=DB::table('inventory_movements')->join('inventory_items','inventory_items.id','=','inventory_movements.inventory_item_id')->where('inventory_movements.inventory_store_id',$selectedId)->select('inventory_items.name','inventory_items.unit','inventory_movements.location',DB::raw("$balance as balance"))->groupBy('inventory_items.id','inventory_items.name','inventory_items.unit','inventory_movements.location')->havingRaw("$balance != 0")->orderBy('inventory_items.name')->get();
        $movements=InventoryMovement::with(['item','recorder','poultryHouse'])->where('inventory_store_id',$selectedId)->latest('movement_date')->latest('id')->paginate(40)->withQueryString();
        $houseIssues=DB::table('inventory_movements as movement')->join('inventory_items as item','item.id','=','movement.inventory_item_id')->join('poultry_houses as house','house.id','=','movement.poultry_house_id')->where('movement.inventory_store_id',$selectedId)->select('house.id as house_id','house.house_number','item.id as item_id','item.name','item.unit',DB::raw("sum(case when movement.movement_type in ('issued','farm_use') then movement.quantity when movement.movement_type='return' then -movement.quantity else 0 end) as quantity"),DB::raw('max(movement.movement_date) as last_movement'))->groupBy('house.id','house.house_number','item.id','item.name','item.unit')->havingRaw("sum(case when movement.movement_type in ('issued','farm_use') then movement.quantity when movement.movement_type='return' then -movement.quantity else 0 end) != 0")->orderBy('house.house_number')->orderBy('item.name')->get();
        $feed=$selectedStore->code==='feed'?DB::table('feed_stock_movements')->select('unit_code','feed_name',DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance"))->groupBy('unit_code','feed_name')->havingRaw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) != 0")->orderBy('unit_code')->get():collect();
        return view('inventory.index',compact('stores','selectedStore','access','items','byLocation','movements','feed','houseIssues','isStoreManager')+['houses'=>PoultryHouse::with('batch')->where('is_active',true)->orderByRaw("nullif(regexp_replace(house_number, '[^0-9]', '', 'g'), '')::int nulls last")->orderBy('house_number')->get(),'categories'=>self::CATEGORIES,'canCreate'=>$user->canDo('inventory','create')&&($access['can_receive']||$access['can_issue']||$access['can_adjust']),'roles'=>Role::where('is_active',true)->orderBy('rank')->get(),'roleAccess'=>DB::table('inventory_store_role')->get()->keyBy(fn($r)=>$r->inventory_store_id.'-'.$r->role_id),'isDirector'=>$user->roles->contains('code','director')||$user->role?->code==='director']);
    }

    public function storeItem(Request $request):RedirectResponse
    {
        abort_if($this->isStoreManager($request->user()),403,'The Farm Store Manager only receives and issues existing stock items.');
        $this->authorizeWrite($request);
        $d=$request->validate(['inventory_store_id'=>['required','exists:inventory_stores,id'],'code'=>['required','string','max:50','unique:inventory_items,code'],'name'=>['required','string','max:160'],'category_choice'=>['required',Rule::in([...self::CATEGORIES,'custom'])],'custom_category'=>['nullable','required_if:category_choice,custom','string','max:60'],'unit'=>['required','string','max:40'],'reorder_level'=>['required','numeric','min:0'],'default_location'=>['required','string','max:100'],'notes'=>['nullable','string','max:2000']]);
        $this->requireRight($request,(int)$d['inventory_store_id'],'can_receive'); $d['category']=$d['category_choice']==='custom'?strtolower(trim($d['custom_category'])):$d['category_choice']; unset($d['category_choice'],$d['custom_category']);
        $item=InventoryItem::create($d+['created_by'=>$request->user()->id]); AuditLog::record('inventory_item_created',$item,'Inventory item '.$item->code.' created.',null,$d,'inventory');
        return back()->with('status','Inventory item added. Record its opening balance or first receipt below.');
    }

    public function storeMovement(Request $request):RedirectResponse
    {
        $this->authorizeWrite($request); $types=$this->isStoreManager($request->user()) ? ['received','issued','farm_use'] : ['received','return',...self::OUT,'adjustment_in','transfer'];
        $d=$request->validate(['inventory_item_id'=>['required','exists:inventory_items,id'],'poultry_house_id'=>['nullable','exists:poultry_houses,id'],'movement_date'=>['required','date','before_or_equal:today'],'movement_type'=>['required',Rule::in($types)],'location'=>['required','string','max:100'],'destination_location'=>['nullable','required_if:movement_type,transfer','different:location','string','max:100'],'quantity'=>['required','numeric','min:0.01'],'unit_cost'=>['nullable','numeric','min:0'],'total_cost'=>['nullable','numeric','min:0'],'supplier_or_recipient'=>['nullable','required_if:movement_type,farm_use','string','max:160'],'reference'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:2000']]);
        $item=InventoryItem::findOrFail($d['inventory_item_id']); $right=in_array($d['movement_type'],['received','return'],true)?'can_receive':(str_starts_with($d['movement_type'],'adjustment')?'can_adjust':'can_issue'); $this->requireRight($request,(int)$item->inventory_store_id,$right); $d['inventory_store_id']=$item->inventory_store_id;
        $qty=(float)$d['quantity']; $source=$d['location']; if(in_array($d['movement_type'],self::OUT,true)||$d['movement_type']==='transfer') abort_if($qty>$this->balance($item->id,$source),422,'This movement exceeds the available stock at '.$source.'.');
        if (! in_array($d['movement_type'], ['issued','farm_use','return'], true)) $d['poultry_house_id']=null;
        if ($d['movement_type']==='return' && ! empty($d['poultry_house_id'])) abort_if($qty>$this->houseBalance($item->id,(int)$d['poultry_house_id']),422,'The return exceeds the quantity currently recorded at that poultry house.');
        if(isset($d['total_cost']))$d['unit_cost']=round((float)$d['total_cost']/$qty,2); elseif(isset($d['unit_cost']))$d['total_cost']=round($qty*(float)$d['unit_cost'],2); else{$d['unit_cost']=null;$d['total_cost']=null;}
        $destination=$d['destination_location']??null; unset($d['destination_location']); DB::transaction(function()use($d,$destination,$request,$item){if($d['movement_type']==='transfer'){$ref=$d['reference']?:'TRANSFER-'.now()->format('YmdHis');InventoryMovement::create(array_merge($d,['movement_type'=>'transfer_out','reference'=>$ref,'recorded_by'=>$request->user()->id]));InventoryMovement::create(array_merge($d,['movement_type'=>'transfer_in','location'=>$destination,'reference'=>$ref,'recorded_by'=>$request->user()->id]));}else InventoryMovement::create($d+['recorded_by'=>$request->user()->id]);AuditLog::record('inventory_movement',null,ucwords(str_replace('_',' ',$d['movement_type'])).' '.$d['quantity'].' '.$item->unit.' of '.$item->name.'.',null,$d,'inventory');});
        return back()->with('status','Stock movement recorded.');
    }

    public function updateStoreAccess(Request $request,InventoryStore $store,Role $role):RedirectResponse
    {
        abort_unless($request->user()->roles->contains('code','director')||$request->user()->role?->code==='director',403,'Only the Director can change store access.');
        $keys=['can_view','can_receive','can_issue','can_adjust','can_view_value']; $d=$request->validate(array_fill_keys($keys,['nullable','boolean'])); $values=[]; foreach($keys as $key)$values[$key]=(bool)($d[$key]??false);
        DB::table('inventory_store_role')->updateOrInsert(['inventory_store_id'=>$store->id,'role_id'=>$role->id],$values+['created_at'=>now(),'updated_at'=>now()]); return back()->with('status',$role->name.' access updated.');
    }

    private function storesFor($user){$stores=InventoryStore::where('is_active',true)->orderBy('section')->orderBy('name')->get();if($user->isSuperuser())return $stores->each(fn($s)=>$s->access=array_fill_keys(['can_view','can_receive','can_issue','can_adjust','can_view_value'],true));$grants=DB::table('inventory_store_role')->whereIn('role_id',$user->roleIds())->get()->groupBy('inventory_store_id');return $stores->filter(function($s)use($grants){$r=$grants->get($s->id,collect());$s->access=['can_view'=>$r->contains('can_view',true),'can_receive'=>$r->contains('can_receive',true),'can_issue'=>$r->contains('can_issue',true),'can_adjust'=>$r->contains('can_adjust',true),'can_view_value'=>$r->contains('can_view_value',true)];return $s->access['can_view'];})->values();}
    private function requireRight(Request $r,int $storeId,string $right):void{$s=$this->storesFor($r->user())->firstWhere('id',$storeId);abort_unless($s&&($s->access[$right]??false),403,'You do not have permission for this action in that store.');}
    private function balance(int $id,string $location):float{return(float)DB::table('inventory_movements')->where('inventory_item_id',$id)->where('location',$location)->selectRaw("coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end),0) balance")->value('balance');}
    private function houseBalance(int $itemId,int $houseId):float{return(float)DB::table('inventory_movements')->where('inventory_item_id',$itemId)->where('poultry_house_id',$houseId)->selectRaw("coalesce(sum(case when movement_type in ('issued','farm_use') then quantity when movement_type='return' then -quantity else 0 end),0) balance")->value('balance');}
    private function authorizeWrite(Request $r):void{abort_unless($r->user()->canDo('inventory','create'),403);}
    private function isStoreManager($user):bool{return $user->role?->code==='store-keeper'||$user->roles->contains('code','store-keeper');}
}
