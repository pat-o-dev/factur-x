<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Support\Money;

use PatODev\FacturX\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * XP Z12-012 §4.4.6: halves round away from zero for both signs.
     */
    public function test_rounds_positive_half_away_from_zero(): void
    {
        self::assertSame(13.46, Money::round2(13.455));
    }

    public function test_rounds_negative_half_away_from_zero(): void
    {
        self::assertSame(-13.46, Money::round2(-13.455));
    }

    public function test_format_pads_decimals(): void
    {
        self::assertSame('10.00', Money::format(10.0));
        self::assertSame('10.50', Money::format(10.5));
    }
}
