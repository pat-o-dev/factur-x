# pat-o-dev/factur-x

Framework-agnostic PHP library to generate **Factur-X** invoices for the French
e-invoicing reform (norme AFNOR XP Z12-012 / EN 16931), in pure PHP with no
Laravel/Symfony dependency. Pairs with [`pat-o-dev/factur-x-laravel`](../factur-x-laravel)
for Laravel projects; a Symfony bundle can reuse this package the same way.

Most existing open-source Factur-X/ZUGFeRD libraries are built and documented
primarily for the German ZUGFeRD use case. This package targets the **French
CIUS (profil EN 16931)** described in XP Z12-012 first, with the
`EXTENDED-CTC-FR` profile and the French `BR-FR-*` business rules planned for
a later release.

## What it does

1. **Builds the `factur-x.xml`** payload (UN/CEFACT CII D22B syntax, EN 16931
   profile) from a plain PHP `Invoice` object — header, parties, lines,
   allowances/charges, VAT breakdown and monetary totals.
2. **Turns an existing PDF into a Factur-X hybrid PDF** by appending, as a
   classic PDF *incremental update* (the same mechanism digital signatures
   use), the XML as an embedded file (`/AF`, `/Names /EmbeddedFiles`) plus the
   XMP metadata declaring the PDF/A-3 + Factur-X extension schema.

## What it does NOT do (yet)

- It does **not** render the human-readable PDF invoice itself, and it does
  **not** convert an arbitrary PDF into full PDF/A-3 conformance (ICC output
  intent, font embedding, colour space restrictions...). Feed it a PDF that
  is already PDF/A-compliant (mPDF and TCPDF both ship a PDF/A mode) — this
  library only adds the Factur-X-specific attachment + metadata layer.
- The PDF attacher only supports **classic (non cross-reference-stream),
  unencrypted PDFs** whose Catalog does not already declare `/Names` or `/AF`
  (an already-hybridized/attachment-bearing PDF). An existing `/Metadata`
  reference (which mPDF/TCPDF always emit in PDF/A mode) is reused in place
  rather than rejected. Anything unsupported raises `UnsupportedPdfException`
  rather than silently producing a broken file.
- The XML writer covers the fields most B2B invoices need (header
  identification, seller/buyer/delivery/payee party, key references, notes,
  billing period, lines with allowances/charges, VAT breakdown, one payment
  mean, totals) but not the full ~160 EN 16931 business terms yet, and not
  the `EXTENDED-CTC-FR` data (multi-seller invoices, sub-lines, ...).

## Usage

```php
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatCategory;
use PatODev\FacturX\FacturXGenerator;
use PatODev\FacturX\Model\{Address, Invoice, InvoiceLine, Party};

$seller = new Party(
    name: 'ACME Transport SARL',
    address: new Address(line1: '1 rue des Tests', city: 'Paris', postalCode: '75001', countryCode: 'FR'),
    legalRegistrationId: '123456789',
    vatNumber: 'FR12123456789',
);

$buyer = new Party(
    name: 'Client SAS',
    address: new Address(line1: '2 avenue du Test', city: 'Lyon', postalCode: '69001', countryCode: 'FR'),
    legalRegistrationId: '987654321',
);

$invoice = new Invoice(
    number: 'F20260001',
    issueDate: new DateTimeImmutable('2026-01-15'),
    typeCode: InvoiceTypeCode::CommercialInvoice,
    seller: $seller,
    buyer: $buyer,
);

$invoice->addLine(new InvoiceLine(
    lineId: '1',
    itemName: 'Prestation de transport',
    quantity: 2.0,
    unitCode: UnitOfMeasureCode::Piece,
    netUnitPrice: 100.0,
    vatCategory: VatCategory::Standard,
    vatRate: 20.0,
));

$generator = new FacturXGenerator();

// Standalone XML:
$xml = $generator->generateXml($invoice);

// Hybrid PDF, given a visual PDF you already generated (e.g. via mPDF):
$hybridPdf = $generator->generateHybridPdf($invoice, $basePdfBytes);

// Reverse: pull the embedded XML back out of an existing hybrid PDF...
$extractedXml = (new \PatODev\FacturX\Pdf\EmbeddedXmlExtractor())->extract($hybridPdf);

// ...and check it against a curated set of business rules:
$report = (new \PatODev\FacturX\Validation\InvoiceValidator())->validate($extractedXml);
$report->passed(); // bool
$report->failures(); // RuleResult[]
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Validating an existing invoice

`InvoiceValidator` checks a curated, hand-picked set of business rules —
not the full official EN 16931 schematron ruleset (see Roadmap):

- Structural: well-formed XML, correct root element/namespace, at least one
  line, a seller and a buyer party.
- `BR-CO-15`: tax basis total + tax total = grand total, plus a sub-rule
  (`BR-CO-15-currencyID`) that the tax total amount carries a `currencyID`
  attribute (required by most EN 16931 validators, e.g. KoSIT).
- `BR-CO-25`: if the amount due is positive, a due date or payment terms
  text must be present.
- `BR-CO-5-6`: every allowance/charge has a reason code or reason text.

Feed it XML extracted from a hybrid PDF via `EmbeddedXmlExtractor` (classic,
unencrypted PDFs only, same limitation class as `PdfA3Attacher`; both our
own uncompressed streams and third-party `/FlateDecode`-compressed ones are
supported) or raw `factur-x.xml` content directly.

## Roadmap

- `EXTENDED-CTC-FR` profile (multi-seller/bi-directional invoices, sub-lines).
- Full `BR-FR-*` business rule validation (growing `InvoiceValidator`'s rule set).
- Schematron-based conformance testing against the official EN 16931 rules.
- Cross-reference-stream PDF support in the attacher and extractor.
