<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-81: payment means type code (UNTDID 4461 subset).
 *
 * Curated subset covering the payment means seen in practice for French
 * B2B invoicing. Extend this enum if a code you need is missing.
 */
enum PaymentMeansCode: string
{
    case Cash = '10';
    case Cheque = '20';
    case CreditTransfer = '30';
    case PaymentToBankAccount = '42';
    case BankCard = '48';
    case DirectDebit = '49';
    case SepaCreditTransfer = '58';
    case SepaDirectDebit = '59';
}
