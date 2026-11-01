<?php

namespace App\Support;

use App\Http\Controllers\Operations\FeedStockController;
use App\Models\Payable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Three-way match for feed bills: for each feed on the bill, the kg billed, the kg the supplier said it
 * sent and the kg the farm weighed on arrival. A bill that charges for more than the farm received
 * (beyond a small tolerance), names no delivery, or covers a delivery not yet weighed is held.
 */
class FeedBillMatch
{
    public static function isFeedItem(?string $name): bool
    {
        return in_array(mb_strtolower(trim((string) $name)), array_map('mb_strtolower', FeedStockController::feedNames()), true);
    }

    public static function isFeedBill(Payable $bill): bool
    {
        return $bill->lines->contains(fn ($line) => self::isFeedItem($line->item_name));
    }

    /** Linked deliveries with their status. */
    public static function deliveries(Payable $bill): Collection
    {
        return DB::table('feed_delivery_verifications')->whereIn('id', $bill->feedDeliveryIds())->orderBy('delivery_date')->get(['id', 'delivery_date', 'reference', 'status', 'vehicle_number']);
    }

    /** One row per feed on the bill: billed, supplier and farm kg, and whether it is over-billed. */
    public static function rows(Payable $bill): Collection
    {
        $ids = $bill->feedDeliveryIds();
        $bags = DB::table('feed_delivery_bags')->whereIn('feed_delivery_verification_id', $ids)->get()->groupBy(fn ($bag) => mb_strtolower($bag->feed_name));
        $tolerance = (float) config('farm.feed_bill_tolerance_percent', 1) / 100;
        return $bill->lines->filter(fn ($line) => self::isFeedItem($line->item_name))->groupBy(fn ($line) => mb_strtolower($line->item_name))->map(function ($lines) use ($bags, $tolerance) {
            $feedBags = $bags->get(mb_strtolower($lines->first()->item_name), collect());
            $billed = (float) $lines->sum(fn ($line) => $line->unit === 'bag' ? (float) $line->quantity * 100 : (float) $line->quantity);
            $supplier = (float) $feedBags->sum('supplier_weight_kg');
            $weighed = $feedBags->isNotEmpty() && $feedBags->every(fn ($bag) => $bag->farm_weight_kg !== null);
            $farm = (float) $feedBags->sum('farm_weight_kg');
            $state = $feedBags->isEmpty() ? 'not_delivered' : (! $weighed ? 'awaiting_weighing' : ($billed > $farm * (1 + $tolerance) ? 'over_billed' : 'matched'));
            return (object) ['feed' => $lines->first()->item_name, 'billed' => $billed, 'supplier' => $supplier, 'farm' => $farm, 'difference' => $weighed ? round($billed - $farm, 2) : null, 'state' => $state];
        })->values();
    }

    /** Reasons the bill is held; empty when it can be paid. */
    public static function holds(Payable $bill): array
    {
        if (! self::isFeedBill($bill)) return [];
        if ($bill->feedDeliveryIds()->isEmpty()) return ['No feed delivery is linked to this bill.'];
        $holds = [];
        foreach (self::rows($bill) as $row) {
            if ($row->state === 'not_delivered') $holds[] = "{$row->feed} is billed but is not in the linked deliveries.";
            elseif ($row->state === 'awaiting_weighing') $holds[] = "{$row->feed} has not yet been weighed at the farm.";
            elseif ($row->state === 'over_billed' && ! $bill->variance_approved_at) $holds[] = "Billed ".number_format($row->billed, 1)." kg {$row->feed}, the farm received ".number_format($row->farm, 1).' kg. The CEO must approve the difference.';
        }
        return $holds;
    }

    public static function needsCeo(Payable $bill): bool
    {
        return ! $bill->variance_approved_at && self::rows($bill)->contains('state', 'over_billed');
    }

    /** Received deliveries that still have feed not on any bill (newest first), with per-feed kg for the bill form. */
    public static function billable(): Collection
    {
        $billedFeeds = DB::table('payable_feed_delivery')->join('payable_lines', 'payable_lines.payable_id', '=', 'payable_feed_delivery.payable_id')
            ->get(['payable_feed_delivery.feed_delivery_verification_id as delivery', 'payable_lines.item_name'])
            ->groupBy('delivery')->map(fn ($rows) => $rows->map(fn ($r) => mb_strtolower($r->item_name))->unique()->all());
        return DB::table('feed_delivery_verifications')->whereDate('delivery_date', '>=', now()->subDays(120))->latest('delivery_date')->get()
            ->map(function ($delivery) use ($billedFeeds) {
                $feeds = DB::table('feed_delivery_bags')->where('feed_delivery_verification_id', $delivery->id)->get()->groupBy('feed_name')
                    ->map(fn ($bags, $feed) => (object) ['feed' => $feed, 'supplier' => (float) $bags->sum('supplier_weight_kg'), 'farm' => $bags->every(fn ($b) => $b->farm_weight_kg !== null) ? (float) $bags->sum('farm_weight_kg') : null])
                    ->reject(fn ($row) => in_array(mb_strtolower($row->feed), $billedFeeds->get($delivery->id, []), true))->values();
                $delivery->open_feeds = $feeds;
                return $delivery;
            })->filter(fn ($delivery) => $delivery->open_feeds->isNotEmpty())->values();
    }
}
