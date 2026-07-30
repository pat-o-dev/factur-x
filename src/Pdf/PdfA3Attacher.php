<?php

declare(strict_types=1);

namespace PatODev\FacturX\Pdf;

use PatODev\FacturX\Pdf\Internal\ClassicXrefReader;
use PatODev\FacturX\Pdf\Internal\PdfTrailer;

/**
 * Turns a plain PDF into a Factur-X hybrid PDF by appending, as a classic
 * PDF *incremental update* (the same mechanism used for digital signatures
 * and form fill-in), everything Factur-X needs at the PDF-container level:
 *
 *  - the XML invoice as an /EmbeddedFile stream, referenced from the
 *    document Catalog's /Names /EmbeddedFiles name tree and /AF array
 *    (PDF 2.0 "Associated Files", required for PDF/A-3 conformance readers
 *    to recognise it as the authoritative machine-readable counterpart);
 *  - an XMP metadata stream declaring the PDF/A-3 + Factur-X extension
 *    schema (see XmpMetadataBuilder).
 *
 * This class does NOT convert the visual PDF itself into full PDF/A-3
 * (ICC output intent, font embedding, colour space restrictions, ...): the
 * base PDF must already satisfy those requirements (mPDF and TCPDF both
 * ship a PDF/A mode for this). Only classic (non cross-reference-stream),
 * unencrypted PDFs are supported — see ClassicXrefReader.
 */
final class PdfA3Attacher
{
    public function __construct(
        private readonly ClassicXrefReader $xrefReader = new ClassicXrefReader(),
    ) {
    }

    public function attach(
        string $pdfBytes,
        string $xmlContent,
        string $xmpPacket,
        string $xmlFileName = 'factur-x.xml',
    ): string {
        $trailer = $this->xrefReader->read($pdfBytes);

        $fileObjNum = $trailer->size;
        $filespecObjNum = $trailer->size + 1;
        $namesObjNum = $trailer->size + 2;
        $isNewMetadataObject = $trailer->metadataObjectNumber === null;
        $metadataObjNum = $trailer->metadataObjectNumber ?? $trailer->size + 3;
        $newSize = $trailer->size + ($isNewMetadataObject ? 4 : 3);

        $appended = '';
        $cursor = strlen($pdfBytes);
        $offsets = [];

        [$appended, $cursor, $offsets[$fileObjNum]] = $this->appendObject(
            $appended,
            $cursor,
            $fileObjNum,
            $this->embeddedFileBody($xmlContent),
        );

        [$appended, $cursor, $offsets[$filespecObjNum]] = $this->appendObject(
            $appended,
            $cursor,
            $filespecObjNum,
            $this->filespecBody($xmlFileName, $fileObjNum),
        );

        [$appended, $cursor, $offsets[$namesObjNum]] = $this->appendObject(
            $appended,
            $cursor,
            $namesObjNum,
            $this->namesBody($xmlFileName, $filespecObjNum),
        );

        [$appended, $cursor, $offsets[$metadataObjNum]] = $this->appendObject(
            $appended,
            $cursor,
            $metadataObjNum,
            $this->metadataBody($xmpPacket),
        );

        [$appended, $cursor, $offsets[$trailer->rootObjectNumber]] = $this->appendObject(
            $appended,
            $cursor,
            $trailer->rootObjectNumber,
            $this->updatedRootBody($trailer, $namesObjNum, $filespecObjNum, $metadataObjNum, $isNewMetadataObject),
        );

        ksort($offsets);

        $xrefStart = $cursor;
        $appended .= $this->buildXrefSection($offsets);
        $appended .= "trailer\n<< /Size {$newSize} /Root {$trailer->rootObjectNumber} 0 R /Prev {$trailer->xrefOffset} >>\n";
        $appended .= "startxref\n{$xrefStart}\n%%EOF";

        return $pdfBytes.$appended;
    }

    /** @return array{0: string, 1: int, 2: int} [appended text so far, new cursor, offset of this object] */
    private function appendObject(string $appended, int $cursor, int $objNum, string $body): array
    {
        $offset = $cursor;
        $text = "{$objNum} 0 obj\n{$body}\nendobj\n";

        return [$appended.$text, $cursor + strlen($text), $offset];
    }

    private function embeddedFileBody(string $xmlContent): string
    {
        $length = strlen($xmlContent);

        return "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size {$length} >> /Length {$length} >>\n"
            ."stream\n{$xmlContent}\nendstream";
    }

    private function filespecBody(string $fileName, int $fileObjNum): string
    {
        $name = $this->pdfString($fileName);

        return '<< /Type /Filespec /F ('.$name.') /UF ('.$name.') /AFRelationship /Data '
            .'/Desc (Factur-X machine-readable invoice) '
            ."/EF << /F {$fileObjNum} 0 R /UF {$fileObjNum} 0 R >> >>";
    }

    private function namesBody(string $fileName, int $filespecObjNum): string
    {
        $name = $this->pdfString($fileName);

        return "<< /Names [ ({$name}) {$filespecObjNum} 0 R ] >>";
    }

    private function metadataBody(string $xmpPacket): string
    {
        $length = strlen($xmpPacket);

        return "<< /Type /Metadata /Subtype /XML /Length {$length} >>\nstream\n{$xmpPacket}\nendstream";
    }

    private function updatedRootBody(
        PdfTrailer $trailer,
        int $namesObjNum,
        int $filespecObjNum,
        int $metadataObjNum,
        bool $isNewMetadataObject,
    ): string {
        $inner = trim(substr($trailer->rootDictionary, 2, -2));

        $inner .= " /Names << /EmbeddedFiles {$namesObjNum} 0 R >>"
            ." /AF [ {$filespecObjNum} 0 R ]";

        // If /Metadata already pointed at this object number, the Catalog's
        // existing reference stays valid as-is; only the object body changes.
        if ($isNewMetadataObject) {
            $inner .= " /Metadata {$metadataObjNum} 0 R";
        }

        return "<< {$inner} >>";
    }

    /** @param array<int, int> $offsets object number => byte offset, already ksort()-ed */
    private function buildXrefSection(array $offsets): string
    {
        $section = "xref\n";

        $runStart = null;
        $runEntries = [];

        $flush = function () use (&$section, &$runStart, &$runEntries): void {
            if ($runStart === null) {
                return;
            }
            $section .= $runStart.' '.count($runEntries)."\n";
            foreach ($runEntries as $offset) {
                $section .= sprintf("%010d %05d n \n", $offset, 0);
            }
            $runStart = null;
            $runEntries = [];
        };

        $previousObjNum = null;
        foreach ($offsets as $objNum => $offset) {
            if ($previousObjNum !== null && $objNum !== $previousObjNum + 1) {
                $flush();
            }
            if ($runStart === null) {
                $runStart = $objNum;
            }
            $runEntries[] = $offset;
            $previousObjNum = $objNum;
        }
        $flush();

        return $section;
    }

    private function pdfString(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
