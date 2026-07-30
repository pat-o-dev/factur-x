<?php

declare(strict_types=1);

namespace PatODev\FacturX\Validation;

/** One row of an InvoiceValidator report: a single business-rule check outcome. */
final readonly class RuleResult
{
    public function __construct(
        public string $code,
        public string $description,
        public bool $passed,
        public ?string $failureDetail = null,
    ) {
    }
}
