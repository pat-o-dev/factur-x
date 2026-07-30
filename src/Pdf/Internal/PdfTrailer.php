<?php

declare(strict_types=1);

namespace PatODev\FacturX\Pdf\Internal;

/** Parsed result of the last classic trailer/xref section of a PDF file. */
final readonly class PdfTrailer
{
    public function __construct(
        public int $rootObjectNumber,
        public int $rootGeneration,
        public int $size,
        public int $xrefOffset,
        public string $rootDictionary,
        public int $rootObjectOffset,
    ) {
    }
}
