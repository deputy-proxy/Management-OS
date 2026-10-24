<?php

namespace App\Services\BankStatements;

use DateTimeImmutable;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

final class BcrStatementPdfParser
{
    public const VERSION = 'bcr-pdf-1.0.0';

    private const MAX_FILE_BYTES = 25_000_000;

    public function __construct(private readonly PdfParser $pdfParser) {}

    public function parse(string $path): BankStatementParseResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new BankStatementParseException('source_unavailable', 'Statement file is unavailable or unreadable.');
        }

        $size = filesize($path);
        if ($size === false || $size < 8 || $size > self::MAX_FILE_BYTES) {
            throw new BankStatementParseException('unsupported_file_size', 'Statement PDF must be between 8 bytes and 25 MB.');
        }

        $handle = fopen($path, 'rb');
        $signature = $handle === false ? false : fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new BankStatementParseException('not_a_pdf', 'Input is not a PDF file.');
        }

        try {
            $document = $this->pdfParser->parseFile($path);
            $pages = $document->getPages();
            $pageTexts = array_map(static fn ($page): string => $page->getText(), $pages);
        } catch (Throwable) {
            throw new BankStatementParseException('pdf_unreadable', 'PDF could not be read; confirm it is a supported, unencrypted PDF.');
        }

        $allText = implode("\n", $pageTexts);
        if (trim($allText) === '') {
            throw new BankStatementParseException('text_extraction_unavailable', 'No selectable text was found; scanned/OCR statements are not supported.');
        }

        if (! preg_match('/BANCA\s+COMERCIALA\s+ROMANA\s+S\.A\./iu', $allText)) {
            throw new BankStatementParseException('unsupported_issuer', 'PDF does not match the documented BCR statement issuer signature.');
        }

        if (! preg_match('/pe\s+perioada\s*:\s*(\d{2}-\d{2}-\d{4})\s*-\s*(\d{2}-\d{2}-\d{4})/iu', $allText, $period)) {
            throw new BankStatementParseException('statement_period_missing', 'Statement period is missing or does not match the documented date format.');
        }

        $statementStart = $this->parseDate($period[1], 1, 1);
        $statementEnd = $this->parseDate($period[2], 1, 1);
        if ($statementStart > $statementEnd) {
            throw new BankStatementParseException('statement_period_invalid', 'Statement period start is after its end.');
        }

        if (! preg_match('/Produse\s+in\s+valuta\s+(RON)\b/iu', $allText, $currencyMatch)) {
            throw new BankStatementParseException('unsupported_currency_marker', 'Account currency marker is missing or unsupported for this parser version.');
        }
        $currency = strtoupper($currencyMatch[1]);

        $openingBalance = $this->firstAmount('/Sold\s+contabil\s+initial\s*:\s*([\d.]+,\d{2})/iu', $allText);
        $closingBalance = $this->firstAmount('/Sold\s+contabil\s+final\s+la\s*:\s*\d{2}-\d{2}-\d{4}\s+([\d.]+,\d{2})/iu', $allText);
        $summaryTotals = $this->summaryTotals($allText);
        if ($openingBalance === null) {
            throw new BankStatementParseException('statement_opening_balance_missing', 'Statement opening balance could not be extracted.');
        }
        if ($closingBalance === null) {
            throw new BankStatementParseException('statement_closing_balance_missing', 'Statement closing balance could not be extracted.');
        }
        if ($summaryTotals['debits'] === null || $summaryTotals['credits'] === null) {
            throw new BankStatementParseException('statement_totals_missing', 'Statement debit/credit summary could not be extracted.');
        }

        $transactions = [];
        $warnings = [];
        $current = null;
        foreach ($pageTexts as $pageIndex => $pageText) {
            $lines = preg_split('/\R/u', $pageText) ?: [];
            foreach ($lines as $lineIndex => $rawLine) {
                $line = trim(preg_replace('/\s+/u', ' ', $rawLine) ?? '');
                if ($line === '') {
                    continue;
                }

                if (preg_match('/^(\d{2}-\d{2}-\d{4})\s+(\d{2}:\d{2})\s+(.+)$/u', $line, $match)) {
                    if ($current !== null) {
                        $transactions[] = $this->finishCandidate($current, $warnings);
                    }

                    if (! preg_match('/([\d.]+,\d{2})\s+([\d.]+,\d{2})\s*$/u', $match[3], $amountMatch, PREG_OFFSET_CAPTURE)) {
                        $warnings[] = ['code' => 'transaction_amount_columns_unreadable', 'page' => $pageIndex + 1, 'row' => $lineIndex + 1];
                        $current = null;

                        continue;
                    }

                    $prefix = trim(substr($match[3], 0, $amountMatch[0][1]));
                    $debit = $this->parseRomanianAmount($amountMatch[1][0], $pageIndex + 1, $lineIndex + 1);
                    $credit = $this->parseRomanianAmount($amountMatch[2][0], $pageIndex + 1, $lineIndex + 1);
                    if (($debit === '0.00') === ($credit === '0.00')) {
                        $warnings[] = ['code' => 'transaction_direction_ambiguous', 'page' => $pageIndex + 1, 'row' => $lineIndex + 1];
                        $current = null;

                        continue;
                    }

                    $operationDate = $this->parseDate($match[1], $pageIndex + 1, $lineIndex + 1);
                    $parsedTime = DateTimeImmutable::createFromFormat('!H:i', $match[2]);
                    $timeErrors = DateTimeImmutable::getLastErrors();
                    if ($parsedTime === false || ($timeErrors !== false && ($timeErrors['warning_count'] > 0 || $timeErrors['error_count'] > 0)) || $parsedTime->format('H:i') !== $match[2]) {
                        throw new BankStatementParseException('invalid_time', sprintf('Invalid HH:MM time at page %d, row %d.', $pageIndex + 1, $lineIndex + 1));
                    }

                    $current = [
                        'operation_date' => $operationDate,
                        'operation_time' => $match[2],
                        'value_date' => null,
                        'description' => $prefix,
                        'operation_reference' => $this->extractReference($prefix),
                        'document_reference' => null,
                        'direction' => $debit === '0.00' ? 'credit' : 'debit',
                        'amount' => $debit === '0.00' ? $credit : $debit,
                        'currency' => $currency,
                        'source_page' => $pageIndex + 1,
                        'source_row' => $lineIndex + 1,
                    ];
                    $this->extractContinuationMetadata($line, $current);

                    continue;
                }

                if (preg_match('/^(SYNTHETIC TEST FIXTURE|BANCA COMERCIALA ROMANA|EXTRAS DE CONT|pe perioada|Cont nou:|Cod IBAN Nou:|Produse in valuta|Titular:|CIC:|CUI\/CNP:)/iu', $line)) {
                    continue;
                }

                if ($current !== null) {
                    if (preg_match('/^(Tranzactii\s+finalizate|Sold\s+contabil\s+final|Total\s+tranzactii|Sold\s+disponibil|Pagina\s+\d+\s+din\s+\d+)/iu', $line)) {
                        $transactions[] = $this->finishCandidate($current, $warnings);
                        $current = null;

                        continue;
                    }

                    $this->extractContinuationMetadata($line, $current);
                    if (! preg_match('/^(Data\s+Valorii|Document\s*:|Data\s*:|Data\s+operatiunii|Explicatie|Referinta\s+Oper\.|Debit\s+Credit)/iu', $line)) {
                        $current['description'] = trim($current['description'].' '.$line);
                    }
                }
            }
        }

        if ($current !== null) {
            $transactions[] = $this->finishCandidate($current, $warnings);
        }

        $debitCents = 0;
        $creditCents = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->operationDate < $statementStart || $transaction->operationDate > $statementEnd) {
                $warnings[] = ['code' => 'transaction_outside_statement_period', 'page' => $transaction->sourcePage, 'row' => $transaction->sourceRow];
            }

            if ($transaction->direction === 'debit') {
                $debitCents += $this->toMinorUnits($transaction->amount);
            } else {
                $creditCents += $this->toMinorUnits($transaction->amount);
            }
        }

        if ($debitCents !== $this->toMinorUnits($summaryTotals['debits'])
            || $creditCents !== $this->toMinorUnits($summaryTotals['credits'])) {
            $warnings[] = ['code' => 'statement_totals_mismatch', 'page' => 1, 'row' => 1];
        }

        if ($this->toMinorUnits($openingBalance) + $this->toMinorUnits($summaryTotals['credits']) - $this->toMinorUnits($summaryTotals['debits']) !== $this->toMinorUnits($closingBalance)) {
            $warnings[] = ['code' => 'statement_balance_mismatch', 'page' => 1, 'row' => 1];
        }

        if ($transactions === []) {
            throw new BankStatementParseException('no_transactions_extracted', 'No supported transaction rows were extracted from the statement.');
        }

        return new BankStatementParseResult(
            self::VERSION,
            hash_file('sha256', $path) ?: '',
            count($pages),
            $statementStart,
            $statementEnd,
            $currency,
            $openingBalance,
            $closingBalance,
            $summaryTotals['debits'],
            $summaryTotals['credits'],
            $transactions,
            $warnings,
        );
    }

    /** @param array<string, mixed> $candidate @param list<array{code: string, page: int, row: int}> $warnings */
    private function finishCandidate(array $candidate, array &$warnings): BankStatementTransactionCandidate
    {
        if ($candidate['operation_date'] < '1900-01-01') {
            $warnings[] = ['code' => 'transaction_date_out_of_range', 'page' => $candidate['source_page'], 'row' => $candidate['source_row']];
        }

        return new BankStatementTransactionCandidate(
            $candidate['operation_date'],
            $candidate['operation_time'],
            $candidate['value_date'],
            trim($candidate['description']),
            $candidate['operation_reference'],
            $candidate['document_reference'],
            $candidate['direction'],
            $candidate['amount'],
            $candidate['currency'],
            $candidate['source_page'],
            $candidate['source_row'],
        );
    }

    /** @param array<string, mixed> $candidate */
    private function extractContinuationMetadata(string $line, array &$candidate): void
    {
        if (preg_match('/(?:Data\s+Valorii|data\s+valutei)\s*:?\s*(\d{2}-\d{2}-\d{4})/iu', $line, $dateMatch)) {
            $candidate['value_date'] = $this->parseDate($dateMatch[1], $candidate['source_page'], $candidate['source_row']);
        }

        if (preg_match('/Document\s*:\s*([A-Z0-9][A-Z0-9._\/-]*)/iu', $line, $documentMatch)) {
            $candidate['document_reference'] = $documentMatch[1];
        }
    }

    private function extractReference(string $description): ?string
    {
        return preg_match('/\bReferinta\s+([A-Z0-9][A-Z0-9._\/-]*)/iu', $description, $match) ? $match[1] : null;
    }

    private function parseDate(string $date, int $page, int $row): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!d-m-Y', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('d-m-Y') !== $date) {
            throw new BankStatementParseException('invalid_date', "Invalid DD-MM-YYYY date at page {$page}, row {$row}.");
        }

        return $parsed->format('Y-m-d');
    }

    private function parseRomanianAmount(string $amount, int $page, int $row): string
    {
        if (! preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+),\d{2}$/u', $amount)) {
            throw new BankStatementParseException('invalid_amount', "Invalid Romanian decimal amount at page {$page}, row {$row}.");
        }

        [$whole, $fraction] = explode(',', str_replace('.', '', $amount), 2);
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 15) {
            throw new BankStatementParseException('amount_out_of_range', sprintf('Amount exceeds supported precision at page %d, row %d.', $page, $row));
        }

        return $whole.'.'.$fraction;
    }

    private function toMinorUnits(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount, 2);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function firstAmount(string $pattern, string $text): ?string
    {
        if (! preg_match($pattern, $text, $match)) {
            return null;
        }

        return $this->parseRomanianAmount($match[1], 1, 1);
    }

    /** @return array{debits: ?string, credits: ?string} */
    private function summaryTotals(string $text): array
    {
        if (! preg_match('/Total\s+tranzactii\s+finalizate\s+pe\s+perioada\s*:\s*(?:\d{2}-\d{2}-\d{4}\s*-\s*\d{2}-\d{2}-\d{4}\s*)?([\d.]+,\d{2})\s+([\d.]+,\d{2})/iu', $text, $match)) {
            return ['debits' => null, 'credits' => null];
        }

        return [
            'debits' => $this->parseRomanianAmount($match[1], 1, 1),
            'credits' => $this->parseRomanianAmount($match[2], 1, 1),
        ];
    }
}
