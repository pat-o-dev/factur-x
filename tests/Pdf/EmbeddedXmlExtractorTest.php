<?php

declare(strict_types=1);

namespace PatODev\FacturX\Tests\Pdf;

use PatODev\FacturX\Exception\NoEmbeddedXmlException;
use PatODev\FacturX\Exception\UnsupportedPdfException;
use PatODev\FacturX\Pdf\EmbeddedXmlExtractor;
use PatODev\FacturX\Pdf\PdfA3Attacher;
use PHPUnit\Framework\TestCase;

final class EmbeddedXmlExtractorTest extends TestCase
{
    public function test_round_trips_xml_through_attach_then_extract(): void
    {
        $pdf = $this->buildMinimalPdf();
        $xml = '<?xml version="1.0"?><root>hello</root>';
        $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';

        $hybrid = (new PdfA3Attacher())->attach($pdf, $xml, $xmp, 'factur-x.xml');

        self::assertSame($xml, (new EmbeddedXmlExtractor())->extract($hybrid));
    }

    public function test_round_trips_when_base_pdf_already_has_metadata(): void
    {
        $pdf = $this->buildPdfWithExistingMetadata();
        $xml = '<?xml version="1.0"?><root>hello</root>';
        $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/">new</x:xmpmeta>';

        $hybrid = (new PdfA3Attacher())->attach($pdf, $xml, $xmp, 'factur-x.xml');

        self::assertSame($xml, (new EmbeddedXmlExtractor())->extract($hybrid));
    }

    public function test_extracts_a_flate_decoded_stream(): void
    {
        $xml = '<?xml version="1.0"?><root>compressed</root>';
        $compressed = zlib_encode($xml, ZLIB_ENCODING_DEFLATE);

        $pdf = $this->buildHybridPdfWithEmbeddedFile(
            "<< /Type /EmbeddedFile /Filter /FlateDecode /Length ".strlen($compressed)." >>\nstream\n{$compressed}\nendstream",
        );

        self::assertSame($xml, (new EmbeddedXmlExtractor())->extract($pdf));
    }

    public function test_throws_no_embedded_xml_exception_on_a_plain_pdf(): void
    {
        $this->expectException(NoEmbeddedXmlException::class);

        (new EmbeddedXmlExtractor())->extract($this->buildMinimalPdf());
    }

    public function test_throws_unsupported_pdf_exception_on_cross_reference_stream(): void
    {
        $this->expectException(UnsupportedPdfException::class);

        (new EmbeddedXmlExtractor())->extract("%PDF-1.4\nnot a real pdf");
    }

    private function buildHybridPdfWithEmbeddedFile(string $embeddedFileBody): string
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

        $add(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 5 0 R >> /AF [ 6 0 R ] >>');
        $add(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
        $add(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>');
        $add(4, $embeddedFileBody);
        $add(5, '<< /Names [ (factur-x.xml) 6 0 R ] >>');
        $add(6, '<< /Type /Filespec /F (factur-x.xml) /UF (factur-x.xml) /AFRelationship /Data /EF << /F 4 0 R /UF 4 0 R >> >>');

        $xrefOffset = $cursor;
        $xref = "xref\n0 7\n";
        $xref .= sprintf("%010d %05d f \n", 0, 65535);
        for ($i = 1; $i <= 6; $i++) {
            $xref .= sprintf("%010d %05d n \n", $offsets[$i], 0);
        }

        $trailer = "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $header.$body.$xref.$trailer;
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
