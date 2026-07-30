<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Pdf;

use PatODev\FacturX\Exception\UnsupportedPdfException;
use PatODev\FacturX\Pdf\PdfA3Attacher;
use PHPUnit\Framework\TestCase;

final class PdfA3AttacherTest extends TestCase
{
    public function test_attaches_xml_and_xmp_to_a_minimal_classic_pdf(): void
    {
        $pdf = $this->buildMinimalPdf();
        $xml = '<?xml version="1.0"?><root>hello</root>';
        $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';

        $result = (new PdfA3Attacher())->attach($pdf, $xml, $xmp, 'factur-x.xml');

        self::assertStringStartsWith($pdf, $result);
        self::assertStringContainsString($xml, $result);
        self::assertStringContainsString($xmp, $result);
        self::assertStringContainsString('/Subtype /text#2Fxml', $result);
        self::assertStringContainsString('/AFRelationship /Data', $result);
        self::assertStringContainsString('/EmbeddedFiles', $result);

        self::assertMatchesRegularExpression('/\/Size 8 \/Root 1 0 R \/Prev \d+/', $result);

        preg_match('/startxref\s+(\d+)/', substr($result, (int) strrpos($result, 'startxref')), $m);
        $newXrefOffset = (int) $m[1];
        self::assertSame('xref', substr($result, $newXrefOffset, 4));
    }

    public function test_rejects_pdf_without_startxref(): void
    {
        $this->expectException(UnsupportedPdfException::class);

        (new PdfA3Attacher())->attach("%PDF-1.4\nnot a real pdf", '<xml/>', '<xmp/>');
    }

    /**
     * mPDF/TCPDF always emit a Catalog /Metadata entry in PDF/A mode
     * (required by the PDF/A spec itself), which is the primary intended
     * source for the base PDF — so it must be reused in place, not rejected.
     */
    public function test_replaces_an_existing_catalog_metadata_object_in_place(): void
    {
        $pdf = $this->buildPdfWithExistingMetadata();
        $xml = '<?xml version="1.0"?><root>hello</root>';
        $newXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">new</x:xmpmeta>';

        $result = (new PdfA3Attacher())->attach($pdf, $xml, $newXmp, 'factur-x.xml');

        self::assertStringContainsString($newXmp, $result);
        // 3 new objects (embedded file, filespec, names) + the reused /Metadata
        // object number 4 + the reused /Root object number 1: original /Size 5 -> 8.
        self::assertMatchesRegularExpression('/\/Size 8 \/Root 1 0 R \/Prev \d+/', $result);

        self::assertSame(2, substr_count($result, '4 0 obj'), 'the reused /Metadata object should appear twice (original + incremental update)');

        $lastMetadataObj = strrpos($result, '4 0 obj');
        self::assertStringContainsString($newXmp, substr($result, $lastMetadataObj));

        // The Catalog's /Metadata reference must not be duplicated.
        $lastRootObj = strrpos($result, '1 0 obj');
        self::assertSame(1, substr_count(substr($result, $lastRootObj), '/Metadata'));
    }

    public function test_rejects_pdf_whose_catalog_already_declares_names(): void
    {
        $pdf = $this->buildMinimalPdf(catalogExtra: ' /Names << /EmbeddedFiles 5 0 R >>');

        $this->expectException(UnsupportedPdfException::class);

        (new PdfA3Attacher())->attach($pdf, '<xml/>', '<xmp/>');
    }

    public function test_rejects_pdf_whose_catalog_already_declares_af(): void
    {
        $pdf = $this->buildMinimalPdf(catalogExtra: ' /AF [5 0 R]');

        $this->expectException(UnsupportedPdfException::class);

        (new PdfA3Attacher())->attach($pdf, '<xml/>', '<xmp/>');
    }

    private function buildMinimalPdf(string $catalogExtra = ''): string
    {
        $header = "%PDF-1.4\n";
        $body = '';
        $cursor = strlen($header);
        $offsets = [];

        $add = function (int $num, string $content) use (&$body, &$cursor, &$offsets): void {
            $offsets[$num] = $cursor;
            $text = "{$num} 0 obj\n{$content}\nendobj\n";
            $body .= $text;
            $cursor += strlen($text);
        };

        $add(1, '<< /Type /Catalog /Pages 2 0 R'.$catalogExtra.' >>');
        $add(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $add(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>');

        $xrefOffset = $cursor;
        $xref = "xref\n0 4\n";
        $xref .= sprintf("%010d %05d f \n", 0, 65535);
        for ($i = 1; $i <= 3; $i++) {
            $xref .= sprintf("%010d %05d n \n", $offsets[$i], 0);
        }

        $trailer = "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $header.$body.$xref.$trailer;
    }

    private function buildPdfWithExistingMetadata(): string
    {
        $header = "%PDF-1.4\n";
        $body = '';
        $cursor = strlen($header);
        $offsets = [];

        $add = function (int $num, string $content) use (&$body, &$cursor, &$offsets): void {
            $offsets[$num] = $cursor;
            $text = "{$num} 0 obj\n{$content}\nendobj\n";
            $body .= $text;
            $cursor += strlen($text);
        };

        $oldXmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">old</x:xmpmeta>';

        $add(1, '<< /Type /Catalog /Pages 2 0 R /Metadata 4 0 R >>');
        $add(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $add(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>');
        $add(4, "<< /Type /Metadata /Subtype /XML /Length ".strlen($oldXmp)." >>\nstream\n{$oldXmp}\nendstream");

        $xrefOffset = $cursor;
        $xref = "xref\n0 5\n";
        $xref .= sprintf("%010d %05d f \n", 0, 65535);
        for ($i = 1; $i <= 4; $i++) {
            $xref .= sprintf("%010d %05d n \n", $offsets[$i], 0);
        }

        $trailer = "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $header.$body.$xref.$trailer;
    }
}
