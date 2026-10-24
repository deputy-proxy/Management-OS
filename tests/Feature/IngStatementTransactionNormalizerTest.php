<?php

namespace Tests\Feature;

use App\Services\BankStatements\BankStatementParseResult;
use App\Services\BankStatements\BankStatementTransactionCandidate;
use App\Services\BankStatements\IngStatementPdfParser;
use App\Services\BankStatements\IngStatementTransactionNormalizer;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class IngStatementTransactionNormalizerTest extends TestCase
{
    public function test_normalizer_maps_signed_direction_and_preserves_ing_row_provenance(): void
    {
        $parsed = (new IngStatementPdfParser(new Parser))
            ->parse(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf'));
        $normalized = (new IngStatementTransactionNormalizer)->normalize($parsed);

        $this->assertCount(3, $normalized->transactions);
        $credit = $normalized->transactions[0];
        $debit = $normalized->transactions[1];
        $fee = $normalized->transactions[2];

        $this->assertSame('money_in', $credit->direction);
        $this->assertSame('200.00', $credit->amount);
        $this->assertSame('RON', $credit->currency);
        $this->assertSame('51943', $credit->operationReference);
        $this->assertSame('SYN-ING-001', $credit->documentReference);
        $this->assertSame('1200.00', $credit->balanceAfter);
        $this->assertSame('Synthetic payer', $credit->counterparty);
        $this->assertSame(1, $credit->sourcePage);
        $this->assertSame('money_out', $debit->direction);
        $this->assertSame('123.45', $debit->amount);
        $this->assertContains('foreign_currency_semantics_unverified', $debit->reviewReasons);
        $this->assertSame('fee', $fee->transactionType);
        $this->assertSame('0.10', $fee->amount);
        $this->assertSame(2, $fee->sourcePage);
        $this->assertSame($parsed->sourceSha256, $fee->sourceSha256);
        $this->assertSame($parsed->parserVersion, $fee->parserVersion);
        $this->assertTrue($normalized->requiresReview());
        $this->assertFalse($normalized->toArray()['posting_performed']);
    }

    public function test_normalizer_flags_credit_line_fx_and_possible_reversal_semantics(): void
    {
        $rows = [
            new BankStatementTransactionCandidate('2026-09-03', null, null, 'Amount debited from credit line', '51950', null, 'credit', '500.00', 'RON', 2, 8, '500.00', '999918754972'),
            new BankStatementTransactionCandidate('2026-09-03', null, null, 'POS purchase Amount: 500.00 USD Settlement Amount: 431.89 EUR Rate: 5.4408', '51951', null, 'debit', '2349.83', 'RON', 2, 9, '-1849.83', 'Synthetic merchant'),
            new BankStatementTransactionCandidate('2026-09-04', null, null, 'Refund reversal returned transaction', '51952', null, 'credit', '20.00', 'RON', 3, 11, '-1829.83', null),
        ];
        $parsed = new BankStatementParseResult(
            'ing-pdf-1.0.0', 'synthetic-normalizer-hash', 3, '2026-09-01', '2026-09-30', 'RON',
            '0.00', '-1829.83', '-2349.83', '520.00', $rows, [],
        );

        $normalized = (new IngStatementTransactionNormalizer)->normalize($parsed);

        $this->assertSame('credit_line_movement', $normalized->transactions[0]->transactionType);
        $this->assertContains('credit_line_accounting_semantics_unverified', $normalized->transactions[0]->reviewReasons);
        $this->assertContains('foreign_currency_semantics_unverified', $normalized->transactions[1]->reviewReasons);
        $this->assertContains('possible_refund_or_reversal', $normalized->transactions[2]->reviewReasons);
        $this->assertTrue($normalized->requiresReview());
    }

    public function test_duplicate_bank_references_are_reported_without_deduplicating_rows(): void
    {
        $rows = [
            new BankStatementTransactionCandidate('2026-09-03', null, null, 'Incoming funds first row', '51943', null, 'credit', '10.00', 'RON', 1, 8, '10.00', null),
            new BankStatementTransactionCandidate('2026-09-03', null, null, 'Incoming funds second row', '51943', null, 'credit', '10.00', 'RON', 1, 9, '20.00', null),
        ];
        $parsed = new BankStatementParseResult(
            'ing-pdf-1.0.0', 'synthetic-duplicate-reference-hash', 1, '2026-09-01', '2026-09-30', 'RON',
            '0.00', '20.00', '0.00', '20.00', $rows, [],
        );

        $normalized = (new IngStatementTransactionNormalizer)->normalize($parsed);

        $this->assertCount(2, $normalized->transactions);
        $this->assertCount(2, array_filter($normalized->warnings, static fn (array $warning): bool => $warning['code'] === 'duplicate_source_reference'));
        $this->assertTrue($normalized->requiresReview());
    }
}
