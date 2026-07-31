<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

use PatODev\FacturX\Builder\PartyBuilder;

/**
 * Shared shape for BG-4 (Seller), BG-7 (Buyer), BG-10 (Payee) and the
 * ship-to party of BG-13 (Delivery information).
 */
final readonly class Party
{
    /**
     * @param  Identifier[]  $privateIdentifiers  BT-29 / BT-46 (e.g. SIRET scheme "0009")
     */
    public function __construct(
        public string $name,
        public Address $address,
        public ?string $legalRegistrationId = null,
        public ?string $vatNumber = null,
        public ?string $taxNumber = null,
        public ?string $tradingName = null,
        public array $privateIdentifiers = [],
        public ?Contact $contact = null,
        public ?ElectronicAddress $electronicAddress = null,
    ) {
    }

    public static function builder(string $name): PartyBuilder
    {
        return PartyBuilder::make($name);
    }
}
