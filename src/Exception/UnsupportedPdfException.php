<?php

declare(strict_types=1);

namespace PatODev\FacturX\Exception;

/**
 * Thrown when the base PDF cannot be safely mutated by the incremental
 * attacher (e.g. it uses PDF 1.5+ cross-reference streams / object streams,
 * or is encrypted). See Pdf\PdfA3Attacher for the supported subset.
 */
class UnsupportedPdfException extends FacturXException
{
}
