<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryRequest;
use App\Models\InventoryStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock figures for Inventory. Stock = opening + receipts + adjustments − issues − losses,
 * counting only confirmed movements (receipts confirmed by the store, adjustments approved by Finance).
 */
class InventoryStock
{
    /** Stores whose items are kept in batches with expiry dates (decided 24 Oct 2026). */
    public const EXPIRY_STORES = ['feed', 'veterinary', 'kitchen', 'farm'];

    public const IN_TYPES = ['received', 'return', 'adjustment_in', 'transfer_in'];

    /** Reasons for an adjustment or loss; each waits for the Finance Manager's approval. */
    public const ADJUSTMENT_REASONS = [
        'expired' => 'Expired',
        'damaged' => 'Damaged',
        'lost' => 'Lost or stolen',
        'count_difference' => 'Count difference',
        'other' => 'Other',
    ];

    public static function balanceSql(string $table = 'inventory_movements'): string
    {
        $in = "'".implode("','", self::IN_TYPES)."'";

        return "coalesce(sum(case when {$table}.movement_type in ($in) then {$table}.quantity else -{$table}.quantity end),0)";
    }

    public static function tracksExpiry(InventoryItem $item): bool
    {
        $code = $item->relationLoaded('store') ? $item->store?->code : InventoryStore::whereKey($item->inventory_store_id)->value('code');

        return in_array($code, self::EXPIRY_STORES, true);
    }

    public static function itemBalance(int $itemId): float
    {
        return (float) DB::table('inventory_movements')->where('inventory_item_id', $itemId)
            ->where('receipt_status', 'confirmed')->selectRaw(self::balanceSql().' as b')->value('b');
    }

    /** Batches of one item with what is left in each, earliest expiry first. */
    public static function batches(int $itemId): Collection
    {
        $left = DB::table('inventory_movements')->where('inventory_item_id', $itemId)->where('receipt_status', 'confirmed')
            ->whereNotNull('inventory_batch_id')->groupBy('inventory_batch_id')
            ->selectRaw('inventory_batch_id, '.self::balanceSql().' as balance')->pluck('balance', 'inventory_batch_id');

        return InventoryBatch::where('inventory_item_id', $itemId)->orderBy('expiry_date')->orderBy('id')->get()
            ->each(fn (InventoryBatch $batch) => $batch->balance = (float) ($left[$batch->id] ?? 0));
    }

    /**
     * Split an issue across stock, first to expire first out. Stock received before batches were
     * kept (no batch) goes first; expired batches are never issued.
     *
     * @return array<int, array{batch_id: ?int, quantity: float}>
     */
    public static function allocate(InventoryItem $item, float $quantity): array
    {
        if (! self::tracksExpiry($item)) {
            return [['batch_id' => null, 'quantity' => $quantity]];
        }
        $batches = self::batches($item->id);
        $unbatched = max(0, self::itemBalance($item->id) - $batches->sum('balance'));
        $parts = [];
        $need = $quantity;
        if ($unbatched > 0) {
            $take = min($need, $unbatched);
            $parts[] = ['batch_id' => null, 'quantity' => $take];
            $need -= $take;
        }
        foreach ($batches->filter(fn ($b) => $b->balance > 0 && ! $b->isExpired()) as $batch) {
            if ($need <= 0.0001) break;
            $take = min($need, $batch->balance);
            $parts[] = ['batch_id' => $batch->id, 'quantity' => round($take, 2)];
            $need -= $take;
        }
        if ($need > 0.0001) {
            $expired = $batches->filter(fn ($b) => $b->balance > 0 && $b->isExpired())->sum('balance');
            throw ValidationException::withMessages(['quantity' => 'Only '.number_format($quantity - $need, 2).' '.$item->unit.' of '.$item->name
                .' can be issued.'.($expired > 0 ? ' '.number_format($expired, 2).' '.$item->unit.' is expired and must be written off, not issued.' : '')]);
        }

        return $parts;
    }

    /** Latest confirmed purchase price of each item (for stock value). */
    public static function unitCosts(array $itemIds): Collection
    {
        return DB::table('inventory_movements')->whereIn('inventory_item_id', $itemIds)->where('movement_type', 'received')
            ->where('receipt_status', 'confirmed')->whereNotNull('unit_cost')
            ->orderBy('movement_date')->orderBy('id')->get(['inventory_item_id', 'unit_cost'])
            ->pluck('unit_cost', 'inventory_item_id')->map(fn ($c) => (float) $c);
    }

    /**
     * Summary tiles for the given stores: items in stock, critical (out of stock), low (at or below
     * reorder level), expiry alerts, adjustments waiting for approval, open requests and stock value.
     */
    public static function summary(Collection $stores): array
    {
        $ids = $stores->pluck('id');
        $items = InventoryItem::whereIn('inventory_store_id', $ids)->where('is_active', true)->select('inventory_items.*')
            ->selectSub(fn ($q) => $q->from('inventory_movements')->whereColumn('inventory_item_id', 'inventory_items.id')
                ->where('receipt_status', 'confirmed')->selectRaw(self::balanceSql()), 'balance')->get();
        $costs = self::unitCosts($items->pluck('id')->all());
        $batchLeft = DB::table('inventory_movements')->whereIn('inventory_store_id', $ids)->where('receipt_status', 'confirmed')
            ->whereNotNull('inventory_batch_id')->groupBy('inventory_batch_id')
            ->selectRaw('inventory_batch_id, '.self::balanceSql().' as balance')->pluck('balance', 'inventory_batch_id');
        $heldBatches = InventoryBatch::whereIn('inventory_store_id', $ids)->get()->filter(fn ($b) => (float) ($batchLeft[$b->id] ?? 0) > 0);

        return [
            'items' => $items->count(),
            'inStock' => $items->filter(fn ($i) => (float) $i->balance > 0)->count(),
            'critical' => $items->filter(fn ($i) => (float) $i->balance <= 0)->count(),
            'low' => $items->filter(fn ($i) => (float) $i->balance > 0 && (float) $i->balance <= (float) $i->reorder_level)->count(),
            'expiring' => $heldBatches->filter(fn ($b) => $b->isExpiringSoon())->count(),
            'expired' => $heldBatches->filter(fn ($b) => $b->isExpired())->count(),
            'awaitingApproval' => DB::table('inventory_movements')->whereIn('inventory_store_id', $ids)->where('receipt_status', 'awaiting_approval')->count(),
            'openRequests' => InventoryRequest::whereIn('inventory_store_id', $ids)->whereIn('status', InventoryRequest::OPEN)->count(),
            'value' => $items->sum(fn ($i) => max(0, (float) $i->balance) * (float) ($costs[$i->id] ?? 0)),
        ];
    }
}
