<?php

declare(strict_types=1);

namespace PatODev\FacturX\Support;

/**
 * BR-FR-MAP-14: the ISO 3166-1 country codes of French overseas
 * departments/territories (DOM/COM) must be reported as "FR" in the
 * CII fields that carry a party's country code (BT-40 seller, BT-55
 * buyer, BT-80 deliver-to, and the Chorus Pro payee extension
 * EXTFR-FE-157) — the postal address itself still shows the real city/
 * postal code, only the country code is normalized.
 */
final class FrenchTerritoryCountryCode
{
    private const DOM_COM_CODES = [
        'GF', // Guyane française
        'TF', // Terres australes françaises
        'GP', // Guadeloupe
        'GY', // Guyana (listed alongside the DOM/COM in the XP Z12-012 mapping table)
        'MQ', // Martinique
        'YT', // Mayotte
        'RE', // Réunion
        'BL', // Saint-Barthélemy
        'MF', // Saint-Martin (partie française)
        'PM', // Saint-Pierre-et-Miquelon
    ];

    public static function toReportedCode(string $countryCode): string
    {
        return in_array($countryCode, self::DOM_COM_CODES, true) ? 'FR' : $countryCode;
    }

    /** @return list<string> */
    public static function domComCodes(): array
    {
        return self::DOM_COM_CODES;
    }
}
