<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

/**
 * A qualified identifier: value + optional scheme (e.g. VAT number scheme "VA",
 * SIRET scheme "0009", GLN scheme "0088"...). Used for BT-29/BT-30/BT-31/BT-46/...
 */
final readonly class Identifier
{
    public function __construct(
        public string $value,
        public ?string $schemeId = null,
    ) {
    }
}
