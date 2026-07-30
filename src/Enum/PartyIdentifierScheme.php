<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * Scheme identifier (ICD code list) qualifying a party's private identifier
 * (BT-29 / BT-46), as referenced by XP Z12-012 §4.3.1.
 *
 * Curated subset covering the schemes named explicitly in the French reform
 * documentation. Extend this enum if a scheme you need is missing.
 */
enum PartyIdentifierScheme: string
{
    case Siret = '0009';
    case Gln = '0088';
    case CodeRoutage = '0224';
    case SingleVatEntitySiren = '0231';
}
