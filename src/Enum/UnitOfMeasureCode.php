<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-130 / BT-150: unit of measure, from the UN/ECE Recommendation No. 20 code list
 * referenced by EN 16931 / the French reform (XP Z12-012).
 *
 * The full list has ~800 entries; this is a curated subset covering the units
 * actually seen in invoicing practice (Chorus Pro and Peppol BIS Billing 3.0
 * examples). Extend this enum if a case you need is missing.
 */
enum UnitOfMeasureCode: string
{
    case Piece = 'C62';
    case Kilogram = 'KGM';
    case Gram = 'GRM';
    case Tonne = 'TNE';
    case Metre = 'MTR';
    case Centimetre = 'CMT';
    case Millimetre = 'MMT';
    case SquareMetre = 'MTK';
    case CubicMetre = 'MTQ';
    case Litre = 'LTR';
    case Millilitre = 'MLT';
    case Hour = 'HUR';
    case Day = 'DAY';
    case Week = 'WEE';
    case Month = 'MON';
    case Year = 'ANN';
    case KilowattHour = 'KWH';
    case Pair = 'PR';
    case Set = 'SET';

    /** Short English label, suitable for a UI select. */
    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Piece',
            self::Kilogram => 'Kilogram',
            self::Gram => 'Gram',
            self::Tonne => 'Tonne',
            self::Metre => 'Metre',
            self::Centimetre => 'Centimetre',
            self::Millimetre => 'Millimetre',
            self::SquareMetre => 'Square metre',
            self::CubicMetre => 'Cubic metre',
            self::Litre => 'Litre',
            self::Millilitre => 'Millilitre',
            self::Hour => 'Hour',
            self::Day => 'Day',
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
            self::KilowattHour => 'Kilowatt hour',
            self::Pair => 'Pair',
            self::Set => 'Set',
        };
    }
}
