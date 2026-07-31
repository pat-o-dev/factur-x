<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Validation;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Model\Address;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Model\Party;
use PatODev\FacturX\Tests\Support\InvoiceFactory;
use PatODev\FacturX\Validation\InvoiceValidator;
use PatODev\FacturX\Validation\RuleResult;
use PatODev\FacturX\Validation\ValidationReport;
use PatODev\FacturX\Xml\CiiInvoiceWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceValidatorTest extends TestCase
{
    public function test_a_well_formed_conformant_invoice_passes_every_rule(): void
    {
        $report = (new InvoiceValidator())->validate($this->baselineXml());

        self::assertTrue($report->passed(), (string) json_encode($report->failures()));
    }

    public function test_fails_on_malformed_xml(): void
    {
        $report = (new InvoiceValidator())->validate('not xml at all <<<');

        self::assertFalse($report->passed());
        self::assertSame('well-formed-xml', $report->results[0]->code);
        self::assertFalse($report->results[0]->passed);
    }

    public function test_fails_when_root_element_is_wrong(): void
    {
        $report = (new InvoiceValidator())->validate('<?xml version="1.0"?><NotAnInvoice/>');

        self::assertFalse($report->passed());
        self::assertFalse($this->ruleResult($report, 'root-element')->passed);
    }

    public function test_br_co_15_fails_on_arithmetic_mismatch(): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath): void {
            $node = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount')->item(0);
            $node->textContent = '999.99';
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-CO-15')->passed);
    }

    public function test_br_co_15_currency_id_fails_when_attribute_missing(): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath): void {
            $node = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount')->item(0);
            $node->removeAttribute('currencyID');
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-CO-15-currencyID')->passed);
    }

    public function test_br_co_25_fails_when_due_payable_positive_and_no_due_date_or_terms(): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath): void {
            $terms = $xpath->query('//ram:SpecifiedTradePaymentTerms')->item(0);
            $terms->parentNode->removeChild($terms);
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-CO-25')->passed);
    }

    public function test_br_co_5_6_fails_when_allowance_charge_missing_reason(): void
    {
        $invoice = $this->baselineInvoice();
        $invoice->addDocumentAllowance(new AllowanceCharge(
            isCharge: false,
            amount: 10.0,
            vatCategory: VatCategory::Standard,
        ));

        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-CO-5-6')->passed);
    }

    public function test_br_fr_map_12_fails_on_a_disallowed_vat_rate(): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath): void {
            $node = $xpath->query('//ram:RateApplicablePercent')->item(0);
            $node->textContent = '15.00';
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-FR-MAP-12')->passed);
    }

    /** @return array<string, array{float}> */
    public static function allowedVatRateProvider(): array
    {
        return [
            '0' => [0.0], '10' => [10.0], '13' => [13.0], '20' => [20.0],
            '8.5' => [8.5], '19.6' => [19.6], '2.1' => [2.1], '5.5' => [5.5],
            '7' => [7.0], '20.6' => [20.6], '1.05' => [1.05], '0.9' => [0.9],
            '1.75' => [1.75], '9.2' => [9.2], '9.6' => [9.6],
        ];
    }

    #[DataProvider('allowedVatRateProvider')]
    public function test_br_fr_map_12_passes_for_every_allowed_vat_rate(float $rate): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath) use ($rate): void {
            $node = $xpath->query('//ram:RateApplicablePercent')->item(0);
            $node->textContent = number_format($rate, 2, '.', '');
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertTrue($this->ruleResult($report, 'BR-FR-MAP-12')->passed);
    }

    public function test_br_fr_map_14_fails_when_a_country_code_is_a_dom_com_code(): void
    {
        $xml = $this->mutate($this->baselineXml(), function (DOMXPath $xpath): void {
            $node = $xpath->query('//ram:SellerTradeParty//ram:CountryID')->item(0);
            $node->textContent = 'RE';
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'BR-FR-MAP-14')->passed);
    }

    public function test_has_billing_period_date_fails_when_both_dates_are_missing(): void
    {
        $invoice = $this->baselineInvoice();
        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);

        // Inject an empty BillingSpecifiedPeriod (as a malformed third-party invoice might).
        $xml = str_replace(
            '<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>',
            '<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode><ram:BillingSpecifiedPeriod></ram:BillingSpecifiedPeriod>',
            $xml,
        );

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'has-billing-period-date')->passed);
    }

    public function test_has_billing_period_date_passes_when_present(): void
    {
        $invoice = new Invoice(
            number: 'F20260002',
            issueDate: new DateTimeImmutable('2026-01-15'),
            typeCode: InvoiceTypeCode::CommercialInvoice,
            seller: InvoiceFactory::seller(),
            buyer: InvoiceFactory::buyer(),
            dueDate: new DateTimeImmutable('2026-02-14'),
            billingPeriodStartDate: new DateTimeImmutable('2026-01-01'),
            billingPeriodEndDate: new DateTimeImmutable('2026-01-31'),
        );
        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Abonnement',
            quantity: 1.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 100.0,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
        ));

        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);
        $report = (new InvoiceValidator())->validate($xml);

        self::assertTrue($this->ruleResult($report, 'has-billing-period-date')->passed);
    }

    public function test_has_classification_scheme_fails_when_list_id_is_missing(): void
    {
        $invoice = $this->baselineInvoice();
        $invoice->addLine(new InvoiceLine(
            lineId: '2',
            itemName: 'Composants électroniques',
            quantity: 1.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 50.0,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
            classificationCode: '8541.10',
            classificationScheme: 'HS',
        ));

        $xml = $this->mutate((new CiiInvoiceWriter())->toXmlString($invoice), function (DOMXPath $xpath): void {
            $xpath->query('//ram:DesignatedProductClassification/ram:ClassCode')->item(0)->removeAttribute('listID');
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'has-classification-scheme')->passed);
    }

    public function test_has_optional_party_name_fails_when_delivery_party_name_is_blank(): void
    {
        $invoice = $this->baselineInvoice();
        $invoice = new Invoice(
            number: $invoice->number,
            issueDate: $invoice->issueDate,
            typeCode: $invoice->typeCode,
            seller: $invoice->seller,
            buyer: $invoice->buyer,
            dueDate: $invoice->dueDate,
            deliveryParty: new Party(
                name: 'Entrepôt',
                address: new Address(line1: '9 rue du Quai', city: 'Dunkerque', postalCode: '59140', countryCode: 'FR'),
            ),
        );
        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Prestation',
            quantity: 1.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 100.0,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
        ));

        $xml = $this->mutate((new CiiInvoiceWriter())->toXmlString($invoice), function (DOMXPath $xpath): void {
            $xpath->query('//ram:ShipToTradeParty/ram:Name')->item(0)->textContent = '';
        });

        $report = (new InvoiceValidator())->validate($xml);

        self::assertFalse($this->ruleResult($report, 'has-optional-party-name')->passed);
    }

    private function baselineInvoice(): Invoice
    {
        $invoice = new Invoice(
            number: 'F20260001',
            issueDate: new DateTimeImmutable('2026-01-15'),
            typeCode: InvoiceTypeCode::CommercialInvoice,
            seller: InvoiceFactory::seller(),
            buyer: InvoiceFactory::buyer(),
            dueDate: new DateTimeImmutable('2026-02-14'),
        );

        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Prestation de transport',
            quantity: 2.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 100.0,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
        ));

        return $invoice;
    }

    private function baselineXml(): string
    {
        return (new CiiInvoiceWriter())->toXmlString($this->baselineInvoice());
    }

    private function ruleResult(ValidationReport $report, string $code): RuleResult
    {
        foreach ($report->results as $result) {
            if ($result->code === $code) {
                return $result;
            }
        }

        self::fail("No rule result found for code {$code}.");
    }

    private function mutate(string $xml, callable $mutator): string
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        $mutator($xpath);

        return (string) $doc->saveXML();
    }
}
