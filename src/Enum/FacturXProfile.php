<?php

declare(strict_types=1);

namespace PatODev\FacturX\Enum;

/**
 * BT-24 guideline URN. v1 only targets the EN 16931 (French CIUS) profile;
 * EXTENDED-CTC-FR support is tracked for a later release.
 */
enum FacturXProfile: string
{
    case En16931 = 'urn:cen.eu:en16931:2017';

    public function facturXAttachmentName(): string
    {
        return match ($this) {
            self::En16931 => 'factur-x.xml',
        };
    }
}
