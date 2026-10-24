<?php

namespace App\Services\BankStatements;

final readonly class BankStatementTransactionCandidate
{
    public function __construct(
        public string $operationDate,
        public string $operationTime,
        public ?string $valueDate,
        public string $description,
        public ?string $operationReference,
        public ?string $documentReference,
        public string $direction,
        public string $amount,
        public string $currency,
        public int $sourcePage,
        public int $sourceRow,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'operation_date' => $this->operationDate,
            'operation_time' => $this->operationTime,
            'value_date' => $this->valueDate,
            'description' => $this->description,
            'operation_reference' => $this->operationReference,
            'document_reference' => $this->documentReference,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'source_page' => $this->sourcePage,
            'source_row' => $this->sourceRow,
        ];
    }
}
