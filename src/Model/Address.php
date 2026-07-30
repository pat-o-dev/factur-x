<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

final readonly class Address
{
    public function __construct(
        public string $line1,
        public string $city,
        public string $postalCode,
        public string $countryCode,
        public ?string $line2 = null,
        public ?string $line3 = null,
    ) {
    }
}
