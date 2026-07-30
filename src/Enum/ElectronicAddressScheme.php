<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-34 / BT-49: scheme qualifying an e-invoicing routing address.
 * "0225" for the French PPF annuaire (SIREN / SIREN_suffix), "EM" for a
 * plain e-mail address, per XP Z12-012 §4.5.1 (rules BR-FR-12 / BR-FR-13).
 */
enum ElectronicAddressScheme: string
{
    case Email = 'EM';
    case FrancePpf = '0225';
}
