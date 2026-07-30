<?php

declare(strict_types=1);

namespace PatODev\FacturX\Pdf\Internal;

use PatODev\FacturX\Exception\UnsupportedPdfException;

/**
 * Low-level classic-PDF parsing primitives shared by ClassicXrefReader (used
 * by PdfA3Attacher to append an incremental update) and EmbeddedXmlExtractor
 * (used to read one back out) — both need to locate the latest xref/trailer
 * section and extract "<< ... >>" dictionaries, but disagree on what a valid
 * Catalog looks like, so that part stays in each caller.
 */
final class PdfDictionaryParser
{
    /** @return array{xrefOffset: int, trailerDict: string, offsets: array<int, int>} */
    public function locateLatestXrefSection(string $pdf): array
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

        return [
            'xrefOffset' => $xrefOffset,
            'trailerDict' => $trailerDict,
            'offsets' => $offsets,
        ];
    }

    /** @return array<int, int> object number => byte offset, for in-use ("n") entries only */
    public function parseXrefEntries(string $section): array
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
    public function extractDictionary(string $pdf, int $from): string
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
