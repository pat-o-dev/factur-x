<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-3: UNTDID 1001 subset allowed by the French reform (XP Z12-012 rule BR-FR-04).
 */
enum InvoiceTypeCode: int
{
    case CommercialInvoice = 380;
    case CreditNote = 381;
    case CorrectedInvoice = 384;
    case PrepaymentInvoice = 386;
    case SelfBilledInvoice = 389;
    case FactoredInvoice = 393;
    case FactoredCreditNote = 396;
    case SelfBilledCreditNote = 261;
    case GlobalAllowanceCreditNote = 262;

    public function isCreditNote(): bool
    {
        return in_array($this, [
            self::CreditNote,
            self::FactoredCreditNote,
            self::SelfBilledCreditNote,
            self::GlobalAllowanceCreditNote,
        ], true);
    }
}
