<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Calculation;

use PatODev\FacturX\Calculation\InvoiceCalculator;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\TestCase;

final class InvoiceCalculatorTest extends TestCase
{
    public function test_totals_for_a_single_standard_rate_line(): void
    {
        $invoice = InvoiceFactory::simple();
        $totals = (new InvoiceCalculator())->totals($invoice);

        self::assertSame(200.0, $totals->lineTotalAmount);
        self::assertSame(200.0, $totals->taxExclusiveAmount);
        self::assertSame(40.0, $totals->taxAmount);
        self::assertSame(240.0, $totals->taxInclusiveAmount);
        self::assertSame(240.0, $totals->duePayableAmount);
    }

    public function test_totals_account_for_document_level_allowance(): void
    {
        $invoice = InvoiceFactory::simple();
        $invoice->addDocumentAllowance(new AllowanceCharge(
            isCharge: false,
            amount: 10.0,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
            reasonText: 'Remise commerciale',
        ));

        $totals = (new InvoiceCalculator())->totals($invoice);

        self::assertSame(200.0, $totals->lineTotalAmount);
        self::assertSame(10.0, $totals->allowanceTotalAmount);
        self::assertSame(190.0, $totals->taxExclusiveAmount);
        self::assertSame(38.0, $totals->taxAmount);
        self::assertSame(228.0, $totals->taxInclusiveAmount);
    }

    public function test_vat_breakdown_groups_by_category_and_rate(): void
    {
        $invoice = InvoiceFactory::simple();
        $breakdown = (new InvoiceCalculator())->vatBreakdown($invoice);

        self::assertCount(1, $breakdown);
        self::assertSame(VatCategory::Standard, $breakdown[0]->category);
        self::assertSame(20.0, $breakdown[0]->rate);
        self::assertSame(200.0, $breakdown[0]->taxableAmount);
        self::assertSame(40.0, $breakdown[0]->taxAmount);
    }

    /**
     * 8.75 * 10% = 0.875, exactly halfway between two cents: XP Z12-012 §4.4.6
     * requires halves to round away from zero, so the tax amount must be
     * 0.88, not 0.87 (which is what naive binary-float rounding sometimes
     * gives, e.g. PHP's round() on 0.875 alone can drift to 0.87 due to the
     * double not representing 0.875 exactly).
     */
    public function test_vat_amount_rounds_a_half_cent_away_from_zero(): void
    {
        $invoice = InvoiceFactory::blank();
        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Article à arrondi de TVA pile',
            quantity: 1.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 8.75,
            vatCategory: VatCategory::Standard,
            vatRate: 10.0,
        ));

        $breakdown = (new InvoiceCalculator())->vatBreakdown($invoice);
        $totals = (new InvoiceCalculator())->totals($invoice);

        self::assertSame(0.88, $breakdown[0]->taxAmount);
        self::assertSame(8.75, $totals->taxExclusiveAmount);
        self::assertSame(0.88, $totals->taxAmount);
        self::assertSame(9.63, $totals->taxInclusiveAmount);
    }

    /**
     * Non-round quantities and unit prices (3 decimals) so both the
     * per-line net amount (99.999 -> 100.00) and the VAT amount
     * (139.995 * 5.5% = 7.69972... -> 7.70) genuinely exercise rounding,
     * instead of the "all integers" happy path.
     */
    public function test_totals_with_fractional_quantities_and_prices(): void
    {
        $invoice = InvoiceFactory::blank();
        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Pièces au détail',
            quantity: 3.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 33.333,
            vatCategory: VatCategory::Standard,
            vatRate: 5.5,
        ));
        $invoice->addLine(new InvoiceLine(
            lineId: '2',
            itemName: 'Consommation mesurée',
            quantity: 2.5,
            unitCode: UnitOfMeasureCode::CubicMetre,
            netUnitPrice: 16.014,
            vatCategory: VatCategory::Standard,
            vatRate: 5.5,
        ));

        $line1 = $invoice->lines()[0];
        $line2 = $invoice->lines()[1];

        // 33.333 * 3 = 99.999 -> rounds to 100.00
        self::assertSame(100.0, $line1->netAmount());
        // 16.014 * 2.5 = 40.035 -> rounds to 40.04 (half-cent, away from zero)
        self::assertSame(40.04, $line2->netAmount());

        $totals = (new InvoiceCalculator())->totals($invoice);

        self::assertSame(140.04, $totals->lineTotalAmount);
        self::assertSame(140.04, $totals->taxExclusiveAmount);
        // 140.04 * 5.5% = 7.7022 -> rounds to 7.70
        self::assertSame(7.7, $totals->taxAmount);
        self::assertSame(147.74, $totals->taxInclusiveAmount);
    }

    /**
     * Two VAT groups with amounts that do not fall on round numbers, to make
     * sure grouping + per-group rounding does not silently average out or
     * drift when summed into the header totals.
     */
    public function test_totals_across_two_vat_rates_with_decimals(): void
    {
        $invoice = InvoiceFactory::blank();
        $invoice->addLine(new InvoiceLine(
            lineId: '1',
            itemName: 'Prestation standard',
            quantity: 1.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 19.99,
            vatCategory: VatCategory::Standard,
            vatRate: 20.0,
        ));
        $invoice->addLine(new InvoiceLine(
            lineId: '2',
            itemName: 'Produit alimentaire',
            quantity: 3.0,
            unitCode: UnitOfMeasureCode::Piece,
            netUnitPrice: 4.33,
            vatCategory: VatCategory::Standard,
            vatRate: 5.5,
        ));

        $breakdown = (new InvoiceCalculator())->vatBreakdown($invoice);
        self::assertCount(2, $breakdown);

        /** @var array<string, \PatODev\FacturX\Calculation\VatBreakdownEntry> $byRate */
        $byRate = [];
        foreach ($breakdown as $entry) {
            $byRate[(string) $entry->rate] = $entry;
        }

        // 19.99 * 20% = 3.998 -> rounds to 4.00
        self::assertSame(19.99, $byRate['20']->taxableAmount);
        self::assertSame(4.0, $byRate['20']->taxAmount);

        // 4.33 * 3 = 12.99, * 5.5% = 0.71445 -> rounds to 0.71
        self::assertSame(12.99, $byRate['5.5']->taxableAmount);
        self::assertSame(0.71, $byRate['5.5']->taxAmount);

        $totals = (new InvoiceCalculator())->totals($invoice);

        self::assertSame(32.98, $totals->taxExclusiveAmount);
        self::assertSame(4.71, $totals->taxAmount);
        self::assertSame(37.69, $totals->taxInclusiveAmount);
    }
}
