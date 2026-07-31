<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Support;

use PatODev\FacturX\Support\FrenchVatRates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchVatRatesTest extends TestCase
{
    #[DataProvider('allowedRateProvider')]
    public function test_allowed_rates_are_accepted(float $rate): void
    {
        self::assertTrue(FrenchVatRates::isAllowed($rate));
    }

    public function test_a_disallowed_rate_is_rejected(): void
    {
        self::assertFalse(FrenchVatRates::isAllowed(15.0));
        self::assertFalse(FrenchVatRates::isAllowed(12.0));
    }

    /** @return array<string, array{float}> */
    public static function allowedRateProvider(): array
    {
        return array_map(static fn (float $rate): array => [$rate], FrenchVatRates::ALLOWED);
    }
}
