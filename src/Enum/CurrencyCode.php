<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-5 / BT-6: ISO 4217 currency code.
 *
 * Curated subset covering EUR (the default and near-universal case for the
 * French reform) plus a few common trading currencies. Extend this enum if
 * a currency you need is missing.
 */
enum CurrencyCode: string
{
    case Euro = 'EUR';
    case UsDollar = 'USD';
    case BritishPound = 'GBP';
    case SwissFranc = 'CHF';
}
