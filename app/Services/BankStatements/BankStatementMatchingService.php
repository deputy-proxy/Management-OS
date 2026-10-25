<?php

namespace App\Services\BankStatements;

use App\Models\BankStatementImportBatch;
use App\Models\BankStatementImportItem;
use App\Models\CustomerInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class BankStatementMatchingService
{
    public const OUTCOMES = ['exact_match', 'partial_candidate', 'unmatched', 'ambiguous', 'rejected'];

    public function currentDecision(BankStatementImportItem $item): ?Activity
    {
        return Activity::query()
            ->where('subject_type', $item->getMorphClass())
            ->where('subject_id', $item->getKey())
            ->where('event', 'manual_match_decision')
            ->latest('id')
            ->first();
    }

    /** Record a reversible decision without changing any financial record. */
    public function recordDecision(
        int $itemId,
        User $actor,
        string $outcome,
        ?string $targetType,
        ?int $targetId,
        string $reason,
    ): Activity {
        Gate::forUser($actor)->authorize('transaction.update');

        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => 'Choose a supported matching outcome.']);
        }

        if (trim($reason) === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'A reason of 1 to 500 characters is required.']);
        }

        $needsTarget = in_array($outcome, ['exact_match', 'partial_candidate'], true);
        if ($needsTarget !== ($targetType !== null && $targetId !== null)) {
            throw ValidationException::withMessages(['target_id' => 'Exact and partial decisions require one target; other outcomes must not have one.']);
        }

        return DB::transaction(function () use ($itemId, $actor, $outcome, $targetType, $targetId, $reason, $needsTarget): Activity {
            $item = BankStatementImportItem::query()->with('batch')->lockForUpdate()->findOrFail($itemId);
            if ($item->batch?->status !== BankStatementImportBatch::STATUS_COMPLETED) {
                throw ValidationException::withMessages(['item' => 'Only completed imports can be matched.']);
            }

            $target = null;
            if ($needsTarget) {
                $targetClass = match ($targetType) {
                    'transaction' => Transaction::class,
                    'customer_invoice' => CustomerInvoice::class,
                    'vendor_invoice' => VendorInvoice::class,
                    default => null,
                };
                if ($targetClass === null) {
                    throw ValidationException::withMessages(['target_type' => 'Unsupported matching target.']);
                }
                $target = $targetClass::query()->findOrFail($targetId);
                if ($target instanceof Transaction) {
                    if ((int) $target->financial_account_id !== (int) $item->financial_account_id) {
                        throw ValidationException::withMessages(['target_id' => 'A transaction from another financial account cannot be matched.']);
                    }
                    if ($outcome === 'exact_match') {
                        if ($target->getAttribute('currency_code') !== null && strtoupper((string) $target->getAttribute('currency_code')) !== strtoupper((string) $item->currency_code)) {
                            throw ValidationException::withMessages(['target_id' => 'Currency must match for an exact transaction match.']);
                        }
                        $existingAmount = $target->getAttribute('amount_decimal') ?? $target->getAttribute('amount');
                        if (number_format((float) $existingAmount, 2, '.', '') !== number_format((float) $item->amount_decimal, 2, '.', '')) {
                            throw ValidationException::withMessages(['target_id' => 'Amount must match for an exact transaction match.']);
                        }
                    }
                }
                if ($outcome === 'exact_match' && $this->isAlreadyExactlyMatched($item, $targetType, $targetId)) {
                    throw ValidationException::withMessages(['target_id' => 'This record already has an active exact match.']);
                }
            }

            $previous = $this->currentDecision($item);

            $logged = activity()
                ->performedOn($item)
                ->causedBy($actor)
                ->event('manual_match_decision')
                ->withProperties([
                    'outcome' => $outcome,
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'reason' => trim($reason),
                    'previous_outcome' => $previous?->getProperty('outcome'),
                    'previous_target_type' => $previous?->getProperty('target_type'),
                    'previous_target_id' => $previous?->getProperty('target_id'),
                    'manual_override' => $previous !== null,
                    'settlement_performed' => false,
                    'allocation_performed' => false,
                    'ledger_mutation_performed' => false,
                ])
                ->log('Manual bank statement match decision recorded');

            return $this->currentDecision($item) ?? throw new \LogicException('The matching decision audit event was not persisted.');
        });
    }

    private function isAlreadyExactlyMatched(BankStatementImportItem $current, string $targetType, int $targetId): bool
    {
        $activities = Activity::query()
            ->where('event', 'manual_match_decision')
            ->where('subject_type', $current->getMorphClass())
            ->where('subject_id', '!=', $current->getKey())
            ->orderByDesc('id')
            ->get();

        $seen = [];
        foreach ($activities as $activity) {
            $key = (string) $activity->subject_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($activity->getProperty('outcome') === 'exact_match'
                && $activity->getProperty('target_type') === $targetType
                && (int) $activity->getProperty('target_id') === $targetId) {
                return true;
            }
        }

        return false;
    }
}
