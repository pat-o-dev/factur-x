<?php

declare(strict_types=1);

namespace PatODev\FacturX\Pdf;

use PatODev\FacturX\Exception\NoEmbeddedXmlException;
use PatODev\FacturX\Exception\UnsupportedPdfException;
use PatODev\FacturX\Pdf\Internal\PdfDictionaryParser;

/**
 * Extracts the embedded Factur-X XML back out of a hybrid PDF — the inverse
 * of PdfA3Attacher. Only classic (non cross-reference-stream), unencrypted
 * PDFs are supported, same limitation class as PdfA3Attacher. Supports both
 * our own uncompressed embedded-file streams and third-party PDFs using a
 * single /FlateDecode filter; other filters, encrypted PDFs, indirect
 * /Length references, and inline (non-indirect) /EmbeddedFiles/Filespec
 * dictionaries are out of scope for v1.
 */
final class EmbeddedXmlExtractor
{
    public function __construct(
        private readonly PdfDictionaryParser $parser = new PdfDictionaryParser(),
    ) {
    }

    public function extract(string $pdfBytes): string
    {
        $section = $this->parser->locateLatestXrefSection($pdfBytes);
        $offsets = $section['offsets'];

        if (! preg_match('/\/Root\s+(\d+)\s+(\d+)\s+R/', $section['trailerDict'], $rootMatch)) {
            throw new UnsupportedPdfException('Could not find /Root in the PDF trailer.');
        }
        $rootObjNum = (int) $rootMatch[1];

        $rootDict = $this->extractDictionaryForObject($pdfBytes, $offsets, $rootObjNum);

        if (! str_contains($rootDict, '/Names') && ! str_contains($rootDict, '/AF')) {
            throw new NoEmbeddedXmlException(
                'This PDF does not embed a Factur-X XML attachment (no /Names/EmbeddedFiles '.
                'or /AF entry in the Catalog).',
            );
        }

        $filespecObjNum = $this->resolveFilespecObjectNumber($pdfBytes, $offsets, $rootDict);

        $filespecDict = $this->extractDictionaryForObject($pdfBytes, $offsets, $filespecObjNum);

        if (! preg_match('/\/EF\s*<<(.*?)>>/s', $filespecDict, $efMatch)) {
            throw new UnsupportedPdfException('Could not find the /EF entry in the Filespec dictionary.');
        }
        if (! preg_match('/\/F\s+(\d+)\s+\d+\s+R/', $efMatch[1], $fileMatch)
            && ! preg_match('/\/UF\s+(\d+)\s+\d+\s+R/', $efMatch[1], $fileMatch)) {
            throw new UnsupportedPdfException('Could not resolve the embedded file stream from /EF.');
        }
        $fileObjNum = (int) $fileMatch[1];

        return $this->extractStreamContent($pdfBytes, $offsets, $fileObjNum);
    }

    /** @param  array<int, int>  $offsets */
    private function resolveFilespecObjectNumber(string $pdf, array $offsets, string $rootDict): int
    {
        if (preg_match('/\/EmbeddedFiles\s+(\d+)\s+\d+\s+R/', $rootDict, $namesRefMatch)) {
            $namesDict = $this->extractDictionaryForObject($pdf, $offsets, (int) $namesRefMatch[1]);

            if (! preg_match('/\/Names\s*\[(.*?)\]/s', $namesDict, $arrayMatch)) {
                throw new UnsupportedPdfException('Could not find the /Names array in the EmbeddedFiles name tree.');
            }

            preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)\s+(\d+)\s+\d+\s+R/', $arrayMatch[1], $pairs, PREG_SET_ORDER);
            if ($pairs === []) {
                throw new UnsupportedPdfException('The /Names array is empty or uses an unsupported name encoding.');
            }

            foreach ($pairs as $pair) {
                if (str_ends_with(strtolower($this->unescapePdfString($pair[1])), '.xml')) {
                    return (int) $pair[2];
                }
            }

            return (int) $pairs[0][2];
        }

        if (preg_match('/\/AF\s*\[\s*(\d+)\s+\d+\s+R/', $rootDict, $afMatch)) {
            return (int) $afMatch[1];
        }

        throw new UnsupportedPdfException(
            'The Catalog declares /Names or /AF but neither could be resolved to an '.
            'embedded Filespec; inline (non-indirect) dictionaries are not supported in v1.',
        );
    }

    /** @param  array<int, int>  $offsets */
    private function extractDictionaryForObject(string $pdf, array $offsets, int $objNum): string
    {
        return $this->parser->extractDictionary($pdf, $this->objectDictStart($pdf, $offsets, $objNum));
    }

    /** @param  array<int, int>  $offsets */
    private function objectDictStart(string $pdf, array $offsets, int $objNum): int
    {
        if (! isset($offsets[$objNum])) {
            throw new UnsupportedPdfException("Could not resolve object {$objNum} 0 R: not found in the xref table.");
        }
        $offset = $offsets[$objNum];

        if (! preg_match('/^\s*'.$objNum.'\s+\d+\s+obj/', substr($pdf, $offset, 40), $objMatch)) {
            throw new UnsupportedPdfException("Object {$objNum} 0 R's header does not match the xref table entry.");
        }

        return $offset + strlen($objMatch[0]);
    }

    /** @param  array<int, int>  $offsets */
    private function extractStreamContent(string $pdf, array $offsets, int $objNum): string
    {
        $dictStart = $this->objectDictStart($pdf, $offsets, $objNum);
        $streamDict = $this->parser->extractDictionary($pdf, $dictStart);
        $dictEnd = strpos($pdf, '<<', $dictStart) + strlen($streamDict);

        $streamKeywordPos = strpos($pdf, 'stream', $dictEnd);
        if ($streamKeywordPos === false) {
            throw new UnsupportedPdfException("Object {$objNum} 0 R has no \"stream\" keyword.");
        }
        $cursor = $streamKeywordPos + strlen('stream');

        if (substr($pdf, $cursor, 2) === "\r\n") {
            $cursor += 2;
        } elseif (substr($pdf, $cursor, 1) === "\n") {
            $cursor += 1;
        } else {
            throw new UnsupportedPdfException('Malformed stream: no EOL after the "stream" keyword.');
        }

        if (preg_match('/\/Length\s+\d+\s+\d+\s+R/', $streamDict)) {
            throw new UnsupportedPdfException('Indirect /Length references are not supported in v1.');
        }

        if (preg_match('/\/Length\s+(\d+)/', $streamDict, $lengthMatch)) {
            $raw = substr($pdf, $cursor, (int) $lengthMatch[1]);
        } else {
            $endPos = strpos($pdf, 'endstream', $cursor);
            if ($endPos === false) {
                throw new UnsupportedPdfException("Object {$objNum} 0 R has no \"endstream\" keyword.");
            }
            $raw = rtrim(substr($pdf, $cursor, $endPos - $cursor), "\r\n");
        }

        if (preg_match('/\/Filter\s*\[?\s*\/(\w+)/', $streamDict, $filterMatch)) {
            if ($filterMatch[1] !== 'FlateDecode') {
                throw new UnsupportedPdfException("Unsupported embedded-file stream filter: /{$filterMatch[1]}.");
            }

            $decoded = @zlib_decode($raw);
            if ($decoded === false) {
                throw new UnsupportedPdfException('Could not inflate the /FlateDecode-compressed embedded file stream.');
            }

            return $decoded;
        }

        return $raw;
    }

    private function unescapePdfString(string $value): string
    {
        return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $value);
    }
}
