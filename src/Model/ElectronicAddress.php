<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

/**
 * BT-34 / BT-49: e-invoicing routing address. schemeId "0225" for the French
 * PPF annuaire (SIREN / SIREN_suffix), "EM" for a plain e-mail address.
 */
final readonly class ElectronicAddress
{
    public function __construct(
        public string $value,
        public string $schemeId = 'EM',
    ) {
    }
}
