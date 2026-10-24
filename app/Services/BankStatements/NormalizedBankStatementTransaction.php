<?php

namespace App\Services\BankStatements;

final readonly class NormalizedBankStatementTransaction
{
    /**
     * @param  list<string>  $reviewReasons
     */
    public function __construct(
        public string $transactionDate,
        public ?string $valueDate,
        public string $direction,
        public string $amount,
        public string $currency,
        public ?string $transactionType,
        public string $classificationStatus,
        public string $description,
        public ?string $operationReference,
        public ?string $documentReference,
        public string $sourceSha256,
        public string $parserVersion,
        public int $sourcePage,
        public int $sourceRow,
        public ?int $relatedSourcePage,
        public ?int $relatedSourceRow,
        public array $reviewReasons,
        public ?string $balanceAfter = null,
        public ?string $counterparty = null,
    ) {}

    /** @return array<string, int|string|bool|null|list<string>> */
    public function toArray(): array
    {
        return [
            'transaction_date' => $this->transactionDate,
            'value_date' => $this->valueDate,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_type' => $this->transactionType,
            'classification_status' => $this->classificationStatus,
            'description' => $this->description,
            'operation_reference' => $this->operationReference,
            'document_reference' => $this->documentReference,
            'source_sha256' => $this->sourceSha256,
            'parser_version' => $this->parserVersion,
            'source_page' => $this->sourcePage,
            'source_row' => $this->sourceRow,
            'related_source_page' => $this->relatedSourcePage,
            'related_source_row' => $this->relatedSourceRow,
            'balance_after' => $this->balanceAfter,
            'counterparty' => $this->counterparty,
            'requires_review' => $this->reviewReasons !== [],
            'review_reasons' => $this->reviewReasons,
        ];
    }
}
