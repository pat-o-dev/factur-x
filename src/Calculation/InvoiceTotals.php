<?php

declare(strict_types=1);

namespace PatODev\FacturX\Calculation;

/** BT-106 to BT-115. */
final readonly class InvoiceTotals
{
    public function __construct(
        public float $lineTotalAmount,
        public float $allowanceTotalAmount,
        public float $chargeTotalAmount,
        public float $taxExclusiveAmount,
        public float $taxAmount,
        public float $taxInclusiveAmount,
        public float $prepaidAmount,
        public float $roundingAmount,
        public float $duePayableAmount,
    ) {
    }
}
