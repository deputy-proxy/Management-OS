<?php

namespace Tests\Feature;

use App\Services\BankStatements\BankStatementParseResult;
use App\Services\BankStatements\BankStatementTransactionCandidate;
use App\Services\BankStatements\BcrStatementPdfParser;
use App\Services\BankStatements\BcrStatementTransactionNormalizer;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class BcrStatementTransactionNormalizerTest extends TestCase
{
    public function test_normalizer_maps_direction_amount_currency_references_and_explicit_fee_link(): void
    {
        $parsed = (new BcrStatementPdfParser(new Parser))
            ->parse(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));

        $normalized = (new BcrStatementTransactionNormalizer)->normalize($parsed);

        $this->assertCount(3, $normalized->transactions);
        $this->assertSame('money_in', $normalized->transactions[0]->direction);
        $this->assertSame('10.00', $normalized->transactions[0]->amount);
        $this->assertSame('RON', $normalized->transactions[0]->currency);
        $this->assertSame('2026-09-03', $normalized->transactions[0]->transactionDate);
        $this->assertSame('2026-09-03', $normalized->transactions[0]->valueDate);
        $this->assertSame('SYN-OP-001', $normalized->transactions[0]->operationReference);
        $this->assertSame('business_type_unresolved', $normalized->transactions[0]->reviewReasons[0]);

        $principal = $normalized->transactions[1];
        $fee = $normalized->transactions[2];
        $this->assertSame('money_out', $principal->direction);
        $this->assertSame('25.00', $principal->amount);
        $this->assertNull($principal->transactionType);
        $this->assertSame('fee', $fee->transactionType);
        $this->assertSame('0.50', $fee->amount);
        $this->assertSame($principal->sourcePage, $fee->relatedSourcePage);
        $this->assertSame($principal->sourceRow, $fee->relatedSourceRow);
        $this->assertSame('SYN-OP-002', $fee->operationReference);
        $this->assertSame('bcr-pdf-1.0.0', $fee->parserVersion);
        $this->assertSame($parsed->sourceSha256, $fee->sourceSha256);
        $this->assertTrue($normalized->requiresReview());
        $this->assertFalse($normalized->toArray()['posting_performed']);
    }

    public function test_normalizer_does_not_infer_fx_or_refund_semantics(): void
    {
        $candidate = new BankStatementTransactionCandidate(
            '2026-09-24', '13:30', '2026-09-23',
            'Tranzactie comerciant: 174 EUR; Suma in valuta contului 922.69 RON; Rata de schimb',
            'SYN-FX-001', null, 'debit', '922.69', 'RON', 7, 20,
        );
        $refund = new BankStatementTransactionCandidate(
            '2026-09-23', '13:18', '2026-09-23', 'Retur taxa - SYNTHETIC',
            'SYN-REF-001', null, 'debit', '200.00', 'RON', 6, 12,
        );
        $parsed = new BankStatementParseResult(
            'bcr-pdf-1.0.0', 'synthetic-source-hash', 9, '2026-09-01', '2026-09-30', 'RON',
            '1000.00', '77.31', '922.69', '0.00', [$candidate, $refund], [],
        );

        $normalized = (new BcrStatementTransactionNormalizer)->normalize($parsed);
        $fx = $normalized->transactions[0];
        $possibleRefund = $normalized->transactions[1];

        $this->assertSame('money_out', $fx->direction);
        $this->assertSame('922.69', $fx->amount);
        $this->assertSame('RON', $fx->currency);
        $this->assertContains('foreign_currency_semantics_unverified', $fx->reviewReasons);
        $this->assertNull($fx->transactionType);
        $this->assertContains('possible_refund_or_reversal', $possibleRefund->reviewReasons);
        $this->assertNull($possibleRefund->transactionType);
        $this->assertTrue($normalized->requiresReview());
    }

    public function test_normalizer_propagates_parser_warnings_and_does_not_mark_preview_approved(): void
    {
        $candidate = new BankStatementTransactionCandidate(
            '2026-09-03', '10:40', null, 'Incasare Comision de administrare pachet',
            null, null, 'debit', '59.00', 'RON', 2, 14,
        );
        $parsed = new BankStatementParseResult(
            'bcr-pdf-1.0.0', 'synthetic-source-hash', 2, '2026-09-01', '2026-09-30', 'RON',
            '100.00', '41.00', '59.00', '0.00', [$candidate],
            [['code' => 'transaction_outside_statement_period', 'page' => 2, 'row' => 14]],
        );

        $normalized = (new BcrStatementTransactionNormalizer)->normalize($parsed);

        $this->assertSame('fee', $normalized->transactions[0]->transactionType);
        $this->assertSame('money_out', $normalized->transactions[0]->direction);
        $this->assertSame('transaction_outside_statement_period', $normalized->warnings[0]['code']);
        $this->assertTrue($normalized->requiresReview());
        $this->assertFalse($normalized->toArray()['posting_performed']);
        $this->assertArrayNotHasKey('ready_for_approval', $normalized->toArray());
    }
}
