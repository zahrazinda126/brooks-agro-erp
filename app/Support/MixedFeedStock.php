<?php

namespace App\Support;

use App\Models\PoultryHouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Mixed feed held in each store: received from mixing, given to the houses that draw from it. */
class MixedFeedStock
{
    public static function balance(int $storeId, ?string $exceptReference = null): float
    {
        return (float) DB::table('mixed_feed_movements')->where('inventory_store_id', $storeId)
            ->when($exceptReference, fn ($q, $ref) => $q->where(fn ($x) => $x->whereNull('reference')->orWhere('reference', '!=', $ref)))
            ->selectRaw("coalesce(sum(case when movement_type = 'received' then quantity_kg else -quantity_kg end), 0) as kg")->value('kg');
    }

    /** All stores that hold mixed feed, with their balance and the houses they feed. */
    public static function stores()
    {
        return \App\Models\InventoryStore::where('holds_mixed_feed', true)->where('is_active', true)->orderBy('name')->get()
            ->each(function ($store) {
                $store->setAttribute('balance_kg', self::balance($store->id));
                $store->setAttribute('houses', PoultryHouse::where('feed_store_id', $store->id)->orderBy('house_number')->pluck('house_number')->all());
            });
    }

    public static function kg(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    /**
     * Feed an attendant gave a house today comes out of the house's store. One entry per house per day:
     * saving again replaces it. Refused when the store has not received that much.
     */
    public static function giveToHouse(PoultryHouse $house, float $kg, int $userId): void
    {
        if (! $house->feed_store_id) return;
        $reference = 'feed-given:house-'.$house->id.':'.now()->toDateString();
        if (DB::getDriverName() === 'pgsql') DB::select('select pg_advisory_xact_lock(hashtext(?))', ['mixed-feed|'.$house->feed_store_id]);
        $available = round(self::balance($house->feed_store_id, $reference), 2);
        if ($kg > $available) {
            $store = DB::table('inventory_stores')->where('id', $house->feed_store_id)->value('name');
            throw ValidationException::withMessages([
                'quantity_kg' => "{$store} has only ".self::kg(max(0, $available)).' kg of mixed feed recorded. Ask the store manager to record the mixed feed that arrived.',
            ]);
        }
        DB::table('mixed_feed_movements')->where('reference', $reference)->delete();
        DB::table('mixed_feed_movements')->insert([
            'inventory_store_id' => $house->feed_store_id, 'movement_date' => now()->toDateString(), 'movement_type' => 'given',
            'quantity_kg' => $kg, 'poultry_house_id' => $house->id, 'reference' => $reference, 'recorded_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
