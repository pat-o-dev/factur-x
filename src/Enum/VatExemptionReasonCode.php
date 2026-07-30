<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-121 / BT-120: VAT exemption reason code (VATEX code list), as referenced
 * by XP Z12-012 §4.4.7 for the VAT categories that require one (E, AE, K, G).
 *
 * Curated subset covering the codes named explicitly in the French reform
 * documentation. Extend this enum if a code you need is missing.
 */
enum VatExemptionReasonCode: string
{
    case ReverseCharge = 'VATEX-EU-AE';
    case ReverseChargeDomesticFrance = 'VATEX-FR-AE';
    case IntraCommunitySupply = 'VATEX-EU-IC';
    case ExportOutsideEu = 'VATEX-EU-G';
    case OutOfScope = 'VATEX-EU-O';
    case FrenchVatFranchise = 'VATEX-FR-FRANCHISE';
}
