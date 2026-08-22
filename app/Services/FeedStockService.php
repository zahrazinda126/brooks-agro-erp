<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeedStockService
{
    private const INCOMING = ['received', 'return', 'adjustment_in', 'transfer_in'];

    public function balance(string $unitCode, string $feedName): float
    {
        return (float) DB::table('feed_stock_movements')
            ->where('unit_code', $unitCode)
            ->where('feed_name', $feedName)
            ->selectRaw(
                "coalesce(sum(case when movement_type in ('received','return','adjustment_in','transfer_in') then quantity_kg else -quantity_kg end), 0) as balance"
            )->value('balance');
    }

    /**
     * Record feed leaving stock, refusing the complete transaction when the
     * requested quantity is greater than the quantity currently available.
     */
    public function issue(array $movement): void
    {
        $unitCode = (string) $movement['unit_code'];
        $feedName = (string) $movement['feed_name'];
        $quantity = round((float) $movement['quantity_kg'], 2);

        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', [$unitCode.'|'.$feedName]);
        } else {
            DB::table('feed_stock_movements')
                ->where('unit_code', $unitCode)
                ->where('feed_name', $feedName)
                ->lockForUpdate()->get('id');
        }

        $available = round($this->balance($unitCode, $feedName), 2);
        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity_kg' => "Only ".number_format(max(0, $available), 2)." kg of {$feedName} is available. You cannot issue ".number_format($quantity, 2)." kg.",
            ]);
        }

        DB::table('feed_stock_movements')->insert($movement + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
