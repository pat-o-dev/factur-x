<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-21: note subject code (UNTDID 4451 subset), as listed in XP Z12-012
 * §4.4.3. The French reform requires at least PMT, PMD and AAB notes on
 * every invoice (rule BR-FR-05) — see Support\MandatoryFrenchNotes.
 */
enum NoteSubjectCode: string
{
    case DiscountTerms = 'AAB';
    case GeneralInformation = 'AAI';
    case LegalInformation = 'ABL';
    case FactoringSubrogationClause = 'ACC';
    case B2gIndicator = 'ADN';
    case ProcessingTypeIndicator = 'BAR';
    case EcoContribution = 'BLU';
    case CustomsInformation = 'CUS';
    case InvoicingMandateDeclaration = 'DCL';
    case LateFeeIndemnity = 'PMT';
    case LatePaymentPenalty = 'PMD';
    case SupplierRemarks = 'SUR';
    case SingleVatEntityMember = 'TXD';
}
