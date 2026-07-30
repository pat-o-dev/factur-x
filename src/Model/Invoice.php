<?php

declare(strict_types=1);

namespace PatODev\FacturX\Model;

use DateTimeImmutable;
use PatODev\FacturX\Builder\InvoiceBuilder;
use PatODev\FacturX\Enum\CurrencyCode;
use PatODev\FacturX\Enum\FacturXProfile;
use PatODev\FacturX\Enum\InvoiceTypeCode;

/**
 * Aggregate root mapping the EN 16931 profile of XP Z12-012 §4.3.1.
 * Built with a fluent, mutable API for developer ergonomics; call
 * InvoiceCalculator::totals() / ::vatBreakdown() to get computed figures.
 */
final class Invoice
{
    /** @var InvoiceLine[] */
    private array $lines = [];

    /** @var AllowanceCharge[] */
    private array $documentAllowances = [];

    /** @var AllowanceCharge[] */
    private array $documentCharges = [];

    /** @var Note[] */
    private array $notes = [];

    public function __construct(
        public readonly string $number,
        public readonly DateTimeImmutable $issueDate,
        public readonly InvoiceTypeCode $typeCode,
        public readonly Party $seller,
        public readonly Party $buyer,
        public readonly CurrencyCode $currencyCode = CurrencyCode::Euro,
        public readonly ?DateTimeImmutable $dueDate = null,
        public readonly ?string $buyerReference = null,
        public readonly ?string $purchaseOrderReference = null,
        public readonly ?string $contractReference = null,
        public readonly ?string $precedingInvoiceNumber = null,
        public readonly ?DateTimeImmutable $precedingInvoiceDate = null,
        public readonly ?string $paymentTermsText = null,
        public readonly ?PaymentMeans $paymentMeans = null,
        public readonly ?Party $deliveryParty = null,
        public readonly ?DateTimeImmutable $deliveryDate = null,
        public readonly FacturXProfile $profile = FacturXProfile::En16931,
    ) {
    }

    public static function builder(string $number): InvoiceBuilder
    {
        return InvoiceBuilder::make($number);
    }

    public function addLine(InvoiceLine $line): self
    {
        $this->lines[] = $line;

        return $this;
    }

    public function addDocumentAllowance(AllowanceCharge $allowance): self
    {
        $this->documentAllowances[] = $allowance;

        return $this;
    }

    public function addDocumentCharge(AllowanceCharge $charge): self
    {
        $this->documentCharges[] = $charge;

        return $this;
    }

    public function addNote(Note $note): self
    {
        $this->notes[] = $note;

        return $this;
    }

    /** @return InvoiceLine[] */
    public function lines(): array
    {
        return $this->lines;
    }

    /** @return AllowanceCharge[] */
    public function documentAllowances(): array
    {
        return $this->documentAllowances;
    }

    /** @return AllowanceCharge[] */
    public function documentCharges(): array
    {
        return $this->documentCharges;
    }

    /** @return Note[] */
    public function notes(): array
    {
        return $this->notes;
    }
}
