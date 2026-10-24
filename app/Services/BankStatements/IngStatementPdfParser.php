<?php

namespace App\Services\BankStatements;

use DateTimeImmutable;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

final class IngStatementPdfParser
{
    public const VERSION = 'ing-pdf-1.0.0';

    private const MAX_FILE_BYTES = 25_000_000;

    private const AMOUNT_PATTERN = '(?:\d+|\d{1,3}(?:,\d{3})+)\.\d{2}';

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
        if (! preg_match('/ING\s+Bank\s+N\.V\.\s+Amsterdam\s+Bucharest\s+Branch/iu', $allText)
            || ! preg_match('/Account\s+Statement/iu', $allText)
            || ! preg_match('/Book\s+date\s+from\s+\d{2}\.\d{2}\.\d{4}\s+to\s+\d{2}\.\d{2}\.\d{4}/iu', $allText)) {
            throw new BankStatementParseException('unsupported_issuer_or_layout', 'PDF does not match the documented ING Romania statement layout.');
        }
        if (! preg_match('/Currency\s*:\s*(RON)\b/iu', $allText, $currencyMatch)) {
            throw new BankStatementParseException('unsupported_currency_marker', 'Account currency marker is missing or unsupported for this parser version.');
        }
        if (! preg_match('/Book\s+date\s+from\s+(\d{2}\.\d{2}\.\d{4})\s+to\s+(\d{2}\.\d{2}\.\d{4})/iu', $allText, $period)) {
            throw new BankStatementParseException('statement_period_missing', 'Statement period is missing or does not match the documented date format.');
        }

        $statementStart = $this->parseDate($period[1], 1, 1);
        $statementEnd = $this->parseDate($period[2], 1, 1);
        if ($statementStart > $statementEnd) {
            throw new BankStatementParseException('statement_period_invalid', 'Statement period start is after its end.');
        }

        $openingBalance = $this->summaryAmount('/Opening\s+Balance\s*:\s*('.self::AMOUNT_PATTERN.')/iu', $allText, 'statement_opening_balance_missing');
        $closingBalance = $this->summaryAmount('/Closing\s+Balance\s*:\s*('.self::AMOUNT_PATTERN.')/iu', $allText, 'statement_closing_balance_missing');
        $totalCredits = $this->summaryAmount('/Total\s+Credits\s*:\s*\d+\s+('.self::AMOUNT_PATTERN.')/iu', $allText, 'statement_totals_missing');
        $totalDebits = $this->summaryAmount('/Total\s+Debits\s*:\s*\d+\s+(-?'.self::AMOUNT_PATTERN.')/iu', $allText, 'statement_totals_missing');

        $transactions = [];
        $warnings = [];
        $current = null;
        $inTransactions = false;
        foreach ($pageTexts as $pageIndex => $pageText) {
            $lines = preg_split('/\R/u', $pageText) ?: [];
            foreach ($lines as $lineIndex => $rawLine) {
                $line = trim(preg_replace('/\s+/u', ' ', $rawLine) ?? '');
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^Transactions\s*$/iu', $line)) {
                    $inTransactions = true;

                    continue;
                }
                if (! $inTransactions) {
                    continue;
                }
                if (preg_match('/^(ING\s+Bank\s+N\.V\.|Account\s+Statement|BIC\s+code|Account\s+number:|Account\s+name:|Account\s+type:|Account\s+owner:|Currency:|Address:|CUI:|Book\s+date\s+from|Book\s+date\s+Counterparty|This\s+document\s+was\s+issued|Valid\s+without\s+signature|Page\s+\d+\s+of\s+\d+)/iu', $line)) {
                    continue;
                }

                if (preg_match('/^(\d{2}\.\d{2}\.\d{4})\s+(.+)$/u', $line, $match)) {
                    if ($current !== null) {
                        $transactions[] = $this->finishCandidate($current, $warnings);
                    }

                    $date = $this->parseDate($match[1], $pageIndex + 1, $lineIndex + 1);
                    $tail = $this->extractTrailingAmounts($match[2]);
                    if ($tail === null) {
                        $current = [
                            'date' => $date,
                            'prefix' => trim($match[2]),
                            'description' => '',
                            'reference' => null,
                            'direction' => null,
                            'amount' => null,
                            'balance' => null,
                            'page' => $pageIndex + 1,
                            'row' => $lineIndex + 1,
                        ];
                        $warnings[] = ['code' => 'transaction_amount_columns_unreadable', 'page' => $pageIndex + 1, 'row' => $lineIndex + 1];

                        continue;
                    }

                    [$prefix, $signedAmount, $balance] = $tail;
                    $current = [
                        'date' => $date,
                        'prefix' => trim($prefix),
                        'description' => '',
                        'reference' => null,
                        'direction' => str_starts_with($signedAmount, '-') ? 'debit' : 'credit',
                        'amount' => ltrim($signedAmount, '-'),
                        'balance' => $balance,
                        'page' => $pageIndex + 1,
                        'row' => $lineIndex + 1,
                    ];
                    if ($signedAmount === '0.00' || $signedAmount === '-0.00') {
                        $warnings[] = ['code' => 'transaction_direction_ambiguous', 'page' => $pageIndex + 1, 'row' => $lineIndex + 1];
                    }

                    continue;
                }

                if ($current !== null) {
                    if (preg_match('/^(Book\s+date\s+Counterparty|Debit\s+Credit|Summary|Transactions|Total\s+Credits|Total\s+Debits|Opening\s+Balance|Closing\s+Balance|Page\s+\d+\s+of\s+\d+)/iu', $line)) {
                        continue;
                    }
                    if (preg_match('/^\d{4,}\s*$/u', $line) && $current['reference'] === null) {
                        $current['reference'] = $line;

                        continue;
                    }
                    if (preg_match('/^Bank\s+reference\s+(.+)$/iu', $line, $ref)) {
                        $current['description'] = trim($current['description'].' '.$line);
                        $current['bank_reference'] = trim($ref[1]);

                        continue;
                    }
                    $current['description'] = trim($current['description'].' '.$line);
                }
            }
        }
        if ($current !== null) {
            $transactions[] = $this->finishCandidate($current, $warnings);
        }

        if ($transactions === []) {
            throw new BankStatementParseException('no_transactions_extracted', 'No supported transaction rows were extracted from the statement.');
        }

        $signedDebitCents = 0;
        $creditCents = 0;
        $previousBalance = $this->toMinorUnits($openingBalance);
        foreach ($transactions as $transaction) {
            if ($transaction->operationDate < $statementStart || $transaction->operationDate > $statementEnd) {
                $warnings[] = ['code' => 'transaction_outside_statement_period', 'page' => $transaction->sourcePage, 'row' => $transaction->sourceRow];
            }
            $amountCents = $this->toMinorUnits($transaction->amount);
            $signedAmountCents = $transaction->direction === 'debit' ? -$amountCents : $amountCents;
            if ($transaction->direction === 'debit') {
                $signedDebitCents += $signedAmountCents;
            } else {
                $creditCents += $amountCents;
            }
            if ($transaction->balanceAfter !== null) {
                $reportedBalance = $this->toMinorUnits($transaction->balanceAfter);
                if ($previousBalance + $signedAmountCents !== $reportedBalance) {
                    $warnings[] = ['code' => 'transaction_balance_mismatch', 'page' => $transaction->sourcePage, 'row' => $transaction->sourceRow];
                }
                $previousBalance = $reportedBalance;
            }
        }

        if ($signedDebitCents !== $this->toMinorUnits($totalDebits) || $creditCents !== $this->toMinorUnits($totalCredits)) {
            $warnings[] = ['code' => 'statement_totals_mismatch', 'page' => 1, 'row' => 1];
        }
        if ($this->toMinorUnits($openingBalance) + $this->toMinorUnits($totalCredits) + $this->toMinorUnits($totalDebits) !== $this->toMinorUnits($closingBalance)) {
            $warnings[] = ['code' => 'statement_balance_mismatch', 'page' => 1, 'row' => 1];
        }

        return new BankStatementParseResult(
            self::VERSION,
            hash_file('sha256', $path) ?: '',
            count($pages),
            $statementStart,
            $statementEnd,
            strtoupper($currencyMatch[1]),
            $openingBalance,
            $closingBalance,
            $totalDebits,
            $totalCredits,
            $transactions,
            $warnings,
        );
    }

    /** @param list<array{code: string, page: int, row: int}> $warnings */
    private function finishCandidate(array $row, array &$warnings): BankStatementTransactionCandidate
    {
        if ($row['direction'] === null || $row['amount'] === null) {
            return new BankStatementTransactionCandidate($row['date'], null, null, trim($row['prefix'].' '.$row['description']), $row['reference'], null, 'debit', '0.00', 'RON', $row['page'], $row['row']);
        }

        $description = trim($row['prefix'].' '.$row['description']);
        $operationReference = $row['reference'];
        $documentReference = null;
        if (preg_match('/Bank\s+reference\s+([a-z0-9-]+)/iu', $description, $match)) {
            $documentReference = $match[1];
        }

        return new BankStatementTransactionCandidate(
            $row['date'],
            null,
            null,
            $description,
            $operationReference,
            $documentReference,
            $row['direction'],
            $row['amount'],
            'RON',
            $row['page'],
            $row['row'],
            $row['balance'],
            $row['prefix'],
        );
    }

    /** @return array{string, string, string}|null prefix, signed transaction amount, balance after */
    private function extractTrailingAmounts(string $text): ?array
    {
        $pattern = '/^(.*?)\s+(-?'.self::AMOUNT_PATTERN.')\s+(-?'.self::AMOUNT_PATTERN.')\s*$/u';
        if (! preg_match($pattern, $text, $match)) {
            return null;
        }

        return [trim($match[1]), $this->normalizeAmount($match[2]), $this->normalizeAmount($match[3])];
    }

    private function summaryAmount(string $pattern, string $text, string $errorCode): string
    {
        if (! preg_match($pattern, $text, $match)) {
            throw new BankStatementParseException($errorCode, 'Required statement summary field is missing or malformed.');
        }

        return $this->normalizeAmount($match[1]);
    }

    private function normalizeAmount(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        $unsigned = ltrim($amount, '-');
        if (! preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)\.\d{2}$/u', $unsigned)) {
            throw new BankStatementParseException('invalid_amount', 'Amount does not match the documented ING decimal format.');
        }
        [$whole, $fraction] = explode('.', str_replace(',', '', $unsigned), 2);
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 15) {
            throw new BankStatementParseException('amount_out_of_range', 'Amount exceeds supported precision.');
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function parseDate(string $date, int $page, int $row): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!d.m.Y', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('d.m.Y') !== $date) {
            throw new BankStatementParseException('invalid_date', sprintf('Invalid DD.MM.YYYY date at page %d, row %d.', $page, $row));
        }

        return $parsed->format('Y-m-d');
    }

    private function toMinorUnits(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = explode('.', ltrim($amount, '-'), 2);
        $minor = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$minor : $minor;
    }
}
