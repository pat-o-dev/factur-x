<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

/**
 * BG-1: free-text note qualified by a subject code (BT-21). The French
 * reform requires at least PMT, PMD and AAB notes (rule BR-FR-05) — see
 * Support\MandatoryFrenchNotes for a helper builder.
 */
final readonly class Note
{
    public function __construct(
        public string $content,
        public ?string $subjectCode = null,
    ) {
    }
}
