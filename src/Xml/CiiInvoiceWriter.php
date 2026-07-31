<?php

declare(strict_types=1);

namespace PatODev\FacturX\Xml;

use DOMDocument;
use DOMElement;
use PatODev\FacturX\Calculation\InvoiceCalculator;
use PatODev\FacturX\Calculation\InvoiceTotals;
use PatODev\FacturX\Calculation\VatBreakdownEntry;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\Identifier;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Model\Party;
use PatODev\FacturX\Support\FrenchTerritoryCountryCode;
use PatODev\FacturX\Support\Money;

/**
 * Builds the UN/CEFACT Cross Industry Invoice (D22B) XML for the EN 16931
 * profile, i.e. the "factur-x.xml" attachment (XP Z12-012 §4.8.7).
 *
 * Field coverage (v1): header identification, seller/buyer/delivery/payee
 * party, references (BT-10/12/13/25/26), notes, lines with line-level
 * allowances/charges, VAT breakdown, document-level allowances/charges,
 * monetary summation and a single SEPA credit transfer payment mean.
 * Not yet covered: multi-party extensions, attachments (BG-24), multiple
 * payment means, sub-lines. Tracked for a future release.
 */
final class CiiInvoiceWriter
{
    private const RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

    private const UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private DOMDocument $doc;

    private InvoiceCalculator $calculator;

    public function __construct(?InvoiceCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new InvoiceCalculator();
    }

    public function toXmlString(Invoice $invoice, float $prepaidAmount = 0.0, float $roundingAmount = 0.0): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = false;

        $root = $this->el($this->doc, self::RSM, 'rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', self::RSM);
        $root->setAttribute('xmlns:ram', self::RAM);
        $root->setAttribute('xmlns:qdt', self::QDT);
        $root->setAttribute('xmlns:udt', self::UDT);
        $this->doc->appendChild($root);

        $root->appendChild($this->buildDocumentContext($invoice));
        $root->appendChild($this->buildExchangedDocument($invoice));
        $root->appendChild($this->buildSupplyChainTradeTransaction($invoice, $prepaidAmount, $roundingAmount));

        return (string) $this->doc->saveXML();
    }

    private function buildDocumentContext(Invoice $invoice): DOMElement
    {
        $context = $this->el($this->doc, self::RSM, 'rsm:ExchangedDocumentContext');

        $guideline = $this->el($this->doc, self::RAM, 'ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($this->el($this->doc, self::RAM, 'ram:ID', $invoice->profile->value));
        $context->appendChild($guideline);

        return $context;
    }

    private function buildExchangedDocument(Invoice $invoice): DOMElement
    {
        $document = $this->el($this->doc, self::RSM, 'rsm:ExchangedDocument');

        $document->appendChild($this->el($this->doc, self::RAM, 'ram:ID', $invoice->number));
        $document->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', (string) $invoice->typeCode->value));

        $issue = $this->el($this->doc, self::RAM, 'ram:IssueDateTime');
        $issue->appendChild($this->dateTimeString($invoice->issueDate));
        $document->appendChild($issue);

        foreach ($invoice->notes() as $note) {
            $noteEl = $this->el($this->doc, self::RAM, 'ram:IncludedNote');
            if ($note->subjectCode !== null) {
                $noteEl->appendChild($this->el($this->doc, self::RAM, 'ram:SubjectCode', $note->subjectCode->value));
            }
            $noteEl->appendChild($this->el($this->doc, self::RAM, 'ram:Content', $note->content));
            $document->appendChild($noteEl);
        }

        return $document;
    }

    private function buildSupplyChainTradeTransaction(Invoice $invoice, float $prepaidAmount, float $roundingAmount): DOMElement
    {
        $transaction = $this->el($this->doc, self::RSM, 'rsm:SupplyChainTradeTransaction');

        foreach ($invoice->lines() as $line) {
            $transaction->appendChild($this->buildLineItem($line));
        }

        $transaction->appendChild($this->buildHeaderTradeAgreement($invoice));
        $transaction->appendChild($this->buildHeaderTradeDelivery($invoice));
        $transaction->appendChild($this->buildHeaderTradeSettlement($invoice, $prepaidAmount, $roundingAmount));

        return $transaction;
    }

    private function buildLineItem(InvoiceLine $line): DOMElement
    {
        $item = $this->el($this->doc, self::RAM, 'ram:IncludedSupplyChainTradeLineItem');

        $lineDoc = $this->el($this->doc, self::RAM, 'ram:AssociatedDocumentLineDocument');
        $lineDoc->appendChild($this->el($this->doc, self::RAM, 'ram:LineID', $line->lineId));
        $item->appendChild($lineDoc);

        $product = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradeProduct');
        $product->appendChild($this->el($this->doc, self::RAM, 'ram:Name', $line->itemName));
        if ($line->itemDescription !== null) {
            $product->appendChild($this->el($this->doc, self::RAM, 'ram:Description', $line->itemDescription));
        }
        $item->appendChild($product);

        $agreement = $this->el($this->doc, self::RAM, 'ram:SpecifiedLineTradeAgreement');
        if ($line->grossUnitPrice !== null) {
            $gross = $this->el($this->doc, self::RAM, 'ram:GrossPriceProductTradePrice');
            $gross->appendChild($this->amountEl('ram:ChargeAmount', $line->grossUnitPrice, decimals: 4));
            $agreement->appendChild($gross);
        }
        $net = $this->el($this->doc, self::RAM, 'ram:NetPriceProductTradePrice');
        $net->appendChild($this->amountEl('ram:ChargeAmount', $line->netUnitPrice, decimals: 4));
        $agreement->appendChild($net);
        $item->appendChild($agreement);

        $delivery = $this->el($this->doc, self::RAM, 'ram:SpecifiedLineTradeDelivery');
        $qty = $this->el($this->doc, self::RAM, 'ram:BilledQuantity', Money::format($line->quantity, 4));
        $qty->setAttribute('unitCode', $line->unitCode->value);
        $delivery->appendChild($qty);
        $item->appendChild($delivery);

        $settlement = $this->el($this->doc, self::RAM, 'ram:SpecifiedLineTradeSettlement');

        $tax = $this->el($this->doc, self::RAM, 'ram:ApplicableTradeTax');
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', 'VAT'));
        if ($line->vatExemptionReasonText !== null) {
            $tax->appendChild($this->el($this->doc, self::RAM, 'ram:ExemptionReason', $line->vatExemptionReasonText));
        }
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:CategoryCode', $line->vatCategory->value));
        if ($line->vatExemptionReasonCode !== null) {
            $tax->appendChild($this->el($this->doc, self::RAM, 'ram:ExemptionReasonCode', $line->vatExemptionReasonCode->value));
        }
        if ($line->vatRate !== null) {
            $tax->appendChild($this->amountEl('ram:RateApplicablePercent', $line->vatRate, decimals: 2));
        }
        $settlement->appendChild($tax);

        foreach ($line->allowances as $allowance) {
            $settlement->appendChild($this->buildAllowanceCharge($allowance));
        }
        foreach ($line->charges as $charge) {
            $settlement->appendChild($this->buildAllowanceCharge($charge));
        }

        $summation = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
        $summation->appendChild($this->amountEl('ram:LineTotalAmount', $line->netAmount()));
        $settlement->appendChild($summation);

        if ($line->buyerOrderLineReference !== null) {
            $poRef = $this->el($this->doc, self::RAM, 'ram:AdditionalReferencedDocument');
            $poRef->appendChild($this->el($this->doc, self::RAM, 'ram:LineID', $line->buyerOrderLineReference));
            $poRef->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', '130'));
            $settlement->appendChild($poRef);
        }

        $item->appendChild($settlement);

        return $item;
    }

    private function buildAllowanceCharge(AllowanceCharge $ac): DOMElement
    {
        $el = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradeAllowanceCharge');

        $indicator = $this->el($this->doc, self::RAM, 'ram:ChargeIndicator');
        $indicator->appendChild($this->el($this->doc, self::UDT, 'udt:Indicator', $ac->isCharge ? 'true' : 'false'));
        $el->appendChild($indicator);

        if ($ac->percentage !== null) {
            $el->appendChild($this->amountEl('ram:CalculationPercent', $ac->percentage, decimals: 2));
        }
        if ($ac->baseAmount !== null) {
            $el->appendChild($this->amountEl('ram:BasisAmount', $ac->baseAmount));
        }
        $el->appendChild($this->amountEl('ram:ActualAmount', $ac->amount));

        if ($ac->reasonCode !== null) {
            $el->appendChild($this->el($this->doc, self::RAM, 'ram:ReasonCode', $ac->reasonCode));
        }
        if ($ac->reasonText !== null) {
            $el->appendChild($this->el($this->doc, self::RAM, 'ram:Reason', $ac->reasonText));
        }

        $tax = $this->el($this->doc, self::RAM, 'ram:CategoryTradeTax');
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', 'VAT'));
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:CategoryCode', $ac->vatCategory->value));
        if ($ac->vatRate !== null) {
            $tax->appendChild($this->amountEl('ram:RateApplicablePercent', $ac->vatRate, decimals: 2));
        }
        $el->appendChild($tax);

        return $el;
    }

    private function buildHeaderTradeAgreement(Invoice $invoice): DOMElement
    {
        $agreement = $this->el($this->doc, self::RAM, 'ram:ApplicableHeaderTradeAgreement');

        if ($invoice->buyerReference !== null) {
            $agreement->appendChild($this->el($this->doc, self::RAM, 'ram:BuyerReference', $invoice->buyerReference));
        }

        $agreement->appendChild($this->buildTradeParty('ram:SellerTradeParty', $invoice->seller));
        $agreement->appendChild($this->buildTradeParty('ram:BuyerTradeParty', $invoice->buyer));

        if ($invoice->purchaseOrderReference !== null) {
            $po = $this->el($this->doc, self::RAM, 'ram:BuyerOrderReferencedDocument');
            $po->appendChild($this->el($this->doc, self::RAM, 'ram:IssuerAssignedID', $invoice->purchaseOrderReference));
            $agreement->appendChild($po);
        }

        if ($invoice->contractReference !== null) {
            $contract = $this->el($this->doc, self::RAM, 'ram:ContractReferencedDocument');
            $contract->appendChild($this->el($this->doc, self::RAM, 'ram:IssuerAssignedID', $invoice->contractReference));
            $agreement->appendChild($contract);
        }

        return $agreement;
    }

    private function buildHeaderTradeDelivery(Invoice $invoice): DOMElement
    {
        $delivery = $this->el($this->doc, self::RAM, 'ram:ApplicableHeaderTradeDelivery');

        if ($invoice->deliveryParty !== null) {
            $delivery->appendChild($this->buildTradeParty('ram:ShipToTradeParty', $invoice->deliveryParty));
        }

        if ($invoice->deliveryDate !== null) {
            $event = $this->el($this->doc, self::RAM, 'ram:ActualDeliverySupplyChainEvent');
            $occurrence = $this->el($this->doc, self::RAM, 'ram:OccurrenceDateTime');
            $occurrence->appendChild($this->dateTimeString($invoice->deliveryDate));
            $event->appendChild($occurrence);
            $delivery->appendChild($event);
        }

        return $delivery;
    }

    private function buildHeaderTradeSettlement(Invoice $invoice, float $prepaidAmount, float $roundingAmount): DOMElement
    {
        $settlement = $this->el($this->doc, self::RAM, 'ram:ApplicableHeaderTradeSettlement');

        $settlement->appendChild($this->el($this->doc, self::RAM, 'ram:InvoiceCurrencyCode', $invoice->currencyCode->value));

        if ($invoice->payeeParty !== null) {
            $settlement->appendChild($this->buildTradeParty('ram:PayeeTradeParty', $invoice->payeeParty));
        }

        if ($invoice->paymentMeans !== null) {
            $means = $invoice->paymentMeans;
            $meansEl = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradeSettlementPaymentMeans');
            $meansEl->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', $means->typeCode->value));
            if ($means->iban !== null) {
                $account = $this->el($this->doc, self::RAM, 'ram:PayeePartyCreditorFinancialAccount');
                $account->appendChild($this->el($this->doc, self::RAM, 'ram:IBANID', $means->iban));
                $meansEl->appendChild($account);
            }
            if ($means->bic !== null) {
                $institution = $this->el($this->doc, self::RAM, 'ram:PayeeSpecifiedCreditorFinancialInstitution');
                $institution->appendChild($this->el($this->doc, self::RAM, 'ram:BICID', $means->bic));
                $meansEl->appendChild($institution);
            }
            $settlement->appendChild($meansEl);

            if ($means->paymentReference !== null) {
                $settlement->appendChild($this->el($this->doc, self::RAM, 'ram:PaymentReference', $means->paymentReference));
            }
        }

        foreach ($this->calculator->vatBreakdown($invoice) as $entry) {
            $settlement->appendChild($this->buildVatBreakdownEntry($entry));
        }

        foreach ($invoice->documentAllowances() as $allowance) {
            $settlement->appendChild($this->buildAllowanceCharge($allowance));
        }
        foreach ($invoice->documentCharges() as $charge) {
            $settlement->appendChild($this->buildAllowanceCharge($charge));
        }

        if ($invoice->paymentTermsText !== null || $invoice->dueDate !== null) {
            $terms = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradePaymentTerms');
            if ($invoice->paymentTermsText !== null) {
                $terms->appendChild($this->el($this->doc, self::RAM, 'ram:Description', $invoice->paymentTermsText));
            }
            if ($invoice->dueDate !== null) {
                $due = $this->el($this->doc, self::RAM, 'ram:DueDateDateTime');
                $due->appendChild($this->dateTimeString($invoice->dueDate));
                $terms->appendChild($due);
            }
            $settlement->appendChild($terms);
        }

        $totals = $this->calculator->totals($invoice, $prepaidAmount, $roundingAmount);
        $settlement->appendChild($this->buildMonetarySummation($totals, $invoice->currencyCode->value));

        if ($invoice->precedingInvoiceNumber !== null) {
            $preceding = $this->el($this->doc, self::RAM, 'ram:InvoiceReferencedDocument');
            $preceding->appendChild($this->el($this->doc, self::RAM, 'ram:IssuerAssignedID', $invoice->precedingInvoiceNumber));
            if ($invoice->precedingInvoiceDate !== null) {
                $formatted = $this->el($this->doc, self::RAM, 'ram:FormattedIssueDateTime');
                $formatted->appendChild($this->dateTimeString($invoice->precedingInvoiceDate));
                $preceding->appendChild($formatted);
            }
            $settlement->appendChild($preceding);
        }

        return $settlement;
    }

    private function buildVatBreakdownEntry(VatBreakdownEntry $entry): DOMElement
    {
        $tax = $this->el($this->doc, self::RAM, 'ram:ApplicableTradeTax');
        $tax->appendChild($this->amountEl('ram:CalculatedAmount', $entry->taxAmount));
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:TypeCode', 'VAT'));
        if ($entry->exemptionReasonText !== null) {
            $tax->appendChild($this->el($this->doc, self::RAM, 'ram:ExemptionReason', $entry->exemptionReasonText));
        }
        $tax->appendChild($this->amountEl('ram:BasisAmount', $entry->taxableAmount));
        $tax->appendChild($this->el($this->doc, self::RAM, 'ram:CategoryCode', $entry->category->value));
        if ($entry->exemptionReasonCode !== null) {
            $tax->appendChild($this->el($this->doc, self::RAM, 'ram:ExemptionReasonCode', $entry->exemptionReasonCode->value));
        }
        if ($entry->rate !== null) {
            $tax->appendChild($this->amountEl('ram:RateApplicablePercent', $entry->rate, decimals: 2));
        }

        return $tax;
    }

    private function buildMonetarySummation(InvoiceTotals $totals, string $currencyCode): DOMElement
    {
        $summation = $this->el($this->doc, self::RAM, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($this->amountEl('ram:LineTotalAmount', $totals->lineTotalAmount));
        $summation->appendChild($this->amountEl('ram:ChargeTotalAmount', $totals->chargeTotalAmount));
        $summation->appendChild($this->amountEl('ram:AllowanceTotalAmount', $totals->allowanceTotalAmount));
        $summation->appendChild($this->amountEl('ram:TaxBasisTotalAmount', $totals->taxExclusiveAmount));
        // BT-110: the only header monetary amount EN 16931 requires a currencyID on
        // (BR-CO-15 validators, e.g. KoSIT, fail to match it to the invoice currency without it).
        $taxTotal = $this->amountEl('ram:TaxTotalAmount', $totals->taxAmount);
        $taxTotal->setAttribute('currencyID', $currencyCode);
        $summation->appendChild($taxTotal);
        $summation->appendChild($this->amountEl('ram:RoundingAmount', $totals->roundingAmount));
        $summation->appendChild($this->amountEl('ram:GrandTotalAmount', $totals->taxInclusiveAmount));
        $summation->appendChild($this->amountEl('ram:TotalPrepaidAmount', $totals->prepaidAmount));
        $summation->appendChild($this->amountEl('ram:DuePayableAmount', $totals->duePayableAmount));

        return $summation;
    }

    private function buildTradeParty(string $qualifiedName, Party $party): DOMElement
    {
        $el = $this->el($this->doc, self::RAM, $qualifiedName);

        $el->appendChild($this->el($this->doc, self::RAM, 'ram:Name', $party->name));

        if ($party->legalRegistrationId !== null) {
            $org = $this->el($this->doc, self::RAM, 'ram:SpecifiedLegalOrganization');
            $id = $this->el($this->doc, self::RAM, 'ram:ID', $party->legalRegistrationId);
            $id->setAttribute('schemeID', '0002');
            $org->appendChild($id);
            $el->appendChild($org);
        }

        foreach ($party->privateIdentifiers as $identifier) {
            /** @var Identifier $identifier */
            $idEl = $this->el($this->doc, self::RAM, 'ram:GlobalID', $identifier->value);
            if ($identifier->schemeId !== null) {
                $idEl->setAttribute('schemeID', $identifier->schemeId->value);
            }
            $el->appendChild($idEl);
        }

        $address = $this->el($this->doc, self::RAM, 'ram:PostalTradeAddress');
        $address->appendChild($this->el($this->doc, self::RAM, 'ram:PostcodeCode', $party->address->postalCode));
        $address->appendChild($this->el($this->doc, self::RAM, 'ram:LineOne', $party->address->line1));
        if ($party->address->line2 !== null) {
            $address->appendChild($this->el($this->doc, self::RAM, 'ram:LineTwo', $party->address->line2));
        }
        if ($party->address->line3 !== null) {
            $address->appendChild($this->el($this->doc, self::RAM, 'ram:LineThree', $party->address->line3));
        }
        $address->appendChild($this->el($this->doc, self::RAM, 'ram:CityName', $party->address->city));
        $address->appendChild($this->el($this->doc, self::RAM, 'ram:CountryID', FrenchTerritoryCountryCode::toReportedCode($party->address->countryCode)));
        $el->appendChild($address);

        if ($party->electronicAddress !== null) {
            $uri = $this->el($this->doc, self::RAM, 'ram:URIUniversalCommunication');
            $uriId = $this->el($this->doc, self::RAM, 'ram:URIID', $party->electronicAddress->value);
            $uriId->setAttribute('schemeID', $party->electronicAddress->schemeId->value);
            $uri->appendChild($uriId);
            $el->appendChild($uri);
        }

        if ($party->vatNumber !== null) {
            $vat = $this->el($this->doc, self::RAM, 'ram:SpecifiedTaxRegistration');
            $vatId = $this->el($this->doc, self::RAM, 'ram:ID', $party->vatNumber);
            $vatId->setAttribute('schemeID', 'VA');
            $vat->appendChild($vatId);
            $el->appendChild($vat);
        }

        return $el;
    }

    private function dateTimeString(\DateTimeImmutable $date): DOMElement
    {
        $el = $this->el($this->doc, self::UDT, 'udt:DateTimeString', $date->format('Ymd'));
        $el->setAttribute('format', '102');

        return $el;
    }

    private function amountEl(string $qualifiedName, float $value, int $decimals = 2): DOMElement
    {
        return $this->el($this->doc, self::RAM, $qualifiedName, Money::format($value, $decimals));
    }

    private function el(DOMDocument $doc, string $namespace, string $qualifiedName, ?string $textContent = null): DOMElement
    {
        $element = $doc->createElementNS($namespace, $qualifiedName);
        if ($textContent !== null) {
            $element->appendChild($doc->createTextNode($textContent));
        }

        return $element;
    }
}
