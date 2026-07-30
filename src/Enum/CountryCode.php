<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-40 / BT-55 / BT-80 / ...: ISO 3166-1 alpha-2 country code.
 *
 * Covers all 27 EU member states (relevant for intracommunity VAT rules)
 * plus a few common non-EU trading partners. Extend this enum if a country
 * you need is missing.
 */
enum CountryCode: string
{
    case Austria = 'AT';
    case Belgium = 'BE';
    case Bulgaria = 'BG';
    case Croatia = 'HR';
    case Cyprus = 'CY';
    case Czechia = 'CZ';
    case Denmark = 'DK';
    case Estonia = 'EE';
    case Finland = 'FI';
    case France = 'FR';
    case Germany = 'DE';
    case Greece = 'GR';
    case Hungary = 'HU';
    case Ireland = 'IE';
    case Italy = 'IT';
    case Latvia = 'LV';
    case Lithuania = 'LT';
    case Luxembourg = 'LU';
    case Malta = 'MT';
    case Netherlands = 'NL';
    case Poland = 'PL';
    case Portugal = 'PT';
    case Romania = 'RO';
    case Slovakia = 'SK';
    case Slovenia = 'SI';
    case Spain = 'ES';
    case Sweden = 'SE';

    case Norway = 'NO';
    case Switzerland = 'CH';
    case UnitedKingdom = 'GB';
    case UnitedStates = 'US';

    /** Short English label, suitable for a UI select. */
    public function label(): string
    {
        return match ($this) {
            self::Austria => 'Austria',
            self::Belgium => 'Belgium',
            self::Bulgaria => 'Bulgaria',
            self::Croatia => 'Croatia',
            self::Cyprus => 'Cyprus',
            self::Czechia => 'Czechia',
            self::Denmark => 'Denmark',
            self::Estonia => 'Estonia',
            self::Finland => 'Finland',
            self::France => 'France',
            self::Germany => 'Germany',
            self::Greece => 'Greece',
            self::Hungary => 'Hungary',
            self::Ireland => 'Ireland',
            self::Italy => 'Italy',
            self::Latvia => 'Latvia',
            self::Lithuania => 'Lithuania',
            self::Luxembourg => 'Luxembourg',
            self::Malta => 'Malta',
            self::Netherlands => 'Netherlands',
            self::Poland => 'Poland',
            self::Portugal => 'Portugal',
            self::Romania => 'Romania',
            self::Slovakia => 'Slovakia',
            self::Slovenia => 'Slovenia',
            self::Spain => 'Spain',
            self::Sweden => 'Sweden',
            self::Norway => 'Norway',
            self::Switzerland => 'Switzerland',
            self::UnitedKingdom => 'United Kingdom',
            self::UnitedStates => 'United States',
        };
    }
}
