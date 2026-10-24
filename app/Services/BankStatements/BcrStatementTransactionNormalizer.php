<?php

namespace App\Services\BankStatements;

final class BcrStatementTransactionNormalizer
{
    public function normalize(BankStatementParseResult $parsed): BankStatementNormalizationResult
    {
        $drafts = [];
        $principalsByReference = [];

        foreach ($parsed->transactions as $index => $candidate) {
            $transactionType = $this->detectFeeType($candidate->description);
            $direction = $candidate->direction === 'credit' ? 'money_in' : 'money_out';
            if ($transactionType === null
                && $direction === 'money_out'
                && $candidate->operationReference !== null
                && ! $this->hasForeignCurrencyDetail($candidate->description)
                && ! $this->mayBeRefundOrReversal($candidate->description)) {
                $principalsByReference[$candidate->operationReference][] = [
                    'index' => $index,
                    'direction' => $direction,
                    'type' => $transactionType,
                    'page' => $candidate->sourcePage,
                    'row' => $candidate->sourceRow,
                ];
            }

            $reasons = [];
            if ($transactionType === null) {
                $reasons[] = 'business_type_unresolved';
            }
            if ($this->hasForeignCurrencyDetail($candidate->description)) {
                $reasons[] = 'foreign_currency_semantics_unverified';
            }
            if ($this->mayBeRefundOrReversal($candidate->description)) {
                $reasons[] = 'possible_refund_or_reversal';
            }

            $drafts[$index] = [
                'candidate' => $candidate,
                'direction' => $direction,
                'transaction_type' => $transactionType,
                'reasons' => $reasons,
                'related_page' => null,
                'related_row' => null,
            ];
        }

        foreach ($drafts as $index => &$draft) {
            /** @var BankStatementTransactionCandidate $candidate */
            $candidate = $draft['candidate'];
            if ($draft['transaction_type'] !== 'fee' || ! $this->isProcessingFee($candidate->description)) {
                continue;
            }

            $matches = $principalsByReference[$candidate->operationReference ?? ''] ?? [];

            if (count($matches) === 1) {
                $draft['related_page'] = $matches[0]['page'];
                $draft['related_row'] = $matches[0]['row'];
            } elseif (count($matches) > 1) {
                $draft['reasons'][] = 'fee_link_ambiguous';
            } else {
                $draft['reasons'][] = 'fee_link_not_established';
            }
        }
        unset($draft);

        $normalized = [];
        foreach ($drafts as $draft) {
            /** @var BankStatementTransactionCandidate $candidate */
            $candidate = $draft['candidate'];
            $reasons = array_values(array_unique($draft['reasons']));
            $normalized[] = new NormalizedBankStatementTransaction(
                $candidate->operationDate,
                $candidate->valueDate,
                $draft['direction'],
                $candidate->amount,
                $candidate->currency,
                $draft['transaction_type'],
                $reasons === [] ? 'mapped' : 'review_required',
                $candidate->description,
                $candidate->operationReference,
                $candidate->documentReference,
                $parsed->sourceSha256,
                $parsed->parserVersion,
                $candidate->sourcePage,
                $candidate->sourceRow,
                $draft['related_page'],
                $draft['related_row'],
                $reasons,
            );
        }

        return new BankStatementNormalizationResult(
            $parsed->sourceSha256,
            $parsed->parserVersion,
            $parsed->statementStart,
            $parsed->statementEnd,
            $parsed->currency,
            $normalized,
            $parsed->warnings,
        );
    }

    private function detectFeeType(string $description): ?string
    {
        return preg_match('/^(?:Comision(?:\s+Procesare|\s+conversie)?|Incasare\s+Comision\s+de\s+administrare)\b/iu', trim($description)) === 1
            ? 'fee'
            : null;
    }

    private function isProcessingFee(string $description): bool
    {
        return preg_match('/^Comision\s+(?:Procesare|conversie)\b/iu', trim($description)) === 1;
    }

    private function hasForeignCurrencyDetail(string $description): bool
    {
        return preg_match('/\b(?:EUR|USD|GBP|CHF)\b|rata\s+de\s+schimb|suma\s+in\s+valuta\s+contului/iu', $description) === 1;
    }

    private function mayBeRefundOrReversal(string $description): bool
    {
        return preg_match('/\b(?:retur|revers|storn|refund|anulare|restituire)\b/iu', $description) === 1;
    }
}
