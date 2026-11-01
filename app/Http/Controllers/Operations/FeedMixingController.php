<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, InventoryStore, PoultryHouse, User};
use App\Services\{CommunicationService, FeedStockService};
use App\Support\MixedFeedStock;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Feed mixing, end to end: ingredients taken from feed stock for mixing; the mixed feed sent to stores; each store
 * manager records what arrived; houses get their mixed feed from one store, and attendants' feed comes out of it.
 */
class FeedMixingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canMix($user) || $user->canViewAllReports(), 403);
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $f['from'] ?? now()->subDays(30)->toDateString();
        $to = $f['to'] ?? now()->toDateString();

        $mixings = DB::table('feed_mixings')->leftJoin('users', 'users.id', '=', 'feed_mixings.recorded_by')
            ->whereBetween('mixed_on', [$from, $to])->orderByDesc('mixed_on')->orderByDesc('feed_mixings.id')
            ->get(['feed_mixings.*', 'users.name as recorder']);
        $ingredients = DB::table('feed_mixing_ingredients')->whereIn('feed_mixing_id', $mixings->pluck('id'))->get()->groupBy('feed_mixing_id');
        $dispatches = DB::table('feed_mixing_dispatches')->join('inventory_stores', 'inventory_stores.id', '=', 'feed_mixing_dispatches.inventory_store_id')
            ->leftJoin('users', 'users.id', '=', 'feed_mixing_dispatches.received_by')
            ->whereIn('feed_mixing_id', $mixings->pluck('id'))->get(['feed_mixing_dispatches.*', 'inventory_stores.name as store', 'users.name as receiver'])->groupBy('feed_mixing_id');

        // Mixed feed on its way to the stores this person receives for.
        $toReceive = DB::table('feed_mixing_dispatches')->join('feed_mixings', 'feed_mixings.id', '=', 'feed_mixing_dispatches.feed_mixing_id')
            ->join('inventory_stores', 'inventory_stores.id', '=', 'feed_mixing_dispatches.inventory_store_id')
            ->where('feed_mixing_dispatches.status', 'sent')->whereIn('feed_mixing_dispatches.inventory_store_id', $this->receivableStoreIds($user))
            ->orderBy('feed_mixings.mixed_on')->get(['feed_mixing_dispatches.*', 'feed_mixings.reference', 'feed_mixings.mixed_on', 'inventory_stores.name as store']);

        $stores = MixedFeedStock::stores();
        $balances = DB::table('feed_stock_movements')->where('unit_code', 'poultry')->select('feed_name')
            ->selectRaw("sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity_kg else -quantity_kg end) as kg")
            ->groupBy('feed_name')->pluck('kg', 'feed_name');

        return view('operations.feed-mixing.index', [
            'mixings' => $mixings, 'ingredients' => $ingredients, 'dispatches' => $dispatches, 'toReceive' => $toReceive,
            'stores' => $stores, 'feeds' => FeedStockController::feedNames(), 'balances' => $balances,
            'houses' => PoultryHouse::where('is_active', true)->orderBy('house_number')->get(),
            'from' => $from, 'to' => $to,
            'canMix' => $this->canMix($user), 'canManage' => $this->canManage($user),
            'people' => \App\Models\Employee::where('status', 'active')->orderBy('first_name')->get()->map(fn ($e) => trim($e->first_name.' '.$e->last_name))->filter()->values(),
        ]);
    }

    /** Ingredients taken for mixing, the mixed feed made, and how much went to each store. */
    public function store(Request $request, FeedStockService $stock, CommunicationService $communications): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canMix($user), 403);
        $data = $request->validate([
            'mixed_on' => ['required', 'date', 'before_or_equal:today'],
            'taken_by' => ['required', 'string', 'max:160'],
            'ingredients' => ['required', 'array'],
            'ingredients.*' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'mixed_kg' => ['required', 'numeric', 'min:1', 'max:200000'],
            'send' => ['required', 'array'],
            'send.*' => ['nullable', 'numeric', 'min:0', 'max:200000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['mixed_kg.required' => 'Enter how much mixed feed was made.']);
        $ingredients = collect($data['ingredients'])->map(fn ($kg) => (float) $kg)->filter(fn ($kg) => $kg > 0)->only(FeedStockController::feedNames());
        $sends = collect($data['send'])->map(fn ($kg) => (float) $kg)->filter(fn ($kg) => $kg > 0);
        $storeIds = InventoryStore::where('holds_mixed_feed', true)->where('is_active', true)->pluck('id')->all();
        $fail = fn ($field, $message) => throw \Illuminate\Validation\ValidationException::withMessages([$field => $message]);
        if ($ingredients->isEmpty()) $fail('ingredients', 'Enter the kilograms of each ingredient taken for mixing.');
        if ($sends->isEmpty()) $fail('send', 'Enter how much mixed feed went to each store.');
        if ($sends->keys()->diff($storeIds)->isNotEmpty()) $fail('send', 'One of the stores is not a mixed feed store.');
        if (round($sends->sum(), 2) !== round((float) $data['mixed_kg'], 2)) {
            $fail('send', 'The feed sent to the stores ('.MixedFeedStock::kg($sends->sum()).' kg) must add up to the mixed feed made ('.MixedFeedStock::kg((float) $data['mixed_kg']).' kg).');
        }

        $reference = DB::transaction(function () use ($data, $ingredients, $sends, $stock, $user) {
            $next = (int) DB::table('feed_mixings')->max('id') + 1;
            $reference = 'MIX-'.now()->format('y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $id = DB::table('feed_mixings')->insertGetId([
                'reference' => $reference, 'unit_code' => 'poultry', 'mixed_on' => $data['mixed_on'], 'taken_by' => $data['taken_by'],
                'mixed_kg' => $data['mixed_kg'], 'notes' => $data['notes'] ?? null, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($ingredients as $feed => $kg) {
                DB::table('feed_mixing_ingredients')->insert(['feed_mixing_id' => $id, 'feed_name' => $feed, 'quantity_kg' => $kg, 'created_at' => now(), 'updated_at' => now()]);
                // Taken out of feed stock; refused if the store does not have that much.
                $stock->issue(['unit_code' => 'poultry', 'movement_date' => $data['mixed_on'], 'movement_type' => 'consumed', 'feed_name' => $feed,
                    'quantity_kg' => $kg, 'reference' => $reference, 'notes' => 'Taken for mixing by '.$data['taken_by'], 'recorded_by' => $user->id]);
            }
            foreach ($sends as $storeId => $kg) {
                DB::table('feed_mixing_dispatches')->insert(['feed_mixing_id' => $id, 'inventory_store_id' => $storeId, 'sent_kg' => $kg, 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);
            }
            return $reference;
        });

        AuditLog::record('feed_mixed', null, "{$reference}: ".MixedFeedStock::kg((float) $data['mixed_kg'])." kg of mixed feed made from ".MixedFeedStock::kg($ingredients->sum())." kg of ingredients.", null, ['reference' => $reference], 'feed-received');
        foreach (['store-keeper', 'inventory-officer'] as $role) {
            $communications->toRole($role, 'Mixed feed on its way: '.$reference, 'Record the mixed feed when it arrives at your store.', $user->id, route('operations.feed-mixing.index', [], false));
        }
        return redirect()->route('operations.feed-mixing.index')->with('status', "Mixing {$reference} saved. The ingredients were taken out of feed stock and the stores were told the mixed feed is coming.");
    }

    /** The store manager records the mixed feed that arrived; it is added to the store's mixed feed. */
    public function receive(Request $request, int $dispatch): RedirectResponse
    {
        $user = $request->user();
        $row = DB::table('feed_mixing_dispatches')->where('id', $dispatch)->first();
        abort_unless($row, 404);
        abort_unless(in_array((int) $row->inventory_store_id, $this->receivableStoreIds($user), true), 403, 'You do not receive for this store.');
        abort_if($row->status === 'received', 422, 'This mixed feed has already been received.');
        $data = $request->validate(['received_kg' => ['required', 'numeric', 'min:0', 'max:200000'], 'receive_note' => ['nullable', 'string', 'max:500']]);
        $reference = DB::table('feed_mixings')->where('id', $row->feed_mixing_id)->value('reference');

        DB::transaction(function () use ($row, $data, $user, $reference) {
            DB::table('feed_mixing_dispatches')->where('id', $row->id)->update(['received_kg' => $data['received_kg'], 'receive_note' => $data['receive_note'] ?? null,
                'status' => 'received', 'received_by' => $user->id, 'received_at' => now(), 'updated_at' => now()]);
            if ((float) $data['received_kg'] > 0) {
                DB::table('mixed_feed_movements')->insert(['inventory_store_id' => $row->inventory_store_id, 'movement_date' => now()->toDateString(), 'movement_type' => 'received',
                    'quantity_kg' => $data['received_kg'], 'feed_mixing_dispatch_id' => $row->id, 'reference' => $reference, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $short = round((float) $row->sent_kg - (float) $data['received_kg'], 2);
        AuditLog::record('mixed_feed_received', null, "{$reference}: ".MixedFeedStock::kg((float) $data['received_kg']).' kg of mixed feed received'.($short > 0 ? ', '.MixedFeedStock::kg($short).' kg less than sent' : '').'.', null, ['dispatch' => $row->id], 'feed-received');
        return back()->with('status', 'Mixed feed received'.($short > 0 ? ' ('.MixedFeedStock::kg($short).' kg less than was sent; the difference is shown on the mixing).' : '.'));
    }

    /** A store that holds mixed feed: a new one, or an existing store marked as such. */
    public function saveStore(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $store = InventoryStore::whereRaw('lower(name) = ?', [mb_strtolower(trim($data['name']))])->first();
        if ($store) {
            $store->update(['holds_mixed_feed' => true, 'is_active' => true]);
        } else {
            $store = InventoryStore::create(['code' => 'MF-'.strtoupper(\Illuminate\Support\Str::random(5)), 'name' => trim($data['name']), 'section' => 'Production',
                'description' => 'Holds mixed feed for the poultry houses.', 'is_active' => true, 'holds_mixed_feed' => true]);
            // The farm's store roles can see and receive for it, as for the other production stores.
            foreach (DB::table('roles')->whereIn('code', ['store-keeper', 'inventory-officer', 'production-manager', 'ceo', 'director'])->pluck('id') as $roleId) {
                DB::table('inventory_store_role')->insertOrIgnore(['inventory_store_id' => $store->id, 'role_id' => $roleId, 'can_view' => true,
                    'can_receive' => true, 'can_issue' => true, 'can_adjust' => false, 'can_view_value' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        return back()->with('status', "{$store->name} now holds mixed feed. Assign the houses it feeds.");
    }

    /** Which store each house gets its mixed feed from. */
    public function assignHouses(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        $storeIds = InventoryStore::where('holds_mixed_feed', true)->pluck('id')->all();
        $data = $request->validate(['house_store' => ['array'], 'house_store.*' => ['nullable', Rule::in($storeIds)]]);
        foreach ($data['house_store'] ?? [] as $houseId => $storeId) {
            PoultryHouse::whereKey((int) $houseId)->update(['feed_store_id' => $storeId ?: null]);
        }
        return back()->with('status', 'House stores saved.');
    }

    private function canMix(User $user): bool
    {
        return $user->isSuperuser() || $user->canDo('feed-received', 'create') || $this->hasRole($user, ['production-manager', 'store-keeper', 'inventory-officer']);
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperuser() || $this->hasRole($user, ['production-manager', 'store-keeper', 'inventory-officer']);
    }

    /** Stores this person records mixed feed arrivals for. */
    private function receivableStoreIds(User $user): array
    {
        $ids = InventoryStore::where('holds_mixed_feed', true)->pluck('id');
        if ($user->isSuperuser()) return $ids->map(fn ($id) => (int) $id)->all();
        return DB::table('inventory_store_role')->whereIn('inventory_store_id', $ids)->whereIn('role_id', $user->roleIds())->where('can_receive', true)
            ->pluck('inventory_store_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function hasRole(User $user, array $codes): bool
    {
        return in_array($user->role?->code, $codes, true) || $user->roles->pluck('code')->intersect($codes)->isNotEmpty();
    }
}
