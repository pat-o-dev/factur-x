<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

final readonly class Contact
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {
    }
}
