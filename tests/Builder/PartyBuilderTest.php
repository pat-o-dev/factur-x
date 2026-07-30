<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Builder;

use LogicException;
use PatODev\FacturX\Model\Party;
use PHPUnit\Framework\TestCase;

final class PartyBuilderTest extends TestCase
{
    public function test_builds_a_fully_populated_party(): void
    {
        $party = Party::builder('ACME Transport SARL')
            ->address('1 rue des Tests', 'Paris', '75001', 'FR')
            ->vatNumber('FR12123456789')
            ->legalRegistrationId('123456789')
            ->contact(name: 'Jean Dupont', email: 'jean@acme.example')
            ->electronicAddress('0009123456789', '0009')
            ->privateIdentifier('123456789', '0009')
            ->build();

        self::assertSame('ACME Transport SARL', $party->name);
        self::assertSame('75001', $party->address->postalCode);
        self::assertSame('FR12123456789', $party->vatNumber);
        self::assertSame('123456789', $party->legalRegistrationId);
        self::assertSame('Jean Dupont', $party->contact?->name);
        self::assertSame('0009123456789', $party->electronicAddress?->value);
        self::assertCount(1, $party->privateIdentifiers);
    }

    public function test_rejects_building_without_an_address(): void
    {
        $this->expectException(LogicException::class);

        Party::builder('ACME Transport SARL')->build();
    }
}
