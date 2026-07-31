<?php

declare(strict_types=1);

namespace PatODev\FacturX\Support;

/**
 * BR-FR-MAP-12: the VAT rate (BT-96 allowance, BT-103 charge, BT-119 VAT
 * breakdown, BT-152 line) must map to one of these percentages (expressed
 * as a percentage, not a coefficient — e.g. 20, not 0.20).
 */
final class FrenchVatRates
{
    public const ALLOWED = [
        0.0, 10.0, 13.0, 20.0, 8.5, 19.6, 2.1, 5.5, 7.0, 20.6, 1.05, 0.9, 1.75, 9.2, 9.6,
    ];

    public static function isAllowed(float $rate): bool
    {
        foreach (self::ALLOWED as $allowed) {
            if (abs($rate - $allowed) < 0.0001) {
                return true;
            }
        }

        return false;
    }
}
