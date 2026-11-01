<?php

namespace App\Services;

use App\Models\DailySalesBalance;
use App\Models\Sale;
use App\Models\SalesPayment;
use App\Models\User;

/**
 * A sales person's daily sales sheet: their sales in a date range, the cash brought
 * forward, and the cash they should be holding (cash received minus cash handed over).
 */
class DailySalesSheet
{
    public function for(User $user, string $from, string $to): array
    {
        $sales = Sale::with(['payments', 'customer'])->where('sold_by', $user->id)->whereBetween('sale_date', [$from, $to])->where('status', '!=', 'void')->orderBy('sale_date')->orderBy('created_at')->get();
        $payments = SalesPayment::whereBetween('paid_on', [$from, $to])->whereHas('sale', fn ($query) => $query->where('sold_by', $user->id)->where('status', '!=', 'void'))->get();
        $priorCash = (float) SalesPayment::whereDate('paid_on', '<', $from)->where('payment_method', 'cash')->whereHas('sale', fn ($query) => $query->where('sold_by', $user->id)->where('status', '!=', 'void'))->sum('amount');
        $priorTransfers = (float) DailySalesBalance::where('sales_person_id', $user->id)->whereDate('business_date', '<', $from)->sum('amount_transferred');
        $openingBalance = max(0, $priorCash - $priorTransfers);
        $rangeTransfers = (float) DailySalesBalance::where('sales_person_id', $user->id)->whereBetween('business_date', [$from, $to])->sum('amount_transferred');

        return [
            'dailySales' => $sales,
            'dailyPayments' => $payments,
            'salesFrom' => $from,
            'salesTo' => $to,
            'openingBalance' => $openingBalance,
            'cashAvailable' => max(0, $openingBalance + (float) $payments->where('payment_method', 'cash')->sum('amount') - $rangeTransfers),
        ];
    }
}
