<?php

declare(strict_types=1);

namespace PatODev\FacturX\Calculation;

use PatODev\FacturX\Enum\VatExemptionReasonCode;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Support\Money;

/**
 * Implements the calculation rules of XP Z12-012 §4.4.5 (BT-106..BT-115) and
 * the VAT breakdown grouping (BG-23) described in §4.4.7.
 */
final class InvoiceCalculator
{
    public function totals(Invoice $invoice, float $prepaidAmount = 0.0, float $roundingAmount = 0.0): InvoiceTotals
    {
        $lineTotal = Money::round2(array_sum(array_map(
            static fn ($line) => $line->netAmount(),
            $invoice->lines(),
        )));

        $allowanceTotal = Money::round2($this->sumAmount($invoice->documentAllowances()));
        $chargeTotal = Money::round2($this->sumAmount($invoice->documentCharges()));

        $taxExclusive = Money::round2($lineTotal - $allowanceTotal + $chargeTotal);

        $taxAmount = Money::round2(array_sum(array_map(
            static fn (VatBreakdownEntry $e) => $e->taxAmount,
            $this->vatBreakdown($invoice),
        )));

        $taxInclusive = Money::round2($taxExclusive + $taxAmount);
        $duePayable = Money::round2($taxInclusive - $prepaidAmount + $roundingAmount);

        return new InvoiceTotals(
            lineTotalAmount: $lineTotal,
            allowanceTotalAmount: $allowanceTotal,
            chargeTotalAmount: $chargeTotal,
            taxExclusiveAmount: $taxExclusive,
            taxAmount: $taxAmount,
            taxInclusiveAmount: $taxInclusive,
            prepaidAmount: Money::round2($prepaidAmount),
            roundingAmount: Money::round2($roundingAmount),
            duePayableAmount: $duePayable,
        );
    }

    /**
     * Groups lines and document-level allowances/charges by
     * (VAT category, rate, exemption reason) and computes the taxable base
     * and tax amount for each group.
     *
     * @return VatBreakdownEntry[]
     */
    public function vatBreakdown(Invoice $invoice): array
    {
        /** @var array<string, array{category: \PatODev\FacturX\Enum\VatCategory, rate: ?float, reasonText: ?string, reasonCode: ?VatExemptionReasonCode, base: float}> $groups */
        $groups = [];

        foreach ($invoice->lines() as $line) {
            $key = $this->groupKey($line->vatCategory->value, $line->vatRate, $line->vatExemptionReasonText, $line->vatExemptionReasonCode);
            $groups[$key] ??= [
                'category' => $line->vatCategory,
                'rate' => $line->vatRate,
                'reasonText' => $line->vatExemptionReasonText,
                'reasonCode' => $line->vatExemptionReasonCode,
                'base' => 0.0,
            ];
            $groups[$key]['base'] += $line->netAmount();
        }

        foreach ($invoice->documentCharges() as $charge) {
            $this->foldAllowanceCharge($groups, $charge, sign: 1);
        }

        foreach ($invoice->documentAllowances() as $allowance) {
            $this->foldAllowanceCharge($groups, $allowance, sign: -1);
        }

        return array_values(array_map(
            static fn (array $g): VatBreakdownEntry => new VatBreakdownEntry(
                category: $g['category'],
                rate: $g['rate'],
                taxableAmount: Money::round2($g['base']),
                taxAmount: Money::round2($g['base'] * (($g['rate'] ?? 0.0) / 100)),
                exemptionReasonText: $g['reasonText'],
                exemptionReasonCode: $g['reasonCode'],
            ),
            $groups,
        ));
    }

    /** @param array<string, array{category: mixed, rate: ?float, reasonText: ?string, reasonCode: ?VatExemptionReasonCode, base: float}> $groups */
    private function foldAllowanceCharge(array &$groups, AllowanceCharge $item, int $sign): void
    {
        $key = $this->groupKey($item->vatCategory->value, $item->vatRate, null, null);
        $groups[$key] ??= [
            'category' => $item->vatCategory,
            'rate' => $item->vatRate,
            'reasonText' => null,
            'reasonCode' => null,
            'base' => 0.0,
        ];
        $groups[$key]['base'] += $sign * $item->amount;
    }

    private function groupKey(string $category, ?float $rate, ?string $reasonText, ?VatExemptionReasonCode $reasonCode): string
    {
        return implode('|', [$category, $rate !== null ? (string) $rate : '', $reasonText ?? '', $reasonCode?->value ?? '']);
    }

    /** @param AllowanceCharge[] $items */
    private function sumAmount(array $items): float
    {
        return array_sum(array_map(static fn (AllowanceCharge $i) => $i->amount, $items));
    }
}
