<?php

namespace App\Services\Finance;

use App\Models\CustomerInvoice;
use App\Models\PaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorInvoice;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function propose(
        User $actor,
        int $transactionId,
        string $kind,
        string $amount,
        string $currency,
        ?string $targetType,
        ?int $targetId,
        string $reason,
    ): PaymentAllocation {
        Gate::forUser($actor)->authorize('transaction.update');

        if (! in_array($kind, [PaymentAllocation::KIND_INVOICE, PaymentAllocation::KIND_FEE], true)) {
            throw ValidationException::withMessages(['allocation_kind' => 'Choose invoice allocation or payment fee.']);
        }
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A reason of 1 to 1000 characters is required.']);
        }

        try {
            $amountValue = BigDecimal::of($amount)->toScale(2);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount_decimal' => 'Enter a valid amount with at most two decimal places.']);
        }
        if ($amountValue->compareTo(BigDecimal::zero()) <= 0) {
            throw ValidationException::withMessages(['amount_decimal' => 'Allocation amounts must be greater than zero.']);
        }

        $needsInvoice = $kind === PaymentAllocation::KIND_INVOICE;
        if ($needsInvoice !== ($targetType !== null && $targetId !== null)) {
            throw ValidationException::withMessages(['target_id' => 'Invoice allocations require exactly one invoice target; fees must not have one.']);
        }

        $transaction = Transaction::query()->findOrFail($transactionId);
        $paymentCurrency = strtoupper(trim((string) $transaction->getAttribute('currency_code')));
        if ($transaction->getAttribute('amount_decimal') === null || preg_match('/^[A-Z]{3}$/', $paymentCurrency) !== 1) {
            throw ValidationException::withMessages(['transaction_id' => 'This payment lacks a verified canonical amount or currency. Normalize it before allocation.']);
        }
        if (strtoupper(trim($currency)) !== $paymentCurrency) {
            throw ValidationException::withMessages(['currency_code' => 'Allocation currency must equal the source payment currency. FX allocation is not supported.']);
        }

        $customerInvoiceId = null;
        $vendorInvoiceId = null;
        if ($needsInvoice) {
            $direction = $transaction->getAttribute('transaction_type_code');
            if (! in_array($direction, ['money_in', 'money_out'], true)) {
                throw ValidationException::withMessages(['transaction_id' => 'Payment direction is unresolved. Approve its canonical classification before invoice allocation.']);
            }
            if (($targetType === 'customer_invoice' && $direction !== 'money_in')
                || ($targetType === 'vendor_invoice' && $direction !== 'money_out')) {
                throw ValidationException::withMessages(['target_type' => 'Invoice type does not agree with the source payment direction.']);
            }

            $invoice = match ($targetType) {
                'customer_invoice' => CustomerInvoice::query()->findOrFail($targetId),
                'vendor_invoice' => VendorInvoice::query()->findOrFail($targetId),
                default => throw ValidationException::withMessages(['target_type' => 'Unsupported invoice type.']),
            };
            $invoiceCurrency = strtoupper(trim((string) $invoice->getAttribute('currency_code')));
            if (preg_match('/^[A-Z]{3}$/', $invoiceCurrency) !== 1) {
                throw ValidationException::withMessages(['target_id' => 'This invoice lacks a verified canonical currency and cannot be allocated yet.']);
            }
            if ($invoiceCurrency !== $paymentCurrency) {
                throw ValidationException::withMessages(['target_id' => 'Invoice and payment currencies differ. FX allocation is not supported.']);
            }
            if ($targetType === 'customer_invoice') {
                $customerInvoiceId = $invoice->getKey();
            } else {
                $vendorInvoiceId = $invoice->getKey();
            }
        }

        return PaymentAllocation::query()->create([
            'transaction_id' => $transaction->getKey(),
            'customer_invoice_id' => $customerInvoiceId,
            'vendor_invoice_id' => $vendorInvoiceId,
            'allocation_kind' => $kind,
            'amount_decimal' => (string) $amountValue,
            'currency_code' => $paymentCurrency,
            'status' => PaymentAllocation::STATUS_PROPOSED,
            'reason' => trim($reason),
            'created_by' => $actor->getKey(),
        ]);
    }

    public function confirm(int $allocationId, User $actor): PaymentAllocation
    {
        Gate::forUser($actor)->authorize('transaction.update');

        return DB::transaction(function () use ($allocationId, $actor): PaymentAllocation {
            $allocation = PaymentAllocation::query()->lockForUpdate()->findOrFail($allocationId);
            if ($allocation->status !== PaymentAllocation::STATUS_PROPOSED) {
                throw ValidationException::withMessages(['allocation' => 'Only proposed allocations can be confirmed.']);
            }

            $transaction = Transaction::query()->lockForUpdate()->findOrFail($allocation->transaction_id);
            $paymentAmount = $this->positiveAmount($transaction->getAttribute('amount_decimal'));
            $paymentCurrency = strtoupper(trim((string) $transaction->getAttribute('currency_code')));
            if ($paymentAmount === null || preg_match('/^[A-Z]{3}$/', $paymentCurrency) !== 1) {
                throw ValidationException::withMessages(['transaction_id' => 'The source payment amount or currency is unresolved.']);
            }
            if ($paymentCurrency !== $allocation->currency_code) {
                throw ValidationException::withMessages(['currency_code' => 'Allocation currency no longer matches the source payment.']);
            }
            if ($allocation->allocation_kind === PaymentAllocation::KIND_INVOICE) {
                $direction = $transaction->getAttribute('transaction_type_code');
                if (($allocation->customer_invoice_id !== null && $direction !== 'money_in')
                    || ($allocation->vendor_invoice_id !== null && $direction !== 'money_out')) {
                    throw ValidationException::withMessages(['transaction_id' => 'Source payment direction no longer supports this invoice allocation.']);
                }
                $invoice = $allocation->customer_invoice_id !== null
                    ? CustomerInvoice::query()->findOrFail($allocation->customer_invoice_id)
                    : VendorInvoice::query()->findOrFail($allocation->vendor_invoice_id);
                $invoiceCurrency = strtoupper(trim((string) $invoice->getAttribute('currency_code')));
                if (preg_match('/^[A-Z]{3}$/', $invoiceCurrency) !== 1 || $invoiceCurrency !== $paymentCurrency) {
                    throw ValidationException::withMessages(['target_id' => 'Invoice currency is unresolved or no longer matches the source payment.']);
                }
            }

            if ($allocation->allocation_kind === PaymentAllocation::KIND_INVOICE) {
                $duplicate = PaymentAllocation::query()
                    ->where('transaction_id', $transaction->getKey())
                    ->where('status', PaymentAllocation::STATUS_CONFIRMED)
                    ->where('allocation_kind', PaymentAllocation::KIND_INVOICE)
                    ->where('id', '<>', $allocation->getKey())
                    ->when($allocation->customer_invoice_id !== null, fn ($query) => $query->where('customer_invoice_id', $allocation->customer_invoice_id))
                    ->when($allocation->vendor_invoice_id !== null, fn ($query) => $query->where('vendor_invoice_id', $allocation->vendor_invoice_id))
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['target_id' => 'This payment already has a confirmed allocation to that invoice. Reverse the earlier allocation before replacing it.']);
                }
            }

            $alreadyAllocated = BigDecimal::of((string) (PaymentAllocation::query()
                ->where('transaction_id', $transaction->getKey())
                ->where('status', PaymentAllocation::STATUS_CONFIRMED)
                ->sum('amount_decimal')));
            $requested = BigDecimal::of((string) $allocation->amount_decimal);
            if ($alreadyAllocated->plus($requested)->compareTo($paymentAmount) > 0) {
                throw ValidationException::withMessages(['amount_decimal' => 'This allocation would exceed the source payment. Keep any overpayment explicitly unallocated.']);
            }

            $allocation->forceFill([
                'status' => PaymentAllocation::STATUS_CONFIRMED,
                'confirmed_by' => $actor->getKey(),
                'confirmed_at' => now(),
            ])->save();

            activity()
                ->performedOn($allocation)
                ->causedBy($actor)
                ->event('payment_allocation_confirmed')
                ->withProperties([
                    'transaction_id' => $transaction->getKey(),
                    'amount_decimal' => (string) $requested,
                    'currency_code' => $allocation->currency_code,
                    'invoice_balance_validation' => $allocation->allocation_kind === PaymentAllocation::KIND_INVOICE ? 'unresolved_invoice_total_semantics' : 'not_applicable',
                ])
                ->log('Payment allocation confirmed');

            return $allocation->fresh();
        });
    }

    public function reject(int $allocationId, User $actor, string $reason): PaymentAllocation
    {
        Gate::forUser($actor)->authorize('transaction.update');
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A rejection reason of 1 to 1000 characters is required.']);
        }

        return DB::transaction(function () use ($allocationId, $actor, $reason): PaymentAllocation {
            $allocation = PaymentAllocation::query()->lockForUpdate()->findOrFail($allocationId);
            if ($allocation->status !== PaymentAllocation::STATUS_PROPOSED) {
                throw ValidationException::withMessages(['allocation' => 'Only proposed allocations can be rejected.']);
            }
            $allocation->forceFill([
                'status' => PaymentAllocation::STATUS_REJECTED,
                'rejected_by' => $actor->getKey(),
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            activity()
                ->performedOn($allocation)
                ->causedBy($actor)
                ->event('payment_allocation_rejected')
                ->withProperties([
                    'transaction_id' => $allocation->transaction_id,
                    'amount_decimal' => (string) $allocation->amount_decimal,
                    'currency_code' => $allocation->currency_code,
                    'reason' => trim($reason),
                    'ledger_mutation_performed' => false,
                ])
                ->log('Payment allocation proposal rejected');

            return $allocation->fresh();
        });
    }

    public function reverse(int $allocationId, User $actor, string $reason): PaymentAllocation
    {
        Gate::forUser($actor)->authorize('transaction.update');
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'A reversal reason of 1 to 1000 characters is required.']);
        }

        return DB::transaction(function () use ($allocationId, $actor, $reason): PaymentAllocation {
            $allocation = PaymentAllocation::query()->lockForUpdate()->findOrFail($allocationId);
            if ($allocation->status !== PaymentAllocation::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['allocation' => 'Only confirmed allocations can be reversed.']);
            }

            $allocation->forceFill([
                'status' => PaymentAllocation::STATUS_REVERSED,
                'reversed_by' => $actor->getKey(),
                'reversed_at' => now(),
                'reversal_reason' => trim($reason),
            ])->save();

            activity()
                ->performedOn($allocation)
                ->causedBy($actor)
                ->event('payment_allocation_reversed')
                ->withProperties([
                    'transaction_id' => $allocation->transaction_id,
                    'amount_decimal' => (string) $allocation->amount_decimal,
                    'currency_code' => $allocation->currency_code,
                    'reason' => trim($reason),
                    'ledger_mutation_performed' => false,
                ])
                ->log('Payment allocation reversed');

            return $allocation->fresh();
        });
    }

    /** @return array{status: string, currency_code: ?string, payment_amount: ?string, invoice_allocated: string, fees: string, total_allocated: string, unallocated: ?string, invoice_balance_validation: string} */
    public function paymentSummary(Transaction $transaction): array
    {
        $currency = strtoupper(trim((string) $transaction->getAttribute('currency_code')));
        $paymentAmount = $this->positiveAmount($transaction->getAttribute('amount_decimal'));
        if ($paymentAmount === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return [
                'status' => 'needs_review',
                'currency_code' => null,
                'payment_amount' => null,
                'invoice_allocated' => '0.00',
                'fees' => '0.00',
                'total_allocated' => '0.00',
                'unallocated' => null,
                'invoice_balance_validation' => 'unresolved_source_amount_or_currency',
            ];
        }

        $aggregate = DB::table('payment_allocations')
            ->where('transaction_id', $transaction->getKey())
            ->selectRaw('SUM(CASE WHEN status = ? AND allocation_kind = ? THEN amount_decimal ELSE 0 END) AS invoice_total', [PaymentAllocation::STATUS_CONFIRMED, PaymentAllocation::KIND_INVOICE])
            ->selectRaw('SUM(CASE WHEN status = ? AND allocation_kind = ? THEN amount_decimal ELSE 0 END) AS fee_total', [PaymentAllocation::STATUS_CONFIRMED, PaymentAllocation::KIND_FEE])
            ->selectRaw('SUM(CASE WHEN status = ? AND currency_code <> ? THEN 1 ELSE 0 END) AS currency_mismatches', [PaymentAllocation::STATUS_CONFIRMED, $currency])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS proposed_count', [PaymentAllocation::STATUS_PROPOSED])
            ->first();
        if ((int) ($aggregate->currency_mismatches ?? 0) > 0) {
            return [
                'status' => 'needs_review',
                'currency_code' => $currency,
                'payment_amount' => $this->formatAmount($paymentAmount),
                'invoice_allocated' => '0.00',
                'fees' => '0.00',
                'total_allocated' => '0.00',
                'unallocated' => null,
                'invoice_balance_validation' => 'confirmed_allocation_currency_mismatch',
            ];
        }

        $invoiceAllocated = BigDecimal::of((string) ($aggregate->invoice_total ?? '0.00'));
        $fees = BigDecimal::of((string) ($aggregate->fee_total ?? '0.00'));
        $total = $invoiceAllocated->plus($fees);
        $unallocated = $paymentAmount->minus($total);

        $status = match (true) {
            $total->compareTo($paymentAmount) > 0 => 'overallocated',
            $total->compareTo(BigDecimal::zero()) === 0 && (int) ($aggregate->proposed_count ?? 0) > 0 => 'proposed',
            $total->compareTo(BigDecimal::zero()) === 0 => 'unmatched',
            $total->compareTo($paymentAmount) < 0 => 'partially_allocated',
            default => 'fully_allocated',
        };

        return [
            'status' => $status,
            'currency_code' => $currency,
            'payment_amount' => $this->formatAmount($paymentAmount),
            'invoice_allocated' => $this->formatAmount($invoiceAllocated),
            'fees' => $this->formatAmount($fees),
            'total_allocated' => $this->formatAmount($total),
            'unallocated' => $this->formatAmount($unallocated->compareTo(BigDecimal::zero()) < 0 ? BigDecimal::zero() : $unallocated),
            'invoice_balance_validation' => $invoiceAllocated->compareTo(BigDecimal::zero()) > 0 ? 'unresolved_invoice_total_semantics' : 'not_applicable',
        ];
    }

    /** @return array{status: string, currency_code: ?string, allocated_total: string, allocation_count: int, invoice_balance_validation: string} */
    public function invoiceSummary(string $targetType, int $targetId): array
    {
        $invoice = match ($targetType) {
            'customer_invoice' => CustomerInvoice::query()->findOrFail($targetId),
            'vendor_invoice' => VendorInvoice::query()->findOrFail($targetId),
            default => throw ValidationException::withMessages(['target_type' => 'Unsupported invoice type.']),
        };
        $currency = strtoupper(trim((string) $invoice->getAttribute('currency_code')));
        $query = DB::table('payment_allocations')
            ->where('status', PaymentAllocation::STATUS_CONFIRMED)
            ->where('allocation_kind', PaymentAllocation::KIND_INVOICE)
            ->when($targetType === 'customer_invoice', fn ($query) => $query->where('customer_invoice_id', $targetId))
            ->when($targetType === 'vendor_invoice', fn ($query) => $query->where('vendor_invoice_id', $targetId));
        $aggregate = $query
            ->selectRaw('SUM(amount_decimal) AS allocated_total')
            ->selectRaw('COUNT(*) AS allocation_count')
            ->selectRaw('SUM(CASE WHEN currency_code <> ? THEN 1 ELSE 0 END) AS currency_mismatches', [$currency])
            ->first();
        $total = BigDecimal::of((string) ($aggregate->allocated_total ?? '0.00'));
        $currencyIsValid = preg_match('/^[A-Z]{3}$/', $currency) === 1;
        $allocationCurrencyMismatch = $currencyIsValid && (int) ($aggregate->currency_mismatches ?? 0) > 0;
        $needsReview = ! $currencyIsValid || $allocationCurrencyMismatch;

        return [
            'status' => $needsReview ? 'needs_review' : 'allocation_total_only',
            'currency_code' => $currencyIsValid ? $currency : null,
            'allocated_total' => $this->formatAmount($total),
            'allocation_count' => (int) ($aggregate->allocation_count ?? 0),
            'invoice_balance_validation' => $allocationCurrencyMismatch ? 'invoice_allocation_currency_mismatch' : 'unresolved_invoice_total_semantics',
        ];
    }

    private function formatAmount(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2);
    }

    private function positiveAmount(mixed $amount): ?BigDecimal
    {
        if (! is_numeric($amount)) {
            return null;
        }
        $value = BigDecimal::of((string) $amount);

        return $value->compareTo(BigDecimal::zero()) < 0 ? $value->negated() : $value;
    }
}
