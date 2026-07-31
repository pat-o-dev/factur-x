<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Builder;

use DateTimeImmutable;
use LogicException;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\Party;
use PHPUnit\Framework\TestCase;

final class InvoiceBuilderTest extends TestCase
{
    public function test_builds_an_equivalent_invoice_to_the_constructor_form(): void
    {
        $seller = Party::builder('ACME Transport SARL')
            ->address('1 rue des Tests', 'Paris', '75001', 'FR')
            ->legalRegistrationId('123456789')
            ->vatNumber('FR12123456789')
            ->build();

        $buyer = Party::builder('Client SAS')
            ->address('2 avenue du Test', 'Lyon', '69001', 'FR')
            ->legalRegistrationId('987654321')
            ->vatNumber('FR98987654321')
            ->build();

        $invoice = Invoice::builder('F20260001')
            ->issueDate(new DateTimeImmutable('2026-01-15'))
            ->seller($seller)
            ->buyer($buyer)
            ->dueInDays(30)
            ->line(
                lineId: '1',
                itemName: 'Prestation de transport',
                quantity: 2.0,
                unitCode: UnitOfMeasureCode::Piece,
                netUnitPrice: 100.0,
                vatCategory: VatCategory::Standard,
                vatRate: 20.0,
            )
            ->build();

        self::assertSame('F20260001', $invoice->number);
        self::assertSame('ACME Transport SARL', $invoice->seller->name);
        self::assertSame('FR12123456789', $invoice->seller->vatNumber);
        self::assertSame('Client SAS', $invoice->buyer->name);
        self::assertCount(1, $invoice->lines());
        self::assertSame('Prestation de transport', $invoice->lines()[0]->itemName);
        self::assertEquals(new DateTimeImmutable('2026-02-14'), $invoice->dueDate);
    }

    public function test_due_in_days_is_resolved_from_the_final_issue_date(): void
    {
        $invoice = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->dueInDays(10)
            ->issueDate(new DateTimeImmutable('2026-01-01'))
            ->build();

        self::assertEquals(new DateTimeImmutable('2026-01-11'), $invoice->dueDate);
    }

    public function test_line_defaults_to_standard_vat_and_auto_numbered_line_ids(): void
    {
        $invoice = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->line(itemName: 'A', quantity: 1.0, unitCode: UnitOfMeasureCode::Piece, netUnitPrice: 10.0, vatRate: 20.0)
            ->line(itemName: 'B', quantity: 1.0, unitCode: UnitOfMeasureCode::Piece, netUnitPrice: 10.0, vatRate: 20.0)
            ->build();

        self::assertSame('1', $invoice->lines()[0]->lineId);
        self::assertSame('2', $invoice->lines()[1]->lineId);
        self::assertSame(VatCategory::Standard, $invoice->lines()[0]->vatCategory);
    }

    public function test_delivery_party_and_payee_party_are_optional_and_independent(): void
    {
        $warehouse = $this->minimalParty('Entrepôt');
        $factor = $this->minimalParty('Factor Finance');

        $invoice = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->deliveryParty($warehouse, new DateTimeImmutable('2026-01-20'))
            ->payeeParty($factor)
            ->build();

        self::assertSame('Entrepôt', $invoice->deliveryParty?->name);
        self::assertEquals(new DateTimeImmutable('2026-01-20'), $invoice->deliveryDate);
        self::assertSame('Factor Finance', $invoice->payeeParty?->name);
    }

    public function test_delivery_and_payee_party_default_to_null(): void
    {
        $invoice = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->build();

        self::assertNull($invoice->deliveryParty);
        self::assertNull($invoice->deliveryDate);
        self::assertNull($invoice->payeeParty);
    }

    public function test_billing_period_is_optional_and_defaults_to_null(): void
    {
        $invoice = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->billingPeriod(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-31'))
            ->build();

        self::assertEquals(new DateTimeImmutable('2026-01-01'), $invoice->billingPeriodStartDate);
        self::assertEquals(new DateTimeImmutable('2026-01-31'), $invoice->billingPeriodEndDate);

        $invoiceWithoutPeriod = Invoice::builder('F1')
            ->seller($this->minimalParty('Seller'))
            ->buyer($this->minimalParty('Buyer'))
            ->build();

        self::assertNull($invoiceWithoutPeriod->billingPeriodStartDate);
        self::assertNull($invoiceWithoutPeriod->billingPeriodEndDate);
    }

    public function test_rejects_building_without_a_seller(): void
    {
        $this->expectException(LogicException::class);

        Invoice::builder('F1')->buyer($this->minimalParty('Buyer'))->build();
    }

    public function test_rejects_building_without_a_buyer(): void
    {
        $this->expectException(LogicException::class);

        Invoice::builder('F1')->seller($this->minimalParty('Seller'))->build();
    }

    private function minimalParty(string $name): Party
    {
        return Party::builder($name)->address('1 rue X', 'Paris', '75001', 'FR')->build();
    }
}
