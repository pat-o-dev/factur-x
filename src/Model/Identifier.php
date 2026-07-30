<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

use PatODev\FacturX\Enum\PartyIdentifierScheme;

/**
 * A qualified identifier: value + optional scheme (e.g. SIRET scheme "0009",
 * GLN scheme "0088"...). Used for BT-29/BT-46 (private identifiers).
 */
final readonly class Identifier
{
    public function __construct(
        public string $value,
        public ?PartyIdentifierScheme $schemeId = null,
    ) {
    }
}
