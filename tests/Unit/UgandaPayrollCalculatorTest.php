<?php

namespace Tests\Unit;

use App\Services\UgandaPayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UgandaPayrollCalculatorTest extends TestCase
{
    #[DataProvider('residentMonthlyIncomeCases')]
    public function test_resident_monthly_paye(float $income, float $expected): void
    {
        $this->assertSame($expected, (new UgandaPayrollCalculator)->residentPaye($income));
    }

    public static function residentMonthlyIncomeCases(): array
    {
        return [
            'tax free threshold' => [235000, 0],
            'first band' => [300000, 6500],
            'second band' => [400000, 23000],
            'thirty percent band' => [1000000, 202000],
            'additional high income tax' => [11000000, 3302000],
        ];
    }
}
