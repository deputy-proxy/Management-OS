<?php

namespace Tests\Feature;

use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\BcrStatementPdfParser;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class BcrStatementPdfParserTest extends TestCase
{
    public function test_parser_returns_candidates_and_reconciled_preview_without_posting(): void
    {
        $result = (new BcrStatementPdfParser(new Parser))
            ->parse(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));

        $this->assertSame('bcr-pdf-1.0.0', $result->parserVersion);
        $this->assertSame(2, $result->pageCount);
        $this->assertSame('2026-09-01', $result->statementStart);
        $this->assertSame('2026-09-30', $result->statementEnd);
        $this->assertSame('RON', $result->currency);
        $this->assertSame('100.00', $result->openingBalance);
        $this->assertSame('84.50', $result->closingBalance);
        $this->assertSame('25.50', $result->totalDebits);
        $this->assertSame('10.00', $result->totalCredits);
        $this->assertCount(3, $result->transactions);
        $this->assertSame([], $result->warnings);

        $credit = $result->transactions[0];
        $this->assertSame('credit', $credit->direction);
        $this->assertSame('10.00', $credit->amount);
        $this->assertSame('2026-09-03', $credit->valueDate);
        $this->assertSame('SYN-OP-001', $credit->operationReference);
        $this->assertSame('SYN-DOC-001', $credit->documentReference);
        $this->assertSame(1, $credit->sourcePage);
        $this->assertGreaterThan(0, $credit->sourceRow);

        $fee = $result->transactions[2];
        $this->assertSame('debit', $fee->direction);
        $this->assertSame('0.50', $fee->amount);
        $this->assertSame('SYN-DOC-002-FEE', $fee->documentReference);
        $this->assertFalse($result->toArray()['posting_performed']);
    }

    public function test_parser_rejects_non_pdf_input_with_safe_error_code(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bcr-invalid-');
        self::assertNotFalse($path);
        file_put_contents($path, 'not a statement');

        try {
            (new BcrStatementPdfParser(new Parser))->parse($path);
            $this->fail('Expected a safe parse exception.');
        } catch (BankStatementParseException $exception) {
            $this->assertSame('not_a_pdf', $exception->errorCode);
            $this->assertStringNotContainsString('not a statement', $exception->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_fails_closed_for_an_invalid_transaction_date(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $malformed = str_replace('03-09-2026 10:40', '32-09-2026 10:40', $source);
        $path = tempnam(sys_get_temp_dir(), 'bcr-date-');
        self::assertNotFalse($path);
        file_put_contents($path, $malformed);

        try {
            (new BcrStatementPdfParser(new Parser))->parse($path);
            $this->fail('Expected invalid transaction date to be rejected.');
        } catch (BankStatementParseException $exception) {
            $this->assertSame('invalid_date', $exception->errorCode);
            $this->assertStringContainsString('page 1, row', $exception->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_reports_ambiguous_amount_rows_as_warnings(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $malformed = str_replace('0,00  10,00', '0,0x  10,00', $source);
        $path = tempnam(sys_get_temp_dir(), 'bcr-amount-');
        self::assertNotFalse($path);
        file_put_contents($path, $malformed);

        try {
            $result = (new BcrStatementPdfParser(new Parser))->parse($path);
            $this->assertNotEmpty($result->warnings);
            $this->assertSame('transaction_amount_columns_unreadable', $result->warnings[0]['code']);
            $this->assertSame(1, $result->warnings[0]['page']);
            $this->assertFalse($result->toArray()['posting_performed']);
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_warns_when_statement_totals_do_not_match_rows(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $changed = str_replace('25,50 10,00', '26,50 10,00', $source);
        $path = tempnam(sys_get_temp_dir(), 'bcr-total-');
        self::assertNotFalse($path);
        file_put_contents($path, $changed);

        try {
            $result = (new BcrStatementPdfParser(new Parser))->parse($path);
            $codes = array_column($result->warnings, 'code');
            $this->assertContains('statement_totals_mismatch', $codes);
            $this->assertContains('statement_balance_mismatch', $codes);
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_handles_grouped_locale_amounts_multiline_text_and_duplicate_rows(): void
    {
        $path = base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-multiline-locale-synthetic.pdf');
        $result = (new BcrStatementPdfParser(new Parser))->parse($path);

        $this->assertSame('10000.00', $result->openingBalance);
        $this->assertSame('7530.88', $result->closingBalance);
        $this->assertSame('2469.12', $result->totalDebits);
        $this->assertSame('0.00', $result->totalCredits);
        $this->assertCount(2, $result->transactions);
        $this->assertSame('1234.56', $result->transactions[0]->amount);
        $this->assertSame('1234.56', $result->transactions[1]->amount);
        $this->assertStringContainsString('Detalii suplimentare', $result->transactions[0]->description);
        $this->assertSame('SYN-LOCALE-001', $result->transactions[0]->operationReference);
        $this->assertSame('SYN-LOCALE-001', $result->transactions[1]->operationReference);
        $this->assertNotSame($result->transactions[0]->sourceRow, $result->transactions[1]->sourceRow);
        $this->assertSame([], $result->warnings);
    }

    public function test_parser_rejects_pdf_without_selectable_text(): void
    {
        $this->expectException(BankStatementParseException::class);
        $this->expectExceptionMessage('No selectable text was found');
        try {
            (new BcrStatementPdfParser(new Parser))
                ->parse(base_path('tests/Fixtures/BankStatements/BankA/bcr-empty-text-synthetic.pdf'));
        } catch (BankStatementParseException $exception) {
            $this->assertSame('text_extraction_unavailable', $exception->errorCode);
            throw $exception;
        }
    }

    public function test_parser_rejects_an_unsupported_issuer_signature(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $issuer = 'BANCA COMERCIALA ROMANA S.A.';
        $replacement = str_repeat('X', strlen($issuer));
        $changed = str_replace($issuer, $replacement, $source);
        $path = tempnam(sys_get_temp_dir(), 'bcr-issuer-');
        self::assertNotFalse($path);
        file_put_contents($path, $changed);

        try {
            (new BcrStatementPdfParser(new Parser))->parse($path);
            $this->fail('Expected unsupported issuer to be rejected.');
        } catch (BankStatementParseException $exception) {
            $this->assertSame('unsupported_issuer', $exception->errorCode);
        } finally {
            @unlink($path);
        }
    }
}
