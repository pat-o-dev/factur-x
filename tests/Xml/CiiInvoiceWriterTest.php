<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Xml;

use DOMDocument;
use DOMXPath;
use PatODev\FacturX\Tests\Support\InvoiceFactory;
use PatODev\FacturX\Xml\CiiInvoiceWriter;
use PHPUnit\Framework\TestCase;

final class CiiInvoiceWriterTest extends TestCase
{
    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        return $xpath;
    }

    public function test_generates_valid_xml_with_expected_header_fields(): void
    {
        $invoice = InvoiceFactory::simple();
        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);

        $xpath = $this->xpath($xml);

        self::assertSame('F20260001', $this->text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID'));
        self::assertSame('380', $this->text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame('20260115', $this->text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString'));
        self::assertSame(
            'urn:cen.eu:en16931:2017',
            $this->text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'),
        );
    }

    public function test_generates_line_and_totals(): void
    {
        $invoice = InvoiceFactory::simple();
        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);

        $xpath = $this->xpath($xml);

        $lines = $xpath->query('//ram:IncludedSupplyChainTradeLineItem');
        self::assertNotFalse($lines);
        self::assertSame(1, $lines->count());

        self::assertSame(
            '200.00',
            $this->text($xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount'),
        );
        self::assertSame(
            '40.00',
            $this->text($xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount'),
        );
        self::assertSame(
            '240.00',
            $this->text($xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'),
        );
    }

    public function test_tax_total_amount_carries_the_invoice_currency_id(): void
    {
        // BR-CO-15 (KoSIT and other EN 16931 validators): ram:TaxTotalAmount is
        // the only header monetary amount that requires a currencyID attribute.
        $invoice = InvoiceFactory::simple();
        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);

        $xpath = $this->xpath($xml);
        $node = $xpath->query('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount')?->item(0);

        self::assertNotNull($node);
        self::assertSame($invoice->currencyCode->value, $node->attributes?->getNamedItem('currencyID')?->textContent);
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node?->textContent;
    }
}
