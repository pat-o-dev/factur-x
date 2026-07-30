<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-118 / BT-151: UNTDID 5305 subset retained by EN 16931 / the French reform (XP Z12-012 §4.4.7).
 */
enum VatCategory: string
{
    case Standard = 'S';
    case ZeroRated = 'Z';
    case Exempt = 'E';
    case ReverseCharge = 'AE';
    case IntraCommunitySupply = 'K';
    case ExportOutsideEu = 'G';
    case OutOfScope = 'O';
    case Canaries = 'L';
    case CeutaMelilla = 'M';

    /** Whether a rate/amount other than zero is expected for this category. */
    public function isRatedTax(): bool
    {
        return $this === self::Standard;
    }

    /** Categories that require an exemption reason (text and/or VATEX code) per BR-E-10/BR-AE-10/etc. */
    public function requiresExemptionReason(): bool
    {
        return $this !== self::Standard;
    }
}
