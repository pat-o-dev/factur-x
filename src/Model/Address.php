<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

/**
 * @property-read string $countryCode ISO 3166-1 alpha-2 country code. Kept as a
 *   plain string rather than an enum: unlike unitCode/vatCategory this list has
 *   ~249 equally-legitimate entries, so callers aren't restricted to a curated
 *   subset — see Enum\CountryCode for the common ones, used to drive UI selects.
 */
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
