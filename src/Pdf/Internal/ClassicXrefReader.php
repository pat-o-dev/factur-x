<?php

declare(strict_types=1);

namespace PatODev\FacturX\Pdf\Internal;

use PatODev\FacturX\Exception\UnsupportedPdfException;

/**
 * Reads just enough of a PDF (classic, non-encrypted, non-linearized-stream
 * xref) to locate its Catalog object, so PdfA3Attacher can append an
 * incremental update. Cross-reference *streams* (PDF 1.5+ compact xref,
 * common for some generators) and encrypted files are out of scope for v1
 * and raise UnsupportedPdfException with an explicit message.
 */
final class ClassicXrefReader
{
    public function __construct(
        private readonly PdfDictionaryParser $parser = new PdfDictionaryParser(),
    ) {
    }

    public function read(string $pdf): PdfTrailer
    {
        $section = $this->parser->locateLatestXrefSection($pdf);
        $xrefOffset = $section['xrefOffset'];
        $trailerDict = $section['trailerDict'];
        $offsets = $section['offsets'];

        if (! preg_match('/\/Root\s+(\d+)\s+(\d+)\s+R/', $trailerDict, $rootMatch)) {
            throw new UnsupportedPdfException('Could not find /Root in the PDF trailer.');
        }
        $rootObjNum = (int) $rootMatch[1];
        $rootGen = (int) $rootMatch[2];

        if (! preg_match('/\/Size\s+(\d+)/', $trailerDict, $sizeMatch)) {
            throw new UnsupportedPdfException('Could not find /Size in the PDF trailer.');
        }
        $size = (int) $sizeMatch[1];

        if (! isset($offsets[$rootObjNum])) {
            throw new UnsupportedPdfException('Could not resolve the Catalog (/Root) object offset.');
        }
        $rootOffset = $offsets[$rootObjNum];

        if (! preg_match('/^\s*'.$rootObjNum.'\s+'.$rootGen.'\s+obj/', substr($pdf, $rootOffset, 40), $objMatch)) {
            throw new UnsupportedPdfException('The /Root object header does not match the xref table entry.');
        }

        $rootDict = $this->parser->extractDictionary($pdf, $rootOffset + strlen($objMatch[0]));

        foreach (['/Names', '/AF'] as $forbidden) {
            if (str_contains($rootDict, $forbidden)) {
                throw new UnsupportedPdfException(
                    "The PDF Catalog already declares {$forbidden}; merging with an existing ".
                    'attachment tree is not supported in v1.',
                );
            }
        }

        $metadataObjectNumber = null;
        if (str_contains($rootDict, '/Metadata')) {
            if (! preg_match('/\/Metadata\s+(\d+)\s+\d+\s+R/', $rootDict, $metadataMatch)) {
                throw new UnsupportedPdfException(
                    'The PDF Catalog declares /Metadata as an inline value rather than an '
                    .'indirect reference; this is not supported in v1.',
                );
            }
            $metadataObjectNumber = (int) $metadataMatch[1];
        }

        return new PdfTrailer(
            rootObjectNumber: $rootObjNum,
            rootGeneration: $rootGen,
            size: $size,
            xrefOffset: $xrefOffset,
            rootDictionary: $rootDict,
            rootObjectOffset: $rootOffset,
            metadataObjectNumber: $metadataObjectNumber,
        );
    }
}
