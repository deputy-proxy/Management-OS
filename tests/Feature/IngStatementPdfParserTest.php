<?php

namespace Tests\Feature;

use App\Services\BankStatements\BankStatementParseException;
use App\Services\BankStatements\IngStatementPdfParser;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class IngStatementPdfParserTest extends TestCase
{
    public function test_parser_returns_typed_candidates_and_reconciled_preview_without_posting(): void
    {
        $result = (new IngStatementPdfParser(new Parser))
            ->parse(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf'));

        $this->assertSame('ing-pdf-1.0.0', $result->parserVersion);
        $this->assertSame('2026-09-01', $result->statementStart);
        $this->assertSame('2026-09-30', $result->statementEnd);
        $this->assertSame('RON', $result->currency);
        $this->assertSame('1000.00', $result->openingBalance);
        $this->assertSame('1076.45', $result->closingBalance);
        $this->assertSame('-123.55', $result->totalDebits);
        $this->assertSame('200.00', $result->totalCredits);
        $this->assertCount(3, $result->transactions);
        $this->assertSame('credit', $result->transactions[0]->direction);
        $this->assertSame('200.00', $result->transactions[0]->amount);
        $this->assertSame('51943', $result->transactions[0]->operationReference);
        $this->assertSame('1200.00', $result->transactions[0]->balanceAfter);
        $this->assertSame('debit', $result->transactions[1]->direction);
        $this->assertSame('123.45', $result->transactions[1]->amount);
        $this->assertSame('1076.55', $result->transactions[1]->balanceAfter);
        $this->assertStringContainsString('Amount: 10.00 USD', $result->transactions[1]->description);
        $this->assertSame(2, $result->transactions[2]->sourcePage);
        $this->assertSame('debit', $result->transactions[2]->direction);
        $this->assertSame('0.10', $result->transactions[2]->amount);
        $this->assertSame('1076.45', $result->transactions[2]->balanceAfter);
        $this->assertSame([], $result->warnings);
        $this->assertFalse($result->toArray()['posting_performed']);
    }

    public function test_parser_rejects_non_pdf_input_with_safe_error_code(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ing-not-pdf-');
        self::assertNotFalse($path);
        file_put_contents($path, 'not a PDF');

        try {
            (new IngStatementPdfParser(new Parser))->parse($path);
            $this->fail('Expected invalid PDF to be rejected.');
        } catch (BankStatementParseException $exception) {
            $this->assertSame('not_a_pdf', $exception->errorCode);
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_rejects_another_bank_layout(): void
    {
        $this->expectException(BankStatementParseException::class);
        $this->expectExceptionMessage('does not match the documented ING');
        (new IngStatementPdfParser(new Parser))
            ->parse(base_path('tests/Fixtures/BankStatements/BankA/bcr-ron-layout-synthetic.pdf'));
    }

    public function test_parser_reports_a_running_balance_mismatch_without_exposing_values(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $changed = str_replace('1,200.00', '1,201.00', $source);
        $path = tempnam(sys_get_temp_dir(), 'ing-balance-');
        self::assertNotFalse($path);
        file_put_contents($path, $changed);

        try {
            $result = (new IngStatementPdfParser(new Parser))->parse($path);
            $codes = array_column($result->warnings, 'code');
            $this->assertContains('transaction_balance_mismatch', $codes);
            $this->assertStringNotContainsString('1,201.00', json_encode($result->warnings, JSON_THROW_ON_ERROR));
        } finally {
            @unlink($path);
        }
    }

    public function test_parser_rejects_a_malformed_transaction_date(): void
    {
        $source = file_get_contents(base_path('tests/Fixtures/BankStatements/ING/ing-ron-layout-synthetic.pdf'));
        self::assertIsString($source);
        $changed = str_replace('01.09.2026 Synthetic payer', '32.09.2026 Synthetic payer', $source);
        $path = tempnam(sys_get_temp_dir(), 'ing-date-');
        self::assertNotFalse($path);
        file_put_contents($path, $changed);

        try {
            (new IngStatementPdfParser(new Parser))->parse($path);
            $this->fail('Expected invalid transaction date to be rejected.');
        } catch (BankStatementParseException $exception) {
            $this->assertSame('invalid_date', $exception->errorCode);
        } finally {
            @unlink($path);
        }
    }
}
