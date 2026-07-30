<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Validation;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Tests\Support\InvoiceFactory;
use PatODev\FacturX\Validation\InvoiceValidator;
use PatODev\FacturX\Validation\RuleResult;
use PatODev\FacturX\Validation\ValidationReport;
use PatODev\FacturX\Xml\CiiInvoiceWriter;
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
