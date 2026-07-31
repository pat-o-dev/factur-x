<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Enum\VatExemptionReasonCode;
use PatODev\FacturX\Support\Money;

/**
 * A single invoice line (BG-25). sellerAssignedId/buyerAssignedId are BG-31's
 * BT-155/BT-156 (item identifiers, e.g. SKUs). originCountryCode/
 * classificationCode(Scheme) are BT-159 (item country of origin) and
 * BT-158/BT-158-1 (item classification identifier + scheme, e.g. a customs
 * tariff code).
 *
 * @property-read AllowanceCharge[] $allowances line-level rebates (BG-27)
 * @property-read AllowanceCharge[] $charges    line-level charges (BG-28)
 */
final class InvoiceLine
{
    /**
     * @param  AllowanceCharge[]  $allowances
     * @param  AllowanceCharge[]  $charges
     */
    public function __construct(
        public readonly string $lineId,
        public readonly string $itemName,
        public readonly float $quantity,
        public readonly UnitOfMeasureCode $unitCode,
        public readonly float $netUnitPrice,
        public readonly VatCategory $vatCategory,
        public readonly ?float $vatRate = null,
        public readonly ?string $itemDescription = null,
        public readonly ?float $grossUnitPrice = null,
        public readonly ?string $vatExemptionReasonText = null,
        public readonly ?VatExemptionReasonCode $vatExemptionReasonCode = null,
        public readonly ?string $buyerOrderLineReference = null,
        public readonly ?string $sellerAssignedId = null,
        public readonly ?string $buyerAssignedId = null,
        public readonly ?string $originCountryCode = null,
        public readonly ?string $classificationCode = null,
        public readonly ?string $classificationScheme = null,
        public readonly array $allowances = [],
        public readonly array $charges = [],
    ) {
    }

    /**
     * BT-131: line net amount, before it is folded into the invoice totals.
     * quantity * netUnitPrice, plus line charges, minus line allowances.
     */
    public function netAmount(): float
    {
        $base = Money::round2($this->netUnitPrice * $this->quantity);

        $allowanceTotal = array_sum(array_map(
            static fn (AllowanceCharge $a): float => $a->amount,
            $this->allowances,
        ));
        $chargeTotal = array_sum(array_map(
            static fn (AllowanceCharge $c): float => $c->amount,
            $this->charges,
        ));

        return Money::round2($base - $allowanceTotal + $chargeTotal);
    }
}
