<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

/**
 * BG-16: simplified to the most common French B2B case (SEPA credit transfer).
 */
final readonly class PaymentMeans
{
    public function __construct(
        public string $typeCode = '58',
        public ?string $iban = null,
        public ?string $bic = null,
        public ?string $paymentReference = null,
    ) {
    }
}
