<?php

declare(strict_types=1);

namespace PatODev\FacturX\Calculation;

use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\Enum\VatExemptionReasonCode;

/** One row of BG-23 (VAT breakdown), grouped by category + rate + exemption reason. */
final readonly class VatBreakdownEntry
{
    public function __construct(
        public VatCategory $category,
        public ?float $rate,
        public float $taxableAmount,
        public float $taxAmount,
        public ?string $exemptionReasonText = null,
        public ?VatExemptionReasonCode $exemptionReasonCode = null,
    ) {
    }

    public function groupingKey(): string
    {
        return implode('|', [
            $this->category->value,
            $this->rate !== null ? (string) $this->rate : '',
            $this->exemptionReasonText ?? '',
            $this->exemptionReasonCode?->value ?? '',
        ]);
    }
}
