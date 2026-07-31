<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Xml;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Model\Address;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\Party;
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

    public function test_writes_the_delivery_party_and_date_when_present(): void
    {
        $warehouse = new Party(
            name: 'Entrepôt Nord',
            address: new Address(line1: '9 rue du Quai', city: 'Dunkerque', postalCode: '59140', countryCode: 'FR'),
        );

        $invoice = new Invoice(
            number: 'F20260002',
            issueDate: new DateTimeImmutable('2026-01-15'),
            typeCode: InvoiceTypeCode::CommercialInvoice,
            seller: InvoiceFactory::seller(),
            buyer: InvoiceFactory::buyer(),
            deliveryParty: $warehouse,
            deliveryDate: new DateTimeImmutable('2026-01-20'),
        );

        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);
        $xpath = $this->xpath($xml);

        self::assertSame(
            'Entrepôt Nord',
            $this->text($xpath, '//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:Name'),
        );
        self::assertSame(
            '20260120',
            $this->text($xpath, '//ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString'),
        );
    }

    public function test_omits_the_delivery_element_body_when_absent(): void
    {
        $xml = (new CiiInvoiceWriter())->toXmlString(InvoiceFactory::simple());
        $xpath = $this->xpath($xml);

        self::assertSame(0, $xpath->query('//ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty')?->count());
        self::assertSame(0, $xpath->query('//ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent')?->count());
    }

    public function test_writes_the_payee_party_when_present(): void
    {
        $factor = new Party(
            name: 'Factor Finance SA',
            address: new Address(line1: '1 place de la Bourse', city: 'Paris', postalCode: '75002', countryCode: 'FR'),
            legalRegistrationId: '111222333',
        );

        $invoice = new Invoice(
            number: 'F20260003',
            issueDate: new DateTimeImmutable('2026-01-15'),
            typeCode: InvoiceTypeCode::CommercialInvoice,
            seller: InvoiceFactory::seller(),
            buyer: InvoiceFactory::buyer(),
            payeeParty: $factor,
        );

        $xml = (new CiiInvoiceWriter())->toXmlString($invoice);
        $xpath = $this->xpath($xml);

        self::assertSame(
            'Factor Finance SA',
            $this->text($xpath, '//ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty/ram:Name'),
        );
    }

    public function test_omits_the_payee_party_when_absent(): void
    {
        $xml = (new CiiInvoiceWriter())->toXmlString(InvoiceFactory::simple());
        $xpath = $this->xpath($xml);

        self::assertSame(0, $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:PayeeTradeParty')?->count());
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node?->textContent;
    }
}
