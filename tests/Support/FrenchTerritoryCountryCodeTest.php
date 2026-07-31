<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Support;

use PatODev\FacturX\Support\FrenchTerritoryCountryCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchTerritoryCountryCodeTest extends TestCase
{
    #[DataProvider('domComCodeProvider')]
    public function test_dom_com_codes_are_reported_as_fr(string $code): void
    {
        self::assertSame('FR', FrenchTerritoryCountryCode::toReportedCode($code));
    }

    public function test_other_codes_are_left_untouched(): void
    {
        self::assertSame('DE', FrenchTerritoryCountryCode::toReportedCode('DE'));
        self::assertSame('FR', FrenchTerritoryCountryCode::toReportedCode('FR'));
    }

    /** @return array<string, array{string}> */
    public static function domComCodeProvider(): array
    {
        return [
            'GF' => ['GF'], 'TF' => ['TF'], 'GP' => ['GP'], 'GY' => ['GY'],
            'MQ' => ['MQ'], 'YT' => ['YT'], 'RE' => ['RE'], 'BL' => ['BL'],
            'MF' => ['MF'], 'PM' => ['PM'],
        ];
    }
}
