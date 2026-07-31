<?php

declare(strict_types=1);

namespace PatODev\FacturX\Validation;

use DOMDocument;
use DOMXPath;
use PatODev\FacturX\Support\FrenchTerritoryCountryCode;
use PatODev\FacturX\Support\FrenchVatRates;

/**
 * Checks a Factur-X CII XML string against a curated, hand-picked set of
 * EN 16931 business rules — not the full official schematron ruleset (see
 * the core README roadmap for that future, separate item). Each rule is one
 * entry in rules(), making new rules a one-entry addition rather than an
 * engine change.
 */
final class InvoiceValidator
{
    private const RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    public function validate(string $xmlContent): ValidationReport
    {
        $doc = new DOMDocument();
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if (! $loaded) {
            return new ValidationReport([
                new RuleResult('well-formed-xml', 'The document is well-formed XML.', false, 'Could not parse the XML content.'),
            ]);
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('rsm', self::RSM);
        $xpath->registerNamespace('ram', self::RAM);

        if ($xpath->query('/rsm:CrossIndustryInvoice')?->count() === 0) {
            return new ValidationReport([
                new RuleResult('well-formed-xml', 'The document is well-formed XML.', true),
                new RuleResult(
                    'root-element',
                    'The root element is rsm:CrossIndustryInvoice in the correct namespace.',
                    false,
                    'Root element rsm:CrossIndustryInvoice (namespace '.self::RSM.') was not found.',
                ),
            ]);
        }

        $results = [
            new RuleResult('well-formed-xml', 'The document is well-formed XML.', true),
            new RuleResult('root-element', 'The root element is rsm:CrossIndustryInvoice in the correct namespace.', true),
        ];

        foreach ($this->rules() as $rule) {
            $failureDetail = ($rule['check'])($xpath);
            $results[] = new RuleResult($rule['code'], $rule['description'], $failureDetail === null, $failureDetail);
        }

        return new ValidationReport($results);
    }

    /** @return list<array{code: string, description: string, check: callable(DOMXPath): ?string}> */
    private function rules(): array
    {
        return [
            [
                'code' => 'has-line-item',
                'description' => 'The invoice has at least one line item.',
                'check' => function (DOMXPath $xpath): ?string {
                    if ($xpath->query('//ram:IncludedSupplyChainTradeLineItem')?->count() === 0) {
                        return 'No ram:IncludedSupplyChainTradeLineItem found.';
                    }

                    return null;
                },
            ],
            [
                'code' => 'has-seller-party',
                'description' => 'The invoice declares a seller (BG-4).',
                'check' => fn (DOMXPath $xpath): ?string => $xpath->query('//ram:SellerTradeParty')?->count() === 0
                    ? 'No ram:SellerTradeParty found.'
                    : null,
            ],
            [
                'code' => 'has-buyer-party',
                'description' => 'The invoice declares a buyer (BG-7).',
                'check' => fn (DOMXPath $xpath): ?string => $xpath->query('//ram:BuyerTradeParty')?->count() === 0
                    ? 'No ram:BuyerTradeParty found.'
                    : null,
            ],
            [
                'code' => 'BR-CO-15',
                'description' => 'Tax basis total (BT-109) + tax total (BT-110) = grand total (BT-112).',
                'check' => $this->checkGrandTotalArithmetic(...),
            ],
            [
                'code' => 'BR-CO-15-currencyID',
                'description' => 'The tax total amount (BT-110) carries a currencyID attribute.',
                'check' => $this->checkTaxTotalCurrencyId(...),
            ],
            [
                'code' => 'BR-CO-25',
                'description' => 'If the amount due (BT-115) is positive, a due date (BT-9) or payment terms (BT-20) must be present.',
                'check' => $this->checkDueDateOrPaymentTerms(...),
            ],
            [
                'code' => 'BR-CO-5-6',
                'description' => 'Every allowance/charge has a reason code or reason text.',
                'check' => $this->checkAllowanceChargeReasons(...),
            ],
            [
                'code' => 'BR-FR-MAP-12',
                'description' => 'Every VAT rate (BT-96, BT-103, BT-119, BT-152) is one of the rates allowed by the French mapping.',
                'check' => $this->checkAllowedVatRates(...),
            ],
            [
                'code' => 'BR-FR-MAP-14',
                'description' => 'No party country code (BT-40, BT-55, BT-80, EXTFR-FE-157) is a DOM/COM code — those must be reported as FR.',
                'check' => $this->checkNoDomComCountryCodes(...),
            ],
            [
                'code' => 'has-billing-period-date',
                'description' => 'If a billing period (BG-14) is present, its start date (BT-73) or end date (BT-74) is filled.',
                'check' => $this->checkBillingPeriodHasADate(...),
            ],
            [
                'code' => 'has-classification-scheme',
                'description' => 'Every item classification identifier (BT-158) carries a scheme (BT-158-1).',
                'check' => $this->checkClassificationHasScheme(...),
            ],
            [
                'code' => 'has-optional-party-name',
                'description' => 'The delivery (BG-13) and payee (BG-10) parties, when present, have a name.',
                'check' => $this->checkOptionalPartiesHaveNames(...),
            ],
        ];
    }

    private function checkGrandTotalArithmetic(DOMXPath $xpath): ?string
    {
        $basis = $this->summationAmount($xpath, 'TaxBasisTotalAmount');
        $tax = $this->summationAmount($xpath, 'TaxTotalAmount');
        $grand = $this->summationAmount($xpath, 'GrandTotalAmount');

        if ($basis === null || $tax === null || $grand === null) {
            return 'Could not find TaxBasisTotalAmount, TaxTotalAmount or GrandTotalAmount.';
        }

        if (abs(($basis + $tax) - $grand) > 0.005) {
            return sprintf('%.2f + %.2f = %.2f, expected %.2f.', $basis, $tax, $basis + $tax, $grand);
        }

        return null;
    }

    private function checkTaxTotalCurrencyId(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount')?->item(0);

        if ($node === null) {
            return 'Could not find TaxTotalAmount.';
        }

        if (! $node->hasAttribute('currencyID') || $node->getAttribute('currencyID') === '') {
            return 'ram:TaxTotalAmount has no currencyID attribute.';
        }

        return null;
    }

    private function checkDueDateOrPaymentTerms(DOMXPath $xpath): ?string
    {
        $duePayable = $this->summationAmount($xpath, 'DuePayableAmount');

        if ($duePayable === null || $duePayable <= 0) {
            return null;
        }

        $hasDueDate = $xpath->query('//ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime')?->count() > 0;
        $hasTermsText = trim((string) $xpath->query('//ram:SpecifiedTradePaymentTerms/ram:Description')?->item(0)?->textContent) !== '';

        if (! $hasDueDate && ! $hasTermsText) {
            return sprintf('DuePayableAmount is %.2f but no due date or payment terms text was found.', $duePayable);
        }

        return null;
    }

    private function checkAllowanceChargeReasons(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//ram:SpecifiedTradeAllowanceCharge');
        if ($nodes === null || $nodes->count() === 0) {
            return null;
        }

        $missing = 0;
        foreach ($nodes as $index => $node) {
            $reasonXPath = new DOMXPath($node->ownerDocument);
            $reasonXPath->registerNamespace('ram', self::RAM);

            $hasReasonCode = trim((string) $reasonXPath->query('ram:ReasonCode', $node)->item(0)?->textContent) !== '';
            $hasReason = trim((string) $reasonXPath->query('ram:Reason', $node)->item(0)?->textContent) !== '';

            if (! $hasReasonCode && ! $hasReason) {
                $missing++;
            }
        }

        if ($missing > 0) {
            return sprintf('%d of %d SpecifiedTradeAllowanceCharge node(s) have neither ram:Reason nor ram:ReasonCode.', $missing, $nodes->count());
        }

        return null;
    }

    private function checkAllowedVatRates(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//ram:RateApplicablePercent');
        if ($nodes === null || $nodes->count() === 0) {
            return null;
        }

        $invalid = [];
        foreach ($nodes as $node) {
            $rate = (float) $node->textContent;
            if (! FrenchVatRates::isAllowed($rate)) {
                $invalid[] = $node->textContent;
            }
        }

        if ($invalid !== []) {
            return sprintf('Disallowed VAT rate(s): %s.', implode(', ', array_unique($invalid)));
        }

        return null;
    }

    private function checkNoDomComCountryCodes(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//ram:CountryID');
        if ($nodes === null || $nodes->count() === 0) {
            return null;
        }

        $invalid = [];
        foreach ($nodes as $node) {
            if (in_array($node->textContent, FrenchTerritoryCountryCode::domComCodes(), true)) {
                $invalid[] = $node->textContent;
            }
        }

        if ($invalid !== []) {
            return sprintf('DOM/COM country code(s) found where FR was expected: %s.', implode(', ', array_unique($invalid)));
        }

        return null;
    }

    private function checkBillingPeriodHasADate(DOMXPath $xpath): ?string
    {
        $period = $xpath->query('//ram:BillingSpecifiedPeriod')?->item(0);
        if ($period === null) {
            return null;
        }

        $periodXPath = new DOMXPath($period->ownerDocument);
        $periodXPath->registerNamespace('ram', self::RAM);

        $hasStart = trim((string) $periodXPath->query('ram:StartDateTime', $period)->item(0)?->textContent) !== '';
        $hasEnd = trim((string) $periodXPath->query('ram:EndDateTime', $period)->item(0)?->textContent) !== '';

        if (! $hasStart && ! $hasEnd) {
            return 'ram:BillingSpecifiedPeriod has neither ram:StartDateTime nor ram:EndDateTime.';
        }

        return null;
    }

    private function checkClassificationHasScheme(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//ram:DesignatedProductClassification/ram:ClassCode');
        if ($nodes === null || $nodes->count() === 0) {
            return null;
        }

        $missing = 0;
        foreach ($nodes as $node) {
            if (! $node->hasAttribute('listID') || $node->getAttribute('listID') === '') {
                $missing++;
            }
        }

        if ($missing > 0) {
            return sprintf('%d of %d ram:ClassCode node(s) have no listID attribute.', $missing, $nodes->count());
        }

        return null;
    }

    private function checkOptionalPartiesHaveNames(DOMXPath $xpath): ?string
    {
        $missing = [];
        foreach (['ShipToTradeParty', 'PayeeTradeParty'] as $partyElement) {
            $party = $xpath->query("//ram:{$partyElement}")?->item(0);
            if ($party === null) {
                continue;
            }

            $partyXPath = new DOMXPath($party->ownerDocument);
            $partyXPath->registerNamespace('ram', self::RAM);

            if (trim((string) $partyXPath->query('ram:Name', $party)->item(0)?->textContent) === '') {
                $missing[] = $partyElement;
            }
        }

        if ($missing !== []) {
            return sprintf('Missing ram:Name on: %s.', implode(', ', $missing));
        }

        return null;
    }

    private function summationAmount(DOMXPath $xpath, string $elementName): ?float
    {
        $node = $xpath->query("//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:{$elementName}")?->item(0);

        if ($node === null) {
            return null;
        }

        return (float) $node->textContent;
    }
}
