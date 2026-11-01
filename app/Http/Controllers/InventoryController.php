<?php
namespace App\Http\Controllers;

use App\Models\{AuditLog,InventoryBatch,InventoryItem,InventoryMovement,InventoryStore,PoultryHouse,Role};
use App\Services\InventoryStock;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const CATEGORIES=['medicine','vaccine','fuel','packaging','tools','equipment','cleaning','farm produce','food','kitchen','staff welfare','supplies','other'];
    private const OUT=['issued','farm_use','wastage','adjustment_out','transfer_out'];
    /** Recorded by the store, applied only after the Finance Manager approves. */
    private const ADJUSTMENTS=['wastage','adjustment_in','adjustment_out'];

    public function index(Request $request):View
    {
        $user=$request->user(); abort_unless($user->canAccess('inventory'),403);
        $isStoreManager=$this->isStoreManager($user);
        $stores=$this->storesFor($user); abort_if($stores->isEmpty(),403,'You have not been given access to any inventory store.');
        // "All" shows every store this person may view (the Stock list and the summary tiles).
        $allStoresView=$request->query('store')==='all';
        $selectedId=$allStoresView?$stores->first()->id:(int)$request->integer('store',$stores->first()->id); abort_unless($stores->contains('id',$selectedId),403);
        $selectedStore=$stores->firstWhere('id',$selectedId); $access=$selectedStore->access;
        $balance="sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end)";
        $items=InventoryItem::with('store')->whereIn('inventory_store_id',$allStoresView?$stores->pluck('id')->all():[$selectedId])->select('inventory_items.*')->selectSub(fn($q)=>$q->from('inventory_movements')->whereColumn('inventory_item_id','inventory_items.id')->where('receipt_status','confirmed')->selectRaw("coalesce($balance,0)"),'balance')->orderBy('category')->orderBy('name')->get();
        $byLocation=DB::table('inventory_movements')->join('inventory_items','inventory_items.id','=','inventory_movements.inventory_item_id')->where('inventory_movements.inventory_store_id',$selectedId)->where('inventory_movements.receipt_status','confirmed')->select('inventory_items.name','inventory_items.unit','inventory_movements.location',DB::raw("$balance as balance"))->groupBy('inventory_items.id','inventory_items.name','inventory_items.unit','inventory_movements.location')->havingRaw("$balance != 0")->orderBy('inventory_items.name')->get();
        $movements=InventoryMovement::with(['item','recorder','poultryHouse'])->where('inventory_store_id',$selectedId)->latest('movement_date')->latest('id')->paginate(40)->withQueryString();
        $pendingReceipts=InventoryMovement::with(['item','recorder'])->where('inventory_store_id',$selectedId)->where('movement_type','received')->where('receipt_status','pending')->oldest('movement_date')->oldest('id')->get();
        $houseIssues=DB::table('inventory_movements as movement')->join('inventory_items as item','item.id','=','movement.inventory_item_id')->join('poultry_houses as house','house.id','=','movement.poultry_house_id')->where('movement.inventory_store_id',$selectedId)->select('house.id as house_id','house.house_number','item.id as item_id','item.name','item.unit',DB::raw("sum(case when movement.movement_type in ('issued','farm_use') then movement.quantity when movement.movement_type='return' then -movement.quantity else 0 end) as quantity"),DB::raw('max(movement.movement_date) as last_movement'))->groupBy('house.id','house.house_number','item.id','item.name','item.unit')->havingRaw("sum(case when movement.movement_type in ('issued','farm_use') then movement.quantity when movement.movement_type='return' then -movement.quantity else 0 end) != 0")->orderBy('house.house_number')->orderBy('item.name')->get();
        $feed=$selectedStore->code==='feed'?DB::table('feed_stock_movements')->select('unit_code','feed_name',DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance"))->groupBy('unit_code','feed_name')->havingRaw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) != 0")->orderBy('unit_code')->get():collect();
        return view('inventory.index',compact('stores','selectedStore','access','items','byLocation','movements','feed','houseIssues','pendingReceipts','isStoreManager','allStoresView')+$this->warehouseData($request,$allStoresView?$stores:collect([$selectedStore]),$selectedStore)+['houses'=>PoultryHouse::with('batch')->where('is_active',true)->orderByRaw("nullif(regexp_replace(house_number, '[^0-9]', '', 'g'), '')::int nulls last")->orderBy('house_number')->get(),'categories'=>self::CATEGORIES,'canCreate'=>$user->canDo('inventory','create')&&($access['can_receive']||$access['can_issue']||$access['can_adjust']),'roles'=>Role::where('is_active',true)->orderBy('rank')->get(),'roleAccess'=>DB::table('inventory_store_role')->get()->keyBy(fn($r)=>$r->inventory_store_id.'-'.$r->role_id),'isDirector'=>$user->roles->contains('code','director')||$user->role?->code==='director','allStores'=>InventoryStore::where('is_active',true)->orderBy('section')->orderBy('name')->get(),'people'=>\App\Models\User::where('is_active',true)->with('role')->orderBy('name')->get(),'personAccess'=>DB::table('inventory_store_user')->get()->groupBy('user_id'),'accessPerson'=>$request->filled('person')?\App\Models\User::find($request->integer('person')):null]);
    }

    /** Data for the Farm Hub style warehouse tabs: summary, batches, adjustments, receipts, ledger, daily report, requests. */
    private function warehouseData(Request $request, $scopeStores, InventoryStore $store): array
    {
        $user = $request->user();
        $batchLeft = DB::table('inventory_movements')->where('inventory_store_id', $store->id)->where('receipt_status', 'confirmed')
            ->whereNotNull('inventory_batch_id')->groupBy('inventory_batch_id')
            ->selectRaw('inventory_batch_id, '.InventoryStock::balanceSql().' as balance')->pluck('balance', 'inventory_batch_id');
        $batches = InventoryBatch::with('item')->where('inventory_store_id', $store->id)->orderBy('expiry_date')->get()
            ->each(fn ($b) => $b->balance = (float) ($batchLeft[$b->id] ?? 0));

        $ledgerItem = $request->filled('item') ? InventoryItem::where('inventory_store_id', $store->id)->find($request->integer('item')) : null;
        $ledgerRows = collect();
        if ($ledgerItem) {
            $running = 0.0;
            $ledgerRows = InventoryMovement::with(['recorder', 'confirmer', 'batch', 'poultryHouse'])->where('inventory_item_id', $ledgerItem->id)
                ->orderBy('movement_date')->orderBy('id')->get()->each(function ($m) use (&$running) {
                    $in = in_array($m->movement_type, InventoryStock::IN_TYPES, true);
                    $m->counted = $m->receipt_status === 'confirmed';
                    if ($m->counted) $running += $in ? (float) $m->quantity : -(float) $m->quantity;
                    $m->direction = $in ? 'in' : 'out';
                    $m->running = $running;
                });
        }

        $dailyDate = $request->date('date')?->toDateString() ?? today()->toDateString();
        $daily = [
            'issued' => DB::table('inventory_movements')->join('inventory_items', 'inventory_items.id', '=', 'inventory_movements.inventory_item_id')
                ->where('inventory_movements.inventory_store_id', $store->id)->where('receipt_status', 'confirmed')
                ->whereIn('movement_type', ['issued', 'farm_use'])->whereDate('movement_date', $dailyDate)
                ->groupBy('inventory_items.name', 'inventory_items.unit')->selectRaw('inventory_items.name, inventory_items.unit, sum(quantity) as quantity')->get(),
            'received' => DB::table('inventory_movements')->join('inventory_items', 'inventory_items.id', '=', 'inventory_movements.inventory_item_id')
                ->where('inventory_movements.inventory_store_id', $store->id)->where('receipt_status', 'confirmed')
                ->where('movement_type', 'received')->whereDate('movement_date', $dailyDate)
                ->groupBy('inventory_items.name', 'inventory_items.unit')->selectRaw('inventory_items.name, inventory_items.unit, sum(quantity) as quantity')->get(),
            'adjustments' => InventoryMovement::with('item')->where('inventory_store_id', $store->id)->whereIn('movement_type', self::ADJUSTMENTS)
                ->whereDate('movement_date', $dailyDate)->get(),
        ];

        $requests = \App\Models\InventoryRequest::with(['requester', 'lines.item'])->where('inventory_store_id', $store->id)->latest('id')->limit(60)->get();

        return [
            'summary' => InventoryStock::summary($scopeStores),
            'batches' => $batches,
            'adjustments' => InventoryMovement::with(['item', 'recorder', 'confirmer', 'batch'])->where('inventory_store_id', $store->id)
                ->whereIn('movement_type', self::ADJUSTMENTS)->orderByRaw("case when receipt_status='awaiting_approval' then 0 else 1 end")
                ->latest('movement_date')->latest('id')->limit(60)->get(),
            'recentReceipts' => InventoryMovement::with(['item', 'recorder', 'confirmer', 'batch'])->where('inventory_store_id', $store->id)
                ->where('movement_type', 'received')->where('receipt_status', 'confirmed')->latest('movement_date')->latest('id')->limit(40)->get(),
            'ledgerItem' => $ledgerItem,
            'ledgerRows' => $ledgerRows,
            'dailyDate' => $dailyDate,
            'daily' => $daily,
            'storeRequests' => $requests,
            'canDecideAdjustments' => self::isFinanceApprover($user),
            'adjustmentReasons' => InventoryStock::ADJUSTMENT_REASONS,
            'tracksExpiry' => in_array($store->code, InventoryStock::EXPIRY_STORES, true),
        ];
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
        $d=$request->validate(['inventory_item_id'=>['required','exists:inventory_items,id'],'poultry_house_id'=>['nullable','exists:poultry_houses,id'],'construction_project_id'=>['nullable','exists:construction_projects,id'],'movement_date'=>['required','date','before_or_equal:today'],'movement_type'=>['required',Rule::in($types)],'location'=>['required','string','max:100'],'destination_location'=>['nullable','required_if:movement_type,transfer','different:location','string','max:100'],'quantity'=>['required','numeric','min:0.01'],'unit_cost'=>['nullable','numeric','min:0'],'total_cost'=>['nullable','numeric','min:0'],'supplier_or_recipient'=>['nullable','required_if:movement_type,farm_use','string','max:160'],'reference'=>['nullable','string','max:100'],'notes'=>['nullable','string','max:2000']]);
        $item=InventoryItem::findOrFail($d['inventory_item_id']); $right=in_array($d['movement_type'],['received','return'],true)?'can_receive':(str_starts_with($d['movement_type'],'adjustment')?'can_adjust':'can_issue'); $this->requireRight($request,(int)$item->inventory_store_id,$right); $d['inventory_store_id']=$item->inventory_store_id;
        $qty=(float)$d['quantity']; $source=$d['location']; if(in_array($d['movement_type'],self::OUT,true)||$d['movement_type']==='transfer') abort_if($qty>$this->balance($item->id,$source),422,'This movement exceeds the available stock at '.$source.'.');
        if (! in_array($d['movement_type'], ['issued','farm_use','return'], true)) $d['poultry_house_id']=null;
        // Building materials always go to a construction project.
        $isBuildingStore = DB::table('inventory_stores')->where('id', $item->inventory_store_id)->value('code') === 'building';
        if ($isBuildingStore && in_array($d['movement_type'], ['issued','farm_use'], true) && empty($d['construction_project_id'])) {
            return back()->withErrors(['construction_project_id' => 'Choose the construction project the materials are going to.'])->withInput();
        }
        if (! $isBuildingStore || ! in_array($d['movement_type'], ['issued','farm_use','return'], true)) $d['construction_project_id'] = null;
        if ($d['movement_type']==='return' && ! empty($d['poultry_house_id'])) abort_if($qty>$this->houseBalance($item->id,(int)$d['poultry_house_id']),422,'The return exceeds the quantity currently recorded at that poultry house.');
        if(isset($d['total_cost']))$d['unit_cost']=round((float)$d['total_cost']/$qty,2); elseif(isset($d['unit_cost']))$d['total_cost']=round($qty*(float)$d['unit_cost'],2); else{$d['unit_cost']=null;$d['total_cost']=null;}
        $destination=$d['destination_location']??null; unset($d['destination_location']);

        // Adjustments and losses wait for the Finance Manager before they change the balance.
        $isAdjustment = in_array($d['movement_type'], self::ADJUSTMENTS, true);
        $tracked = InventoryStock::tracksExpiry($item);
        $extra = $request->validate([
            'adjustment_reason' => [$isAdjustment ? 'required' : 'nullable', Rule::in(array_keys(InventoryStock::ADJUSTMENT_REASONS))],
            'inventory_batch_id' => ['nullable', Rule::exists('inventory_batches', 'id')->where('inventory_item_id', $item->id)],
            'batch_code' => [$tracked && $d['movement_type'] === 'received' ? 'required' : 'nullable', 'string', 'max:80'],
            'expiry_date' => [$tracked && $d['movement_type'] === 'received' ? 'required' : 'nullable', 'date'],
        ], ['batch_code.required' => 'Give the batch or lot number on the package.', 'expiry_date.required' => 'Give the expiry date on the package.']);

        DB::transaction(function () use ($d, $destination, $request, $item, $isAdjustment, $tracked, $extra) {
            $by = ['recorded_by' => $request->user()->id];
            if ($d['movement_type'] === 'transfer') {
                $ref = $d['reference'] ?: 'TRANSFER-'.now()->format('YmdHis');
                InventoryMovement::create(array_merge($d, ['movement_type' => 'transfer_out', 'reference' => $ref], $by));
                InventoryMovement::create(array_merge($d, ['movement_type' => 'transfer_in', 'location' => $destination, 'reference' => $ref], $by));
            } elseif ($isAdjustment) {
                $movement = InventoryMovement::create($d + $by + ['receipt_status' => 'awaiting_approval',
                    'adjustment_reason' => $extra['adjustment_reason'], 'inventory_batch_id' => $extra['inventory_batch_id'] ?? null]);
                $this->notifyFinance($request, 'Stock adjustment waiting for your approval',
                    InventoryStock::ADJUSTMENT_REASONS[$extra['adjustment_reason']].': '.$d['quantity'].' '.$item->unit.' of '.$item->name.' ('.$movement->movement_type.').');
            } elseif ($d['movement_type'] === 'received' && $tracked) {
                $movement = InventoryMovement::create($d + $by);
                $batch = InventoryBatch::create(['inventory_item_id' => $item->id, 'inventory_store_id' => $item->inventory_store_id,
                    'batch_code' => $extra['batch_code'], 'expiry_date' => $extra['expiry_date'], 'received_on' => $d['movement_date'],
                    'quantity_received' => $d['quantity'], 'created_by' => $request->user()->id]);
                $movement->update(['inventory_batch_id' => $batch->id]);
            } elseif (in_array($d['movement_type'], ['issued', 'farm_use'], true) && $tracked) {
                // First to expire, first out; expired stock is never issued.
                foreach (InventoryStock::allocate($item, (float) $d['quantity']) as $part) {
                    $share = $part['quantity'] / (float) $d['quantity'];
                    InventoryMovement::create(array_merge($d, $by, ['quantity' => $part['quantity'], 'inventory_batch_id' => $part['batch_id'],
                        'total_cost' => isset($d['total_cost']) ? round((float) $d['total_cost'] * $share, 2) : null]));
                }
            } else {
                InventoryMovement::create($d + $by);
            }
            AuditLog::record('inventory_movement', null, ucwords(str_replace('_', ' ', $d['movement_type'])).' '.$d['quantity'].' '.$item->unit.' of '.$item->name.'.', null, $d, 'inventory');
        });

        return back()->with('status', $isAdjustment ? 'Sent to the Finance Manager for approval. The balance changes once it is approved.' : 'Stock movement recorded.');
    }

    /** Finance Manager: approve or reject an adjustment or loss. Nothing is deleted; a rejection stays in the ledger. */
    public function decideAdjustment(Request $request, InventoryMovement $movement): RedirectResponse
    {
        abort_unless($this->isFinanceApprover($request->user()), 403, 'Only the Finance Manager approves stock adjustments and losses.');
        abort_unless($movement->receipt_status === 'awaiting_approval', 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'decision_note' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000']],
            ['decision_note.required_if' => 'Say why the adjustment is rejected.']);
        if ($data['decision'] === 'approve' && in_array($movement->movement_type, self::OUT, true)) {
            abort_if((float) $movement->quantity > $this->balance($movement->inventory_item_id, $movement->location), 422,
                'The store no longer holds '.number_format((float) $movement->quantity, 2).' at '.$movement->location.'. Ask the store to recount.');
        }
        $status = $data['decision'] === 'approve' ? 'confirmed' : 'rejected';
        $movement->update(['receipt_status' => $status, 'confirmed_by' => $request->user()->id, 'confirmed_at' => now(),
            'notes' => trim(($movement->notes ? $movement->notes.' ' : '').(! empty($data['decision_note']) ? 'Finance: '.$data['decision_note'] : ''))]);
        AuditLog::record('inventory_adjustment_'.$status, $movement, ucfirst($status === 'confirmed' ? 'approved' : 'rejected').' '.$movement->movement_type.' of '.$movement->quantity.' '.$movement->item->unit.' '.$movement->item->name.'.',
            ['receipt_status' => 'awaiting_approval'], ['receipt_status' => $status], 'inventory');

        return back()->with('status', $status === 'confirmed' ? 'Adjustment approved. The balance has changed.' : 'Adjustment rejected. It stays in the ledger as rejected.');
    }

    public static function isFinanceApprover($user): bool
    {
        return $user->isSuperuser() || $user->roles->pluck('code')->push($user->role?->code)->contains('finance-manager');
    }

    private function notifyFinance(Request $request, string $subject, string $body): void
    {
        $financeIds = \App\Models\User::where('is_active', true)->where(fn ($q) => $q->whereHas('role', fn ($r) => $r->where('code', 'finance-manager'))
            ->orWhereHas('roles', fn ($r) => $r->where('code', 'finance-manager')))->pluck('id');
        foreach ($financeIds as $id) {
            \App\Models\Communication::create(['kind' => 'notification', 'subject' => $subject, 'body' => $body, 'audience' => 'individual',
                'recipient_id' => $id, 'created_by' => $request->user()->id, 'action_url' => route('inventory.index', ['screen' => 'adjustments'])]);
        }
    }

    public function confirmReceipt(Request $request, InventoryMovement $movement): RedirectResponse
    {
        $this->authorizeWrite($request);
        abort_unless($movement->movement_type === 'received' && $movement->receipt_status === 'pending', 404);
        $this->requireRight($request, (int) $movement->inventory_store_id, 'can_receive');
        $tracked = InventoryStock::tracksExpiry($movement->item);
        $data = $request->validate([
            'quantity_received' => ['required', 'numeric', 'min:0.01'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'receipt_note' => ['nullable', 'string', 'max:1000'],
            'batch_code' => [$tracked ? 'required' : 'nullable', 'string', 'max:80'],
            'expiry_date' => [$tracked ? 'required' : 'nullable', 'date'],
        ], ['batch_code.required' => 'Give the batch or lot number on the package.', 'expiry_date.required' => 'Give the expiry date on the package.']);
        $ordered = (float) $movement->quantity;
        $received = (float) $data['quantity_received'];
        $note = trim((string) ($data['receipt_note'] ?? ''));
        if ($received !== $ordered) {
            $note = trim(($note ? $note.' ' : '').'Finance recorded '.number_format($ordered, 2).'; store received '.number_format($received, 2).'.');
        }
        $movement->update([
            'quantity' => $received,
            'unit_cost' => $movement->total_cost !== null ? round((float) $movement->total_cost / $received, 2) : $movement->unit_cost,
            'movement_date' => $data['received_on'],
            'receipt_status' => 'confirmed',
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
            'notes' => trim(($movement->notes ? $movement->notes.' ' : '').$note),
        ]);
        if ($tracked) {
            $batch = InventoryBatch::create(['inventory_item_id' => $movement->inventory_item_id, 'inventory_store_id' => $movement->inventory_store_id,
                'batch_code' => $data['batch_code'], 'expiry_date' => $data['expiry_date'], 'received_on' => $data['received_on'],
                'quantity_received' => $received, 'created_by' => $request->user()->id]);
            $movement->update(['inventory_batch_id' => $batch->id]);
        }
        AuditLog::record('inventory_receipt_confirmed', $movement, $received.' '.$movement->item->unit.' of '.$movement->item->name.' confirmed received by '.$request->user()->displayName().'.', ['receipt_status'=>'pending','quantity'=>$ordered], ['receipt_status'=>'confirmed','quantity'=>$received], 'inventory');

        return back()->with('status', 'Receipt confirmed. The item is now included in store stock.');
    }

    /** Director: assign stores to one person. Clearing every View box returns them to their role's stores. */
    public function updateUserStoreAccess(Request $request, \App\Models\User $person): RedirectResponse
    {
        abort_unless($request->user()->roles->contains('code','director') || $request->user()->role?->code==='director' || $request->user()->isSuperuser(), 403, 'Only the Director can change store access.');
        $keys = ['can_view','can_receive','can_issue','can_adjust','can_view_value'];
        $data = $request->validate(['stores' => ['nullable','array'], 'stores.*' => ['array'], 'stores.*.*' => ['nullable','boolean']]);
        $before = DB::table('inventory_store_user')->where('user_id', $person->id)->pluck('inventory_store_id')->all();
        DB::transaction(function () use ($data, $keys, $person) {
            DB::table('inventory_store_user')->where('user_id', $person->id)->delete();
            foreach ($data['stores'] ?? [] as $storeId => $rights) {
                if (empty($rights['can_view']) || ! InventoryStore::whereKey($storeId)->exists()) continue;
                $values = [];
                foreach ($keys as $key) $values[$key] = (bool) ($rights[$key] ?? false);
                DB::table('inventory_store_user')->insert(['inventory_store_id' => $storeId, 'user_id' => $person->id] + $values + ['created_at' => now(), 'updated_at' => now()]);
            }
        });
        $after = DB::table('inventory_store_user')->where('user_id', $person->id)->pluck('inventory_store_id')->all();
        AuditLog::record('inventory_store_access_person', $person, 'Store assignments changed for '.$person->displayName(), ['stores' => $before], ['stores' => $after], 'inventory');
        return back()->with('status', $after ? $person->displayName().' now handles '.count($after).' '.\Illuminate\Support\Str::plural('store', count($after)).'.' : $person->displayName().' now follows their role\'s store access.');
    }

    public function updateStoreAccess(Request $request,InventoryStore $store,Role $role):RedirectResponse
    {
        abort_unless($request->user()->roles->contains('code','director')||$request->user()->role?->code==='director',403,'Only the Director can change store access.');
        $keys=['can_view','can_receive','can_issue','can_adjust','can_view_value']; $d=$request->validate(array_fill_keys($keys,['nullable','boolean'])); $values=[]; foreach($keys as $key)$values[$key]=(bool)($d[$key]??false);
        DB::table('inventory_store_role')->updateOrInsert(['inventory_store_id'=>$store->id,'role_id'=>$role->id],$values+['created_at'=>now(),'updated_at'=>now()]); return back()->with('status',$role->name.' access updated.');
    }

    /** Active stores this person may view, each with its access rights (also used to build the store tabs). */
    public static function storesFor($user){$stores=InventoryStore::where('is_active',true)->orderBy('section')->orderBy('name')->get();if($user->isSuperuser())return $stores->each(fn($s)=>$s->access=array_fill_keys(['can_view','can_receive','can_issue','can_adjust','can_view_value'],true));$personal=DB::table('inventory_store_user')->where('user_id',$user->id)->get();$grants=$personal->isNotEmpty()?$personal->groupBy('inventory_store_id'):DB::table('inventory_store_role')->whereIn('role_id',$user->roleIds())->get()->groupBy('inventory_store_id');return $stores->filter(function($s)use($grants){$r=$grants->get($s->id,collect());$s->access=['can_view'=>$r->contains('can_view',true),'can_receive'=>$r->contains('can_receive',true),'can_issue'=>$r->contains('can_issue',true),'can_adjust'=>$r->contains('can_adjust',true),'can_view_value'=>$r->contains('can_view_value',true)];return $s->access['can_view'];})->values();}
    private function requireRight(Request $r,int $storeId,string $right):void{$s=$this->storesFor($r->user())->firstWhere('id',$storeId);abort_unless($s&&($s->access[$right]??false),403,'You do not have permission for this action in that store.');}
    private function balance(int $id,string $location):float{return(float)DB::table('inventory_movements')->where('inventory_item_id',$id)->where('location',$location)->where('receipt_status','confirmed')->selectRaw("coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end),0) balance")->value('balance');}
    private function houseBalance(int $itemId,int $houseId):float{return(float)DB::table('inventory_movements')->where('inventory_item_id',$itemId)->where('poultry_house_id',$houseId)->selectRaw("coalesce(sum(case when movement_type in ('issued','farm_use') then quantity when movement_type='return' then -quantity else 0 end),0) balance")->value('balance');}
    private function authorizeWrite(Request $r):void{abort_unless($r->user()->canDo('inventory','create'),403);}
    private function isStoreManager($user):bool{return $user->role?->code==='store-keeper'||$user->roles->contains('code','store-keeper');}
}
