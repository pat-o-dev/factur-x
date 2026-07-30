<?php

declare(strict_types=1);

namespace PatODev\FacturX\Support;

/**
 * Rounding per XP Z12-012 §4.4.6: round to the nearest value, halves rounded
 * away from zero (13.455 -> 13.46, -13.455 -> -13.46). PHP's round() with
 * PHP_ROUND_HALF_UP already implements "half away from zero" for both signs;
 * we go through bcmath (when available) to dodge binary float artefacts such
 * as 13.455 being stored as 13.454999999999998.
 */
final class Money
{
    public static function round2(float $value): float
    {
        return self::roundTo($value, 2);
    }

    public static function roundTo(float $value, int $decimals): float
    {
        if (extension_loaded('bcmath')) {
            $shifted = bcmul((string) $value, bcpow('10', (string) $decimals, 0), $decimals + 4);
            $rounded = round((float) $shifted);
            $result = bcdiv((string) $rounded, bcpow('10', (string) $decimals, 0), $decimals);

            return (float) $result;
        }

        return round($value, $decimals, PHP_ROUND_HALF_UP);
    }

    /** Formats an amount the way CII/UBL expect it: fixed decimals, dot separator, no thousands separator. */
    public static function format(float $value, int $decimals = 2): string
    {
        return number_format(self::roundTo($value, $decimals), $decimals, '.', '');
    }
}
