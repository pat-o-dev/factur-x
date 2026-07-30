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

    private function buildMinimalPdf(): string
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

        $add(1, '<< /Type /Catalog /Pages 2 0 R >>');
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
}
