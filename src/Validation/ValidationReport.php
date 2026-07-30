<?php

declare(strict_types=1);

namespace PatODev\FacturX\Validation;

final readonly class ValidationReport
{
    /** @param  RuleResult[]  $results */
    public function __construct(
        public array $results,
    ) {
    }

    public function passed(): bool
    {
        return $this->failures() === [];
    }

    /** @return RuleResult[] */
    public function failures(): array
    {
        return array_values(array_filter($this->results, static fn (RuleResult $r): bool => ! $r->passed));
    }
}
