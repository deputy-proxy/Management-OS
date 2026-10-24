<?php

namespace App\Services\BankStatements;

final class IngStatementTransactionNormalizer
{
    public function normalize(BankStatementParseResult $parsed): BankStatementNormalizationResult
    {
        $normalized = [];
        $seenReferences = [];

        foreach ($parsed->transactions as $candidate) {
            $description = trim($candidate->description);
            $transactionType = $this->detectTransactionType($description);
            $direction = $candidate->direction === 'credit' ? 'money_in' : 'money_out';
            $reasons = [];

            if ($transactionType === null) {
                $reasons[] = 'business_type_unresolved';
            }
            if ($transactionType === 'credit_line_movement') {
                $reasons[] = 'credit_line_accounting_semantics_unverified';
            }
            if ($this->hasForeignCurrencyDetail($description)) {
                $reasons[] = 'foreign_currency_semantics_unverified';
            }
            if ($this->mayBeRefundOrReversal($description)) {
                $reasons[] = 'possible_refund_or_reversal';
            }
            if ($candidate->operationReference !== null) {
                $seenReferences[$candidate->operationReference] = ($seenReferences[$candidate->operationReference] ?? 0) + 1;
            }

            $documentReference = $candidate->documentReference;
            if ($documentReference === null && preg_match('/Bank\s+reference\s+([a-z0-9-]+)/iu', $description, $match)) {
                $documentReference = $match[1];
            }

            $normalized[] = new NormalizedBankStatementTransaction(
                $candidate->operationDate,
                $candidate->valueDate,
                $direction,
                $candidate->amount,
                $candidate->currency,
                $transactionType,
                $reasons === [] ? 'mapped' : 'review_required',
                $description,
                $candidate->operationReference,
                $documentReference,
                $parsed->sourceSha256,
                $parsed->parserVersion,
                $candidate->sourcePage,
                $candidate->sourceRow,
                null,
                null,
                $reasons,
                $candidate->balanceAfter,
                $candidate->counterparty,
            );
        }

        $warnings = $parsed->warnings;
        foreach ($seenReferences as $reference => $count) {
            if ($count > 1) {
                $matches = array_values(array_filter($parsed->transactions, static fn (BankStatementTransactionCandidate $candidate): bool => (string) $candidate->operationReference === (string) $reference));
                foreach ($matches as $candidate) {
                    $warnings[] = ['code' => 'duplicate_source_reference', 'page' => $candidate->sourcePage, 'row' => $candidate->sourceRow];
                }
            }
        }

        return new BankStatementNormalizationResult(
            $parsed->sourceSha256,
            $parsed->parserVersion,
            $parsed->statementStart,
            $parsed->statementEnd,
            $parsed->currency,
            $normalized,
            $warnings,
        );
    }

    private function detectTransactionType(string $description): ?string
    {
        if (preg_match('/\b(?:Service\s+Fee|Bank\s+Service\s+Fee)\b/iu', $description) === 1) {
            return 'fee';
        }
        if (preg_match('/\bAmount\s+debited\s+from\s+credit\s+line\b/iu', $description) === 1) {
            return 'credit_line_movement';
        }

        return null;
    }

    private function hasForeignCurrencyDetail(string $description): bool
    {
        return preg_match('/\b(?:EUR|USD|GBP|CHF)\b|Settlement\s+Amount|\bRate\s*:/iu', $description) === 1;
    }

    private function mayBeRefundOrReversal(string $description): bool
    {
        return preg_match('/\b(?:refund|reversal|reversed|chargeback|return|returned|storno|restituire|anulare)\b/iu', $description) === 1;
    }
}
