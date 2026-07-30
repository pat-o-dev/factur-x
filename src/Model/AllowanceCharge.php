<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

use PatODev\FacturX\Enum\VatCategory;

/**
 * A single allowance (remise) or charge, at document level (BG-20/BG-21) or
 * line level (BG-27/BG-28). One of $reasonCode / $reasonText is required by
 * BR-CO-5 / BR-CO-6 (checked by the calculator, not here).
 */
final readonly class AllowanceCharge
{
    public function __construct(
        public bool $isCharge,
        public float $amount,
        public VatCategory $vatCategory,
        public ?float $vatRate = null,
        public ?float $baseAmount = null,
        public ?float $percentage = null,
        public ?string $reasonCode = null,
        public ?string $reasonText = null,
    ) {
    }
}
