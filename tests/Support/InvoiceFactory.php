<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Support;

use DateTimeImmutable;
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Model\Address;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Model\Party;

final class InvoiceFactory
{
    public static function seller(): Party
    {
        return new Party(
            name: 'ACME Transport SARL',
            address: new Address(line1: '1 rue des Tests', city: 'Paris', postalCode: '75001', countryCode: 'FR'),
            legalRegistrationId: '123456789',
            vatNumber: 'FR12123456789',
        );
    }

    public static function buyer(): Party
    {
        return new Party(
            name: 'Client SAS',
            address: new Address(line1: '2 avenue du Test', city: 'Lyon', postalCode: '69001', countryCode: 'FR'),
            legalRegistrationId: '987654321',
            vatNumber: 'FR98987654321',
        );
    }

    /** Bare invoice (no lines) with a fixed seller/buyer, ready for addLine() calls in tests. */
    public static function blank(string $number = 'F20260001'): Invoice
    {
        return new Invoice(
            number: $number,
            issueDate: new DateTimeImmutable('2026-01-15'),
            typeCode: InvoiceTypeCode::CommercialInvoice,
            seller: self::seller(),
            buyer: self::buyer(),
        );
    }

    public static function simple(): Invoice
    {
        $invoice = self::blank();

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
}
