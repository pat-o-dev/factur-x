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
        /**
         * Object number of the Catalog's existing /Metadata stream, if any
         * (common: mPDF/TCPDF always emit one in PDF/A mode). Reused in
         * place — via the same incremental-update mechanism as the Root
         * object — rather than rejected, since PDF/A conformance requires it.
         */
        public ?int $metadataObjectNumber = null,
    ) {
    }
}
