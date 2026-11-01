<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SalesCustomer;

/**
 * A customer who has not bought anything for a year moves to Archived on their own; buying again brings
 * them back. Their history is kept either way. Runs daily and whenever the customer list is opened.
 */
class CustomerArchive
{
    public const DORMANT_DAYS = 365;

    public static function archiveDormant(): int
    {
        $cutoff = now()->subDays(self::DORMANT_DAYS)->toDateString();
        $dormant = SalesCustomer::where('is_active', true)
            ->whereDate('created_at', '<', $cutoff)
            ->whereDoesntHave('sales', fn ($sales) => $sales->where('status', '!=', 'void')->whereDate('sale_date', '>=', $cutoff))
            ->get();
        foreach ($dormant as $customer) {
            $customer->update(['is_active' => false]);
            AuditLog::record('customer_archived', $customer, 'Customer archived automatically: no purchase for a year.', null, ['is_active' => false], 'customers');
        }
        return $dormant->count();
    }

    /** An archived customer who buys again is active again. */
    public static function reactivate(?SalesCustomer $customer): void
    {
        if (! $customer || $customer->is_active) return;
        $customer->update(['is_active' => true]);
        AuditLog::record('customer_reactivated', $customer, 'Archived customer bought again and is active again.', null, ['is_active' => true], 'customers');
    }
}
