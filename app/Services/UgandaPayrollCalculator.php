<?php

namespace App\Services;

class UgandaPayrollCalculator
{
    /** Uganda resident employee PAYE on total chargeable income for one month. */
    public function residentPaye(float $monthlyIncome): float
    {
        $income = max(0, $monthlyIncome);

        $tax = match (true) {
            $income <= 235000 => 0,
            $income <= 335000 => ($income - 235000) * 0.10,
            $income <= 410000 => 10000 + (($income - 335000) * 0.20),
            $income <= 10000000 => 25000 + (($income - 410000) * 0.30),
            default => 25000 + (($income - 410000) * 0.30) + (($income - 10000000) * 0.10),
        };

        return round($tax, 2);
    }
}
