<?php

namespace App\Services\BankStatements;

final readonly class BankStatementParseResult
{
    /**
     * @param  list<BankStatementTransactionCandidate>  $transactions
     * @param  list<array{code: string, page: int, row: int}>  $warnings
     */
    public function __construct(
        public string $parserVersion,
        public string $sourceSha256,
        public int $pageCount,
        public string $statementStart,
        public string $statementEnd,
        public string $currency,
        public ?string $openingBalance,
        public ?string $closingBalance,
        public ?string $totalDebits,
        public ?string $totalCredits,
        public array $transactions,
        public array $warnings,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'parser_version' => $this->parserVersion,
            'source_sha256' => $this->sourceSha256,
            'page_count' => $this->pageCount,
            'statement_start' => $this->statementStart,
            'statement_end' => $this->statementEnd,
            'currency' => $this->currency,
            'opening_balance' => $this->openingBalance,
            'closing_balance' => $this->closingBalance,
            'total_debits' => $this->totalDebits,
            'total_credits' => $this->totalCredits,
            'transactions' => array_map(static fn (BankStatementTransactionCandidate $row): array => $row->toArray(), $this->transactions),
            'warnings' => $this->warnings,
            'posting_performed' => false,
        ];
    }
}
