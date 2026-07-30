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
    public function read(string $pdf): PdfTrailer
    {
        $startXrefPos = strrpos($pdf, 'startxref');
        if ($startXrefPos === false) {
            throw new UnsupportedPdfException('Could not locate the "startxref" keyword in the source PDF.');
        }

        if (! preg_match('/startxref\s+(\d+)/', substr($pdf, $startXrefPos), $matches)) {
            throw new UnsupportedPdfException('Could not parse the startxref offset.');
        }
        $xrefOffset = (int) $matches[1];

        if (! preg_match('/^xref\s*\r?\n/', substr($pdf, $xrefOffset, 10))) {
            throw new UnsupportedPdfException(
                'This PDF uses a cross-reference stream (PDF 1.5+) or a non-standard '
                .'xref layout, which is not yet supported. Regenerate the base PDF '
                .'with a tool that emits a classic xref table (e.g. mPDF, TCPDF, DomPDF).',
            );
        }

        $trailerPos = strpos($pdf, 'trailer', $xrefOffset);
        if ($trailerPos === false) {
            throw new UnsupportedPdfException('Could not locate the "trailer" keyword.');
        }

        $offsets = $this->parseXrefEntries(substr($pdf, $xrefOffset, $trailerPos - $xrefOffset));

        $trailerDict = $this->extractDictionary($pdf, $trailerPos + strlen('trailer'));

        if (str_contains($trailerDict, '/Encrypt')) {
            throw new UnsupportedPdfException('Encrypted PDFs are not supported.');
        }

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

        $rootDict = $this->extractDictionary($pdf, $rootOffset + strlen($objMatch[0]));

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

    /** @return array<int, int> object number => byte offset, for in-use ("n") entries only */
    private function parseXrefEntries(string $section): array
    {
        $offsets = [];
        $lines = preg_split('/\r\n|\r|\n/', $section) ?: [];

        $currentObjNum = null;
        $indexInSubsection = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'xref') {
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header)) {
                $currentObjNum = (int) $header[1];
                $indexInSubsection = 0;

                continue;
            }

            if ($currentObjNum === null) {
                continue;
            }

            if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])/', $line, $entry)) {
                if ($entry[3] === 'n') {
                    $offsets[$currentObjNum + $indexInSubsection] = (int) $entry[1];
                }
                $indexInSubsection++;
            }
        }

        return $offsets;
    }

    /** Returns the full "<< ... >>" text (braces included) starting from the first "<<" at/after $from. */
    private function extractDictionary(string $pdf, int $from): string
    {
        $start = strpos($pdf, '<<', $from);
        if ($start === false) {
            throw new UnsupportedPdfException('Could not locate a dictionary ("<<") at the expected offset.');
        }

        $depth = 0;
        $length = strlen($pdf);
        $pos = $start;

        while ($pos < $length) {
            if (substr($pdf, $pos, 2) === '<<') {
                $depth++;
                $pos += 2;

                continue;
            }
            if (substr($pdf, $pos, 2) === '>>') {
                $depth--;
                $pos += 2;
                if ($depth === 0) {
                    return substr($pdf, $start, $pos - $start);
                }

                continue;
            }
            $pos++;
        }

        throw new UnsupportedPdfException('Unterminated dictionary while parsing the PDF.');
    }
}
