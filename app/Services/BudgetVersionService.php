<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetLineItem;
use App\Models\BudgetVersion;
use App\Models\BudgetVersionEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetVersionService
{
    /** @param array<string, mixed> $data */
    public function createBudget(array $data): Budget
    {
        $this->authorize('budget.create');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'Budget name is required and must be at most 160 characters.']);
        }

        return DB::transaction(function () use ($data, $name): Budget {
            $budget = Budget::query()->create(['name' => $name, 'created_by' => auth()->id()]);
            $this->createVersionRecord($budget, $data, 1, 'created');

            return $budget->load(['latestVersion.lineItems', 'versions.events']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createVersion(Budget $budget, array $data): BudgetVersion
    {
        $this->authorize('budget.update');

        return DB::transaction(function () use ($budget, $data): BudgetVersion {
            $latest = BudgetVersion::query()->where('budget_id', $budget->getKey())->lockForUpdate()->orderByDesc('version')->first();
            if ($latest && in_array($latest->status, [BudgetVersion::STATUS_DRAFT, BudgetVersion::STATUS_SUBMITTED], true)) {
                throw ValidationException::withMessages(['status' => 'Finish or retire the current draft/submitted version before creating another version.']);
            }

            if ($latest) {
                $data = array_replace([
                    'period_start' => Carbon::parse($latest->period_start)->toDateString(),
                    'period_end' => Carbon::parse($latest->period_end)->toDateString(),
                    'currency_code' => $latest->currency_code,
                    'dimensions' => $latest->dimensions ?? [],
                    'line_items' => $latest->lineItems->map(fn (BudgetLineItem $item): array => [
                        'code' => $item->code,
                        'description' => $item->description,
                        'amount' => $item->amount,
                        'dimensions' => $item->dimensions ?? [],
                    ])->all(),
                ], $data);
            }

            return $this->createVersionRecord($budget, $data, $latest === null ? 1 : $latest->version + 1, 'version_created');
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(BudgetVersion $version, array $data): BudgetVersion
    {
        $this->authorize('budget.update');
        if ($version->status !== BudgetVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft budget versions can be edited.']);
        }

        return DB::transaction(function () use ($version, $data): BudgetVersion {
            $version = BudgetVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            if ($version->status !== BudgetVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft budget versions can be edited.']);
            }
            $normalized = $this->normalizeVersionData($data);
            $version->fill($normalized);
            $version->save();
            $version->lineItems()->delete();
            $this->createLineItems($version, $normalized['line_items']);
            $this->recordEvent($version, 'updated');

            return $version->load('lineItems', 'events');
        });
    }

    public function submit(BudgetVersion $version): BudgetVersion
    {
        $this->authorize('budget.submit');
        if ($version->status !== BudgetVersion::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft budget versions can be submitted.']);
        }

        return DB::transaction(function () use ($version): BudgetVersion {
            $version = BudgetVersion::query()->with('lineItems')->lockForUpdate()->findOrFail($version->getKey());
            if ($version->status !== BudgetVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['status' => 'Only draft budget versions can be submitted.']);
            }
            $this->assertNoPeriodOverlap($version);
            $this->assertLineItemDimensions($version);
            if ($version->lineItems->isEmpty()) {
                throw ValidationException::withMessages(['line_items' => 'Add at least one budget line item before submission.']);
            }
            $version->status = BudgetVersion::STATUS_SUBMITTED;
            $version->submitted_by = auth()->id();
            $version->submitted_at = now();
            $version->save();
            $this->recordEvent($version, 'submitted');

            return $version->refresh()->load('lineItems', 'events');
        });
    }

    public function approve(BudgetVersion $version): BudgetVersion
    {
        $this->authorize('budget.approve');
        if ($version->status !== BudgetVersion::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Only submitted budget versions can be approved.']);
        }

        return DB::transaction(function () use ($version): BudgetVersion {
            $version = BudgetVersion::query()->with('lineItems')->lockForUpdate()->findOrFail($version->getKey());
            if ($version->status !== BudgetVersion::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only submitted budget versions can be approved.']);
            }
            $this->assertNoPeriodOverlap($version);
            $version->status = BudgetVersion::STATUS_APPROVED;
            $version->approved_by = auth()->id();
            $version->approved_at = now();
            $version->save();
            $this->recordEvent($version, 'approved');

            return $version->refresh()->load('lineItems', 'events');
        });
    }

    public function retire(BudgetVersion $version): BudgetVersion
    {
        $this->authorize('budget.retire');
        if ($version->status !== BudgetVersion::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only approved budget versions can be retired.']);
        }

        return DB::transaction(function () use ($version): BudgetVersion {
            $version = BudgetVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            if ($version->status !== BudgetVersion::STATUS_APPROVED) {
                throw ValidationException::withMessages(['status' => 'Only approved budget versions can be retired.']);
            }
            $version->status = BudgetVersion::STATUS_RETIRED;
            $version->retired_by = auth()->id();
            $version->retired_at = now();
            $version->save();
            $this->recordEvent($version, 'retired');

            return $version->refresh()->load('lineItems', 'events');
        });
    }

    /** @param array<string, mixed> $data */
    private function createVersionRecord(Budget $budget, array $data, int $number, string $event): BudgetVersion
    {
        $normalized = $this->normalizeVersionData($data);
        $version = $budget->versions()->create([
            'version' => $number,
            'period_start' => $normalized['period_start'],
            'period_end' => $normalized['period_end'],
            'currency_code' => $normalized['currency_code'],
            'dimensions' => $normalized['dimensions'],
            'status' => BudgetVersion::STATUS_DRAFT,
            'change_summary' => $data['change_summary'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $this->createLineItems($version, $normalized['line_items']);
        $this->recordEvent($version, $event);

        return $version->load('lineItems', 'events');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{period_start: string, period_end: string, currency_code: string, dimensions: array<string, string>, line_items: array<int, array{code: ?string, description: string, amount: string, dimensions: array<string, string>}>}
     */
    private function normalizeVersionData(array $data): array
    {
        $start = $this->validDate($data['period_start'] ?? null, 'period_start');
        $end = $this->validDate($data['period_end'] ?? null, 'period_end');
        if ($end < $start) {
            throw ValidationException::withMessages(['period_end' => 'Budget period end must be on or after its start.']);
        }

        $currency = strtoupper(trim((string) ($data['currency_code'] ?? '')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['currency_code' => 'Currency must be a three-letter uppercase ISO currency code.']);
        }

        $dimensions = $this->normalizeDimensions($data['dimensions'] ?? [], 'dimensions');
        $items = $data['line_items'] ?? [];
        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages(['line_items' => 'Add at least one budget line item.']);
        }

        $normalizedItems = [];
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(["line_items.{$index}" => 'Each line item must be an object.']);
            }
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '' || mb_strlen($description) > 200) {
                throw ValidationException::withMessages(["line_items.{$index}.description" => 'Line item description is required and must be at most 200 characters.']);
            }
            $amount = trim((string) ($item['amount'] ?? ''));
            if (! preg_match('/^\d{1,17}(?:\.\d{1,2})?$/', $amount)) {
                throw ValidationException::withMessages(["line_items.{$index}.amount" => 'Line item amount must be a non-negative amount with up to two decimal places.']);
            }
            $lineDimensions = $this->normalizeDimensions($item['dimensions'] ?? [], "line_items.{$index}.dimensions");
            foreach ($lineDimensions as $key => $value) {
                if (! array_key_exists($key, $dimensions) || $dimensions[$key] !== $value) {
                    throw ValidationException::withMessages(["line_items.{$index}.dimensions" => 'Line item dimensions must match the budget version dimension contract.']);
                }
            }
            $code = trim((string) ($item['code'] ?? ''));
            if (mb_strlen($code) > 64) {
                throw ValidationException::withMessages(["line_items.{$index}.code" => 'Line item code must be at most 64 characters.']);
            }
            [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
            $normalizedItems[] = [
                'code' => $code === '' ? null : $code,
                'description' => $description,
                'amount' => $whole.'.'.str_pad($fraction, 2, '0'),
                'dimensions' => $lineDimensions,
            ];
        }

        return [
            'period_start' => $start,
            'period_end' => $end,
            'currency_code' => $currency,
            'dimensions' => $dimensions,
            'line_items' => $normalizedItems,
        ];
    }

    /** @return array<string, string> */
    private function normalizeDimensions(mixed $dimensions, string $field): array
    {
        if (! is_array($dimensions)) {
            throw ValidationException::withMessages([$field => 'Dimensions must be a key/value object.']);
        }
        $normalized = [];
        foreach ($dimensions as $key => $value) {
            $name = trim((string) $key);
            if ($name === '' || mb_strlen($name) > 80 || ! is_scalar($value) || trim((string) $value) === '') {
                throw ValidationException::withMessages([$field => 'Dimension keys and values must be non-empty scalar values.']);
            }
            $normalized[$name] = trim((string) $value);
        }
        ksort($normalized);

        return $normalized;
    }

    private function validDate(mixed $value, string $field): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Enter a valid calendar date.']);
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Enter a valid calendar date.']);
        }

        return $value;
    }

    /** @param array<int, array{code: ?string, description: string, amount: string, dimensions: array<string, string>}> $items */
    private function createLineItems(BudgetVersion $version, array $items): void
    {
        foreach ($items as $position => $item) {
            $version->lineItems()->create($item + ['position' => $position]);
        }
    }

    private function assertLineItemDimensions(BudgetVersion $version): void
    {
        /** @var array<string, string> $contract */
        $contract = (array) $version->dimensions;
        foreach ($version->lineItems as $item) {
            /** @var array<string, string> $lineDimensions */
            $lineDimensions = (array) $item->dimensions;
            foreach ($lineDimensions as $key => $value) {
                if (! array_key_exists($key, $contract) || $contract[$key] !== $value) {
                    throw ValidationException::withMessages(['dimensions' => 'A line item does not match the budget version dimension contract.']);
                }
            }
        }
    }

    private function assertNoPeriodOverlap(BudgetVersion $version): void
    {
        Budget::query()->orderBy('id')->lockForUpdate()->get(['id']);

        $dimensions = (array) $version->dimensions;
        ksort($dimensions);
        $candidates = BudgetVersion::query()
            ->where('budget_id', '!=', $version->budget_id)
            ->whereIn('status', [BudgetVersion::STATUS_SUBMITTED, BudgetVersion::STATUS_APPROVED])
            ->whereDate('period_start', '<=', Carbon::parse($version->period_end)->toDateString())
            ->whereDate('period_end', '>=', Carbon::parse($version->period_start)->toDateString())
            ->get(['id', 'currency_code', 'dimensions']);

        foreach ($candidates as $candidate) {
            $candidateDimensions = (array) $candidate->dimensions;
            ksort($candidateDimensions);
            if ($candidateDimensions === $dimensions) {
                $message = $candidate->currency_code === $version->currency_code
                    ? 'Another submitted or approved budget already covers this dimension and period.'
                    : 'An overlapping budget exists for these dimensions in a different currency.';
                throw ValidationException::withMessages(['period_start' => $message]);
            }
        }
    }

    private function recordEvent(BudgetVersion $version, string $event): void
    {
        $version->loadMissing('lineItems');
        BudgetVersionEvent::query()->create([
            'budget_version_id' => $version->getKey(),
            'event' => $event,
            'actor_id' => auth()->id(),
            'snapshot' => [
                'version' => $version->version,
                'status' => $version->status,
                'period_start' => Carbon::parse($version->period_start)->toDateString(),
                'period_end' => Carbon::parse($version->period_end)->toDateString(),
                'currency_code' => $version->currency_code,
                'dimensions' => $version->dimensions,
                'line_items' => $version->lineItems->map(fn (BudgetLineItem $item): array => [
                    'code' => $item->code,
                    'description' => $item->description,
                    'amount' => $item->amount,
                    'dimensions' => $item->dimensions,
                ])->all(),
                'change_summary' => $version->change_summary,
            ],
            'created_at' => now(),
        ]);
    }

    private function authorize(string $ability): void
    {
        if (! hexa()->can($ability)) {
            throw new AuthorizationException("The {$ability} permission is required.");
        }
    }
}
