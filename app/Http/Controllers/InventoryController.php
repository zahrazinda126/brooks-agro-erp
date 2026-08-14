<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const CATEGORIES = ['medicine', 'vaccine', 'fuel', 'packaging', 'tools', 'equipment', 'cleaning', 'farm produce', 'supplies', 'other'];

    private const IN = ['received', 'return', 'adjustment_in', 'transfer_in'];

    private const OUT = ['issued', 'farm_use', 'wastage', 'adjustment_out', 'transfer_out'];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canAccess('inventory'), 403);
        $balanceSql = "sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end)";
        $items = InventoryItem::query()->select('inventory_items.*')->selectSub(fn ($q) => $q->from('inventory_movements')->whereColumn('inventory_item_id', 'inventory_items.id')->selectRaw("coalesce($balanceSql,0)"), 'balance')->orderBy('category')->orderBy('name')->get();
        $byLocation = DB::table('inventory_movements')->join('inventory_items', 'inventory_items.id', '=', 'inventory_movements.inventory_item_id')->select('inventory_items.name', 'inventory_items.unit', 'inventory_movements.location', DB::raw("$balanceSql as balance"))->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.unit', 'inventory_movements.location')->havingRaw("$balanceSql != 0")->orderBy('inventory_items.name')->orderBy('inventory_movements.location')->get();
        $movements = InventoryMovement::with(['item', 'recorder'])->latest('movement_date')->latest('id')->paginate(40)->withQueryString();
        $feed = DB::table('feed_stock_movements')->select('unit_code', 'feed_name', DB::raw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) as balance"))->groupBy('unit_code', 'feed_name')->havingRaw("sum(case when movement_type='received' then quantity_kg else -quantity_kg end) != 0")->orderBy('unit_code')->get();
        $eggStock = DB::table('egg_collection_lines')->join('egg_collections', 'egg_collections.id', '=', 'egg_collection_lines.egg_collection_id')->join('egg_grades', 'egg_grades.id', '=', 'egg_collection_lines.egg_grade_id')->where('egg_collections.status', 'approved')->select('egg_grades.name', 'egg_grades.eggs_per_tray', DB::raw('sum(egg_collection_lines.total_eggs) as eggs'))->groupBy('egg_grades.id', 'egg_grades.name', 'egg_grades.eggs_per_tray')->get();

        return view('inventory.index', ['items' => $items, 'byLocation' => $byLocation, 'movements' => $movements, 'feed' => $feed, 'eggStock' => $eggStock, 'categories' => self::CATEGORIES, 'canCreate' => $user->canDo('inventory', 'create')]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request);
        $d = $request->validate(['code' => ['required', 'string', 'max:50', 'unique:inventory_items,code'], 'name' => ['required', 'string', 'max:160'], 'category_choice' => ['required', Rule::in([...self::CATEGORIES, 'custom'])], 'custom_category' => ['nullable', 'required_if:category_choice,custom', 'string', 'max:60'], 'unit' => ['required', 'string', 'max:40'], 'reorder_level' => ['required', 'numeric', 'min:0'], 'default_location' => ['required', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $d['category'] = $d['category_choice'] === 'custom' ? strtolower(trim($d['custom_category'])) : $d['category_choice'];
        unset($d['category_choice'],$d['custom_category']);
        $item = InventoryItem::create($d + ['created_by' => $request->user()->id]);
        AuditLog::record('inventory_item_created', $item, 'Inventory item '.$item->code.' created.', null, $d, 'inventory');

        return back()->with('status', 'Inventory item added. Record its opening balance or first receipt below.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request);
        $types = ['return', ...self::OUT, 'transfer'];
        $d = $request->validate(['inventory_item_id' => ['required', 'exists:inventory_items,id'], 'movement_date' => ['required', 'date', 'before_or_equal:today'], 'movement_type' => ['required', Rule::in($types)], 'location' => ['required', 'string', 'max:100'], 'destination_location' => ['nullable', 'required_if:movement_type,transfer', 'different:location', 'string', 'max:100'], 'quantity' => ['required', 'numeric', 'min:0.01'], 'unit_cost' => ['nullable', 'numeric', 'min:0'], 'total_cost' => ['nullable', 'numeric', 'min:0'], 'supplier_or_recipient' => ['nullable', 'string', 'max:160'], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $item = InventoryItem::findOrFail($d['inventory_item_id']);
        $qty = (float) $d['quantity'];
        $source = $d['location'];
        if (in_array($d['movement_type'], self::OUT, true) || $d['movement_type'] === 'transfer') {
            abort_if($qty > $this->balance($item->id, $source), 422, 'This movement exceeds the available stock at '.$source.'.');
        }
        if (isset($d['total_cost'])) {
            $d['unit_cost'] = round((float) $d['total_cost'] / $qty, 2);
        } elseif (isset($d['unit_cost'])) {
            $d['total_cost'] = round($qty * (float) $d['unit_cost'], 2);
        } else {
            $d['unit_cost'] = null;
            $d['total_cost'] = null;
        }$destination = $d['destination_location'] ?? null;
        unset($d['destination_location']);
        DB::transaction(function () use ($d, $destination, $request, $item) {
            if ($d['movement_type'] === 'transfer') {
                $reference = $d['reference'] ?: 'TRANSFER-'.now()->format('YmdHis');
                InventoryMovement::create(array_merge($d, ['movement_type' => 'transfer_out', 'reference' => $reference, 'recorded_by' => $request->user()->id]));
                InventoryMovement::create(array_merge($d, ['movement_type' => 'transfer_in', 'location' => $destination, 'reference' => $reference, 'recorded_by' => $request->user()->id]));
            } else {
                InventoryMovement::create($d + ['recorded_by' => $request->user()->id]);
            }AuditLog::record('inventory_movement', null, ucwords(str_replace('_', ' ', $d['movement_type'])).' '.$d['quantity'].' '.$item->unit.' of '.$item->name.'.', null, $d, 'inventory');
        });

        return back()->with('status', 'Stock movement recorded.');
    }

    private function balance(int $itemId, string $location): float
    {
        return (float) DB::table('inventory_movements')->where('inventory_item_id', $itemId)->where('location', $location)->selectRaw("coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity else -quantity end),0) as balance")->value('balance');
    }

    private function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()->canDo('inventory', 'create'), 403);
    }
}
