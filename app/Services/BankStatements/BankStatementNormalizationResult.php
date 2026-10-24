<?php

namespace App\Services\BankStatements;

final readonly class BankStatementNormalizationResult
{
    /**
     * @param  list<NormalizedBankStatementTransaction>  $transactions
     * @param  list<array{code: string, page: int, row: int}>  $warnings
     */
    public function __construct(
        public string $sourceSha256,
        public string $parserVersion,
        public string $statementStart,
        public string $statementEnd,
        public string $currency,
        public array $transactions,
        public array $warnings,
    ) {}

    public function requiresReview(): bool
    {
        if ($this->warnings !== []) {
            return true;
        }

        foreach ($this->transactions as $transaction) {
            if ($transaction->reviewReasons !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_sha256' => $this->sourceSha256,
            'parser_version' => $this->parserVersion,
            'statement_start' => $this->statementStart,
            'statement_end' => $this->statementEnd,
            'currency' => $this->currency,
            'transactions' => array_map(static fn (NormalizedBankStatementTransaction $transaction): array => $transaction->toArray(), $this->transactions),
            'warnings' => $this->warnings,
            'requires_review' => $this->requiresReview(),
            'posting_performed' => false,
        ];
    }
}
