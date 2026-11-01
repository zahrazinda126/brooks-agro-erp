<?php

namespace App\Support;

/**
 * One place for how amounts appear on screen. Blade uses it through
 * @money($amount) or @money($amount, 2), registered in AppServiceProvider.
 */
class Money
{
    public const CURRENCY = 'UGX';

    public static function format($amount, int $decimals = 0): string
    {
        return self::CURRENCY.' '.number_format((float) $amount, $decimals);
    }
}
