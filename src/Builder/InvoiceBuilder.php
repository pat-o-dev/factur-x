<?php

declare(strict_types=1);

namespace PatODev\FacturX\Builder;

use DateTimeImmutable;
use PatODev\FacturX\Enum\CurrencyCode;
use PatODev\FacturX\Enum\FacturXProfile;
use PatODev\FacturX\Enum\InvoiceTypeCode;
use PatODev\FacturX\Enum\NoteSubjectCode;
use PatODev\FacturX\Enum\UnitOfMeasureCode;
use PatODev\FacturX\Enum\VatExemptionReasonCode;
use PatODev\FacturX\Model\AllowanceCharge;
use PatODev\FacturX\Model\Invoice;
use PatODev\FacturX\Model\InvoiceLine;
use PatODev\FacturX\Model\Note;
use PatODev\FacturX\Model\Party;
use PatODev\FacturX\Model\PaymentMeans;

/**
 * Fluent alternative to Invoice's constructor-with-named-args. Produces a
 * plain Invoice — no behaviour of its own beyond assembly; the resulting
 * object still goes through InvoiceCalculator / CiiInvoiceWriter as usual.
 */
final class InvoiceBuilder
{
    private DateTimeImmutable $issueDate;

    private InvoiceTypeCode $typeCode = InvoiceTypeCode::CommercialInvoice;

    private CurrencyCode $currencyCode = CurrencyCode::Euro;

    private ?DateTimeImmutable $dueDate = null;

    private ?int $dueInDaysOffset = null;

    private ?string $paymentTermsText = null;

    private ?string $buyerReference = null;

    private ?string $purchaseOrderReference = null;

    private ?string $contractReference = null;

    private ?string $precedingInvoiceNumber = null;

    private ?DateTimeImmutable $precedingInvoiceDate = null;

    private ?PaymentMeans $paymentMeans = null;

    private ?Party $seller = null;

    private ?Party $buyer = null;

    private ?Party $deliveryParty = null;

    private ?DateTimeImmutable $deliveryDate = null;

    private FacturXProfile $profile = FacturXProfile::En16931;

    /** @var InvoiceLine[] */
    private array $lines = [];

    /** @var AllowanceCharge[] */
    private array $documentAllowances = [];

    /** @var AllowanceCharge[] */
    private array $documentCharges = [];

    /** @var Note[] */
    private array $notes = [];

    private function __construct(private readonly string $number)
    {
        $this->issueDate = new DateTimeImmutable();
    }

    public static function make(string $number): self
    {
        return new self($number);
    }

    public function issueDate(DateTimeImmutable $date): self
    {
        $this->issueDate = $date;

        return $this;
    }

    public function type(InvoiceTypeCode $type): self
    {
        $this->typeCode = $type;

        return $this;
    }

    public function currency(CurrencyCode $currencyCode): self
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function dueDate(DateTimeImmutable $date): self
    {
        $this->dueDate = $date;
        $this->dueInDaysOffset = null;

        return $this;
    }

    /** Sugar for dueDate(issueDate + N days); resolved from the final issueDate at build() time. */
    public function dueInDays(int $days): self
    {
        $this->dueInDaysOffset = $days;
        $this->dueDate = null;

        return $this;
    }

    public function paymentTerms(string $text): self
    {
        $this->paymentTermsText = $text;

        return $this;
    }

    public function buyerReference(string $reference): self
    {
        $this->buyerReference = $reference;

        return $this;
    }

    public function purchaseOrderReference(string $reference): self
    {
        $this->purchaseOrderReference = $reference;

        return $this;
    }

    public function contractReference(string $reference): self
    {
        $this->contractReference = $reference;

        return $this;
    }

    public function precedingInvoice(string $number, ?DateTimeImmutable $date = null): self
    {
        $this->precedingInvoiceNumber = $number;
        $this->precedingInvoiceDate = $date;

        return $this;
    }

    public function paymentMeans(PaymentMeans $paymentMeans): self
    {
        $this->paymentMeans = $paymentMeans;

        return $this;
    }

    public function seller(Party $seller): self
    {
        $this->seller = $seller;

        return $this;
    }

    public function buyer(Party $buyer): self
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function deliveryParty(Party $party, ?DateTimeImmutable $date = null): self
    {
        $this->deliveryParty = $party;
        $this->deliveryDate = $date;

        return $this;
    }

    public function profile(FacturXProfile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function addLine(InvoiceLine $line): self
    {
        $this->lines[] = $line;

        return $this;
    }

    /**
     * Convenience mirror of InvoiceLine's constructor, to avoid a nested `new`
     * for the common case: standard-rate VAT, auto-numbered lines (1, 2, 3...).
     */
    public function line(
        string $itemName,
        float $quantity,
        UnitOfMeasureCode $unitCode,
        float $netUnitPrice,
        ?string $lineId = null,
        \PatODev\FacturX\Enum\VatCategory $vatCategory = \PatODev\FacturX\Enum\VatCategory::Standard,
        ?float $vatRate = null,
        ?string $itemDescription = null,
        ?float $grossUnitPrice = null,
        ?string $vatExemptionReasonText = null,
        ?VatExemptionReasonCode $vatExemptionReasonCode = null,
        ?string $buyerOrderLineReference = null,
        array $allowances = [],
        array $charges = [],
    ): self {
        return $this->addLine(new InvoiceLine(
            lineId: $lineId ?? (string) (count($this->lines) + 1),
            itemName: $itemName,
            quantity: $quantity,
            unitCode: $unitCode,
            netUnitPrice: $netUnitPrice,
            vatCategory: $vatCategory,
            vatRate: $vatRate,
            itemDescription: $itemDescription,
            grossUnitPrice: $grossUnitPrice,
            vatExemptionReasonText: $vatExemptionReasonText,
            vatExemptionReasonCode: $vatExemptionReasonCode,
            buyerOrderLineReference: $buyerOrderLineReference,
            allowances: $allowances,
            charges: $charges,
        ));
    }

    public function allowance(AllowanceCharge $allowance): self
    {
        $this->documentAllowances[] = $allowance;

        return $this;
    }

    public function charge(AllowanceCharge $charge): self
    {
        $this->documentCharges[] = $charge;

        return $this;
    }

    public function note(string $content, ?NoteSubjectCode $subjectCode = null): self
    {
        $this->notes[] = new Note($content, $subjectCode);

        return $this;
    }

    public function build(): Invoice
    {
        if ($this->seller === null) {
            throw new \LogicException('Invoice is missing a seller; call ->seller(...) before ->build().');
        }
        if ($this->buyer === null) {
            throw new \LogicException('Invoice is missing a buyer; call ->buyer(...) before ->build().');
        }

        $dueDate = $this->dueDate ?? ($this->dueInDaysOffset !== null
            ? $this->issueDate->modify("+{$this->dueInDaysOffset} days")
            : null);

        $invoice = new Invoice(
            number: $this->number,
            issueDate: $this->issueDate,
            typeCode: $this->typeCode,
            seller: $this->seller,
            buyer: $this->buyer,
            currencyCode: $this->currencyCode,
            dueDate: $dueDate,
            buyerReference: $this->buyerReference,
            purchaseOrderReference: $this->purchaseOrderReference,
            contractReference: $this->contractReference,
            precedingInvoiceNumber: $this->precedingInvoiceNumber,
            precedingInvoiceDate: $this->precedingInvoiceDate,
            paymentTermsText: $this->paymentTermsText,
            paymentMeans: $this->paymentMeans,
            deliveryParty: $this->deliveryParty,
            deliveryDate: $this->deliveryDate,
            profile: $this->profile,
        );

        foreach ($this->lines as $line) {
            $invoice->addLine($line);
        }
        foreach ($this->documentAllowances as $allowance) {
            $invoice->addDocumentAllowance($allowance);
        }
        foreach ($this->documentCharges as $charge) {
            $invoice->addDocumentCharge($charge);
        }
        foreach ($this->notes as $note) {
            $invoice->addNote($note);
        }

        return $invoice;
    }
}
