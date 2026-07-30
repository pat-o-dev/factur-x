<?php

declare(strict_types=1);

namespace PatODev\FacturX\Builder;

use PatODev\FacturX\Enum\ElectronicAddressScheme;
use PatODev\FacturX\Enum\PartyIdentifierScheme;
use PatODev\FacturX\Model\Address;
use PatODev\FacturX\Model\Contact;
use PatODev\FacturX\Model\ElectronicAddress;
use PatODev\FacturX\Model\Identifier;
use PatODev\FacturX\Model\Party;

/**
 * Fluent alternative to Party's constructor-with-named-args. Produces a
 * plain, immutable Party — no behaviour of its own beyond assembly.
 */
final class PartyBuilder
{
    private ?Address $address = null;

    private ?string $legalRegistrationId = null;

    private ?string $vatNumber = null;

    private ?string $taxNumber = null;

    private ?string $tradingName = null;

    /** @var Identifier[] */
    private array $privateIdentifiers = [];

    private ?Contact $contact = null;

    private ?ElectronicAddress $electronicAddress = null;

    private function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function address(
        string $line1,
        string $city,
        string $postalCode,
        string $countryCode,
        ?string $line2 = null,
        ?string $line3 = null,
    ): self {
        $this->address = new Address($line1, $city, $postalCode, $countryCode, $line2, $line3);

        return $this;
    }

    public function vatNumber(string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function legalRegistrationId(string $siret): self
    {
        $this->legalRegistrationId = $siret;

        return $this;
    }

    public function taxNumber(string $taxNumber): self
    {
        $this->taxNumber = $taxNumber;

        return $this;
    }

    public function tradingName(string $tradingName): self
    {
        $this->tradingName = $tradingName;

        return $this;
    }

    public function privateIdentifier(string $value, ?PartyIdentifierScheme $schemeId = null): self
    {
        $this->privateIdentifiers[] = new Identifier($value, $schemeId);

        return $this;
    }

    public function contact(?string $name = null, ?string $phone = null, ?string $email = null): self
    {
        $this->contact = new Contact($name, $phone, $email);

        return $this;
    }

    public function electronicAddress(string $value, ElectronicAddressScheme $schemeId = ElectronicAddressScheme::Email): self
    {
        $this->electronicAddress = new ElectronicAddress($value, $schemeId);

        return $this;
    }

    public function build(): Party
    {
        if ($this->address === null) {
            throw new \LogicException("Party \"{$this->name}\" is missing an address; call ->address(...) before ->build().");
        }

        return new Party(
            name: $this->name,
            address: $this->address,
            legalRegistrationId: $this->legalRegistrationId,
            vatNumber: $this->vatNumber,
            taxNumber: $this->taxNumber,
            tradingName: $this->tradingName,
            privateIdentifiers: $this->privateIdentifiers,
            contact: $this->contact,
            electronicAddress: $this->electronicAddress,
        );
    }
}
