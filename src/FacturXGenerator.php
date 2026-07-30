<?php

declare(strict_types=1);

namespace PatODev\FacturX;

use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Pdf\PdfA3Attacher;
use PatODev\FacturX\Pdf\XmpMetadataBuilder;
use PatODev\FacturX\Xml\CiiInvoiceWriter;

/**
 * Framework-agnostic entry point: Invoice -> factur-x.xml, and
 * (Invoice, base PDF bytes) -> hybrid Factur-X PDF/A-3 bytes.
 */
final class FacturXGenerator
{
    public function __construct(
        private readonly CiiInvoiceWriter $xmlWriter = new CiiInvoiceWriter(),
        private readonly PdfA3Attacher $pdfAttacher = new PdfA3Attacher(),
        private readonly XmpMetadataBuilder $xmpBuilder = new XmpMetadataBuilder(),
    ) {
    }

    public function generateXml(Invoice $invoice, float $prepaidAmount = 0.0, float $roundingAmount = 0.0): string
    {
        return $this->xmlWriter->toXmlString($invoice, $prepaidAmount, $roundingAmount);
    }

    /**
     * @param  string  $basePdf  Bytes of an existing, visually complete PDF invoice
     *                           (ideally already PDF/A compliant, e.g. produced by
     *                           mPDF/TCPDF in PDF/A mode). See PdfA3Attacher for
     *                           the supported PDF subset.
     */
    public function generateHybridPdf(
        Invoice $invoice,
        string $basePdf,
        float $prepaidAmount = 0.0,
        float $roundingAmount = 0.0,
    ): string {
        $xml = $this->generateXml($invoice, $prepaidAmount, $roundingAmount);
        $xmp = $this->xmpBuilder->build(
            documentTitle: sprintf('Facture %s', $invoice->number),
            attachmentFileName: $invoice->profile->facturXAttachmentName(),
        );

        return $this->pdfAttacher->attach(
            pdfBytes: $basePdf,
            xmlContent: $xml,
            xmpPacket: $xmp,
            xmlFileName: $invoice->profile->facturXAttachmentName(),
        );
    }
}
