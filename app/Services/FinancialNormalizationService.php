<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FinancialNormalizationService
{
    private ?int $runId = null;

    /** @var array<string, array<string, int>> */
    private array $recordCounts = [];

    /** @var array<string, array<string, int>> */
    private array $fieldCounts = [];

    public function run(bool $apply, int $batchSize): array
    {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new InvalidArgumentException('Batch size must be between 1 and 1000.');
        }

        $this->recordCounts = [];
        $this->fieldCounts = [];
        $this->runId = null;

        if ($apply) {
            $now = now();
            $this->runId = DB::table('financial_normalization_runs')->insertGetId([
                'mode' => 'apply',
                'status' => 'running',
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        try {
            foreach (config('financial-normalization.tables', []) as $table => $definition) {
                $this->processTable($table, $definition, $batchSize, $apply);
            }

            $summary = [
                'record_outcomes' => $this->recordCounts,
                'field_outcomes' => $this->fieldCounts,
                'reconciliation' => $apply ? $this->reconcileAppliedRun() : ['status' => 'dry-run; no post-write totals computed'],
                'batch_size' => $batchSize,
                'apply' => $apply,
            ];

            if ($apply && $this->runId !== null) {
                DB::table('financial_normalization_runs')->where('id', $this->runId)->update([
                    'status' => 'completed',
                    'summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $summary;
        } catch (Throwable $exception) {
            if ($apply && $this->runId !== null) {
                DB::table('financial_normalization_runs')->where('id', $this->runId)->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($this->safeFailureMessage($exception), 0, 2000),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            throw $exception;
        }
    }

    private function safeFailureMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (str_contains(strtoupper($message), 'SQLSTATE') || $exception instanceof QueryException) {
            return 'Database operation failed; inspect restricted application logs for details.';
        }

        return $message;
    }

    public function runId(): ?int
    {
        return $this->runId;
    }

    /** @param array<string, mixed> $definition */
    private function processTable(string $table, array $definition, int $batchSize, bool $apply): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Financial normalization table is missing: {$table}.");
        }

        foreach (['amounts', 'currencies', 'classifications'] as $group) {
            foreach ($definition[$group] ?? [] as $mapping) {
                if (! is_array($mapping)) {
                    throw new RuntimeException("Invalid {$group} mapping configured for {$table}.");
                }
                $target = $mapping['target'] ?? null;
                $source = $mapping['source'] ?? null;
                if (! is_string($target) || $target === '' || ! Schema::hasColumn($table, $target)) {
                    throw new RuntimeException("Configured canonical target {$table}.".(is_string($target) ? $target : '<missing>').' is missing.');
                }
                if ($source !== null && (! is_string($source) || ! Schema::hasColumn($table, $source))) {
                    throw new RuntimeException("Configured source field {$table}.".(is_string($source) ? $source : '<invalid>').' is missing.');
                }
                if ($group === 'amounts' && ! in_array($mapping['unit'] ?? null, [null, 'major', 'minor'], true)) {
                    throw new RuntimeException("Invalid source unit configured for {$table}.{$target}; use major, minor, or null.");
                }
                if ($group !== 'amounts' && ! is_array($mapping['map'] ?? [])) {
                    throw new RuntimeException("Configured exact-value map for {$table}.{$target} must be an array.");
                }
            }
        }
        foreach (($definition['invoice_semantics']['fields'] ?? []) as $source) {
            if (! is_string($source) || ! Schema::hasColumn($table, $source)) {
                throw new RuntimeException("Configured invoice semantic source field {$table}.".(is_string($source) ? $source : '<invalid>').' is missing.');
            }
        }

        $mappings = [];
        foreach (['amounts', 'currencies', 'classifications'] as $group) {
            foreach ($definition[$group] ?? [] as $mapping) {
                $mappings[] = ['group' => $group, 'mapping' => $mapping];
            }
        }

        $query = DB::table($table)->orderBy('id');
        $query->chunkById($batchSize, function ($rows) use ($table, $definition, $mappings, $apply): void {
            foreach ($rows as $row) {
                $this->processRow($table, $row, $mappings, $definition['invoice_semantics'] ?? null, $apply);
            }
        });
    }

    /** @param array<int, array{group: string, mapping: array<string, mixed>}> $mappings */
    private function processRow(string $table, object $row, array $mappings, ?array $invoiceSemantics, bool $apply): void
    {
        $decisions = [];
        foreach ($mappings as $entry) {
            $decisions[] = $this->assessMapping($table, $row, $entry['group'], $entry['mapping']);
        }

        if ($invoiceSemantics !== null) {
            $decisions[] = $this->assessInvoiceSemantics($table, $row, $invoiceSemantics);
        }

        $hasAmountMapping = collect($mappings)->contains(fn (array $entry): bool => $entry['group'] === 'amounts');
        $hasResolvedCurrency = ($row->currency_code ?? null) !== null || collect($decisions)->contains(fn (array $decision): bool => $decision['target'] === 'currency_code' && in_array($decision['outcome'], ['changed', 'unchanged'], true) && $decision['target_value'] !== null);
        if ($hasAmountMapping && Schema::hasColumn($table, 'currency_code') && ! $hasResolvedCurrency) {
            $decisions[] = $this->decision('currency_context', 'currency_context', 'quarantined', null, null, 'Canonical amount cannot be populated without an explicit verified currency code.');
        }

        if ($decisions === []) {
            return;
        }

        $hasRejected = collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'rejected');
        $hasQuarantine = collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'quarantined');
        $recordOutcome = $hasRejected ? 'rejected' : ($hasQuarantine ? 'quarantined' : (collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'changed') ? 'changed' : 'unchanged'));

        if ($recordOutcome === 'rejected' || $recordOutcome === 'quarantined') {
            foreach ($decisions as &$decision) {
                if ($decision['outcome'] === 'changed') {
                    $decision['outcome'] = 'quarantined';
                    $decision['reason'] = 'Row left unchanged because another field on the same record is rejected or ambiguous.';
                }
            }
            unset($decision);
        }

        $updates = [];
        foreach ($decisions as $decision) {
            if ($decision['outcome'] === 'changed') {
                $updates[$decision['target']] = $decision['target_value'];
            }
        }

        $this->increment($this->recordCounts, $table, $recordOutcome);
        foreach ($decisions as $decision) {
            $this->increment($this->fieldCounts, $table.'.'.$decision['field'], $decision['outcome']);
        }

        if (! $apply || $this->runId === null) {
            return;
        }

        DB::transaction(function () use ($table, $row, $decisions, $updates): void {
            if ($updates !== []) {
                DB::table($table)->where('id', $row->id)->update($updates);
            }

            $now = now();
            foreach ($decisions as $decision) {
                DB::table('financial_normalization_items')->insert([
                    'run_id' => $this->runId,
                    'source_table' => $table,
                    'source_id' => $row->id,
                    'field_name' => $decision['field'],
                    'outcome' => $decision['outcome'],
                    'record_outcome' => $this->recordOutcomeForAudit($decisions),
                    'source_value' => json_encode($decision['source_value'], JSON_THROW_ON_ERROR),
                    'target_value' => $decision['target_value'],
                    'reason' => $decision['reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $mapping
     * @return array{field: string, target: string, outcome: string, source_value: mixed, target_value: ?string, reason: ?string}
     */
    private function assessMapping(string $table, object $row, string $group, array $mapping): array
    {
        $target = $mapping['target'] ?? '';
        $source = $mapping['source'] ?? null;
        $field = $target !== '' ? $target : ($source ?? $group);

        if ($target === '' || ! Schema::hasColumn($table, $target)) {
            throw new RuntimeException("Configured canonical target {$table}.{$target} is missing.");
        }
        if ($source !== null && ! Schema::hasColumn($table, $source)) {
            throw new RuntimeException("Configured source field {$table}.{$source} is missing.");
        }

        $targetValue = $row->{$target} ?? null;
        $sourceValue = $source !== null && property_exists($row, $source) ? $row->{$source} : null;

        if ($targetValue !== null) {
            $candidate = $this->candidate($group, $mapping, $sourceValue);
            if ($candidate['outcome'] !== 'changed') {
                return $this->decision($field, $target, 'unchanged', $sourceValue, (string) $targetValue, 'Existing canonical value preserved; no overwrite attempted.');
            }
            if ($this->sameValue((string) $targetValue, (string) $candidate['value'], $group === 'amounts')) {
                return $this->decision($field, $target, 'unchanged', $sourceValue, (string) $targetValue, null);
            }

            return $this->decision($field, $target, 'quarantined', $sourceValue, (string) $targetValue, 'Canonical value conflicts with the configured source mapping; no overwrite performed.');
        }

        $candidate = $this->candidate($group, $mapping, $sourceValue);
        if ($candidate['outcome'] !== 'changed') {
            return $this->decision($field, $target, $candidate['outcome'], $sourceValue, null, $candidate['reason']);
        }

        return $this->decision($field, $target, 'changed', $sourceValue, (string) $candidate['value'], null);
    }

    /** @param array<string, mixed> $mapping
     * @return array{outcome: string, value?: string, reason?: string}
     */
    private function candidate(string $group, array $mapping, mixed $sourceValue): array
    {
        if (($mapping['source'] ?? null) === null) {
            return ['outcome' => 'quarantined', 'reason' => $mapping['reason'] ?? 'No approved source field; inference is prohibited.'];
        }
        if ($sourceValue === null || $sourceValue === '') {
            return ['outcome' => 'quarantined', 'reason' => 'Source value is missing; canonical value remains unknown.'];
        }

        if ($group === 'amounts') {
            $unit = $mapping['unit'] ?? null;
            if (! in_array($unit, ['major', 'minor'], true)) {
                return ['outcome' => 'quarantined', 'reason' => 'No approved legacy amount unit mapping.'];
            }
            $value = trim((string) $sourceValue);
            if (! preg_match('/^-?\d+$/', $value)) {
                return ['outcome' => 'rejected', 'reason' => 'Legacy amount is not an integer in the configured source representation.'];
            }
            if (($mapping['non_negative'] ?? false) && str_starts_with($value, '-')) {
                return ['outcome' => 'quarantined', 'reason' => 'Negative transaction amount may represent a correction/refund; classify from source evidence first.'];
            }

            $formatted = $this->formatAmount($value, $unit);
            $wholeDigits = ltrim(explode('.', ltrim($formatted, '-'))[0], '0');
            if (strlen($wholeDigits) > 17) {
                return ['outcome' => 'rejected', 'reason' => 'Mapped amount exceeds the DECIMAL(19,2) target range.'];
            }

            return ['outcome' => 'changed', 'value' => $formatted];
        }

        $map = $mapping['map'] ?? [];
        if ($map === []) {
            return ['outcome' => 'quarantined', 'reason' => $group === 'currencies' ? 'No approved exact currency-code mapping.' : 'No approved exact classification mapping.'];
        }
        $key = (string) $sourceValue;
        if (! array_key_exists($key, $map)) {
            return ['outcome' => 'quarantined', 'reason' => 'Source value is absent from the approved exact-value mapping.'];
        }
        $mapped = $map[$key];
        if (! is_string($mapped) || trim($mapped) === '') {
            return ['outcome' => 'rejected', 'reason' => 'Configured mapping target is empty or invalid.'];
        }
        $mapped = trim($mapped);
        if ($group === 'currencies' && preg_match('/^[A-Z]{3}$/', $mapped) !== 1) {
            return ['outcome' => 'rejected', 'reason' => 'Configured currency target must be a three-letter uppercase code; ISO validity must be established by the approved map.'];
        }
        if ($group === 'classifications' && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $mapped) !== 1) {
            return ['outcome' => 'rejected', 'reason' => 'Configured classification target must be a lowercase canonical code.'];
        }

        return ['outcome' => 'changed', 'value' => $mapped];
    }

    /** @param array<string, mixed> $rule
     * @return array{field: string, target: string, outcome: string, source_value: mixed, target_value: ?string, reason: ?string}
     */
    private function assessInvoiceSemantics(string $table, object $row, array $rule): array
    {
        $fields = $rule['fields'] ?? [];
        $source = [];
        foreach ($fields as $field) {
            $source[$field] = $row->{$field} ?? null;
        }

        $ruleName = $rule['rule'] ?? null;
        if ($ruleName === null) {
            return $this->decision('invoice_semantics', 'invoice_semantics', 'quarantined', $source, null, 'Invoice semantics are not approved; totals are not inferred or recalculated.');
        }

        if ($table === 'customer_invoices' && $ruleName === 'customer_vat_zero') {
            $vat = $row->vat ?? null;
            $valid = is_numeric($vat) && (float) $vat === 0.0;

            return $this->decision('invoice_semantics', 'invoice_semantics', $valid ? 'unchanged' : 'quarantined', $source, null, $valid ? null : 'Stored VAT is non-zero or malformed; preserve and review.');
        }

        if ($table === 'vendor_invoices' && $ruleName === 'vendor_total_equals_components') {
            $values = [$row->amount ?? null, $row->amount_without_vat ?? null, $row->vat ?? null];
            $valid = collect($values)->every(fn ($value): bool => is_numeric($value) && preg_match('/^-?\d+$/', (string) $value) === 1)
                && (int) $values[0] === (int) $values[1] + (int) $values[2];

            return $this->decision('invoice_semantics', 'invoice_semantics', $valid ? 'unchanged' : 'quarantined', $source, null, $valid ? null : 'Stored payable total does not match configured component rule; preserve and review.');
        }

        return $this->decision('invoice_semantics', 'invoice_semantics', 'rejected', $source, null, 'Unsupported invoice semantic rule configured for this table.');
    }

    /** @return array{status: string, groups: array<string, array{rows: int, expected_total: string, actual_total: string, matches: bool}>, mismatches: int, unresolved_currency_or_classification_rows: int} */
    private function reconcileAppliedRun(): array
    {
        if ($this->runId === null) {
            return ['status' => 'not-applied', 'groups' => [], 'mismatches' => 0, 'unresolved_currency_or_classification_rows' => 0];
        }

        $groups = [];
        $unresolvedRecords = [];
        foreach (config('financial-normalization.tables', []) as $table => $definition) {
            foreach ($definition['amounts'] ?? [] as $mapping) {
                $target = $mapping['target'];
                $query = DB::table('financial_normalization_items as items')
                    ->join($table.' as source', 'source.id', '=', 'items.source_id')
                    ->where('items.run_id', $this->runId)
                    ->where('items.source_table', $table)
                    ->where('items.field_name', $target)
                    ->whereIn('items.record_outcome', ['changed', 'unchanged'])
                    ->whereIn('items.outcome', ['changed', 'unchanged']);

                $classificationColumn = Schema::hasColumn($table, 'status_code')
                    ? 'status_code'
                    : (Schema::hasColumn($table, 'transaction_type_code') ? 'transaction_type_code' : null);
                $select = ['source.id as source_id', 'items.target_value', 'source.'.$target.' as actual_value', 'source.currency_code'];
                if ($classificationColumn !== null) {
                    $select[] = 'source.'.$classificationColumn.' as classification_code';
                }

                foreach ($query->get($select) as $row) {
                    if ($row->currency_code === null || ($classificationColumn !== null && $row->classification_code === null)) {
                        $unresolvedRecords[$table.'|'.$row->source_id] = true;

                        continue;
                    }

                    $classification = $classificationColumn === null ? 'NOT_APPLICABLE' : (string) $row->classification_code;
                    $key = $table.'.'.$target.'|'.$row->currency_code.'|'.$classification;
                    $groups[$key] ??= [
                        'rows' => 0,
                        'expected' => BigDecimal::zero(),
                        'actual' => BigDecimal::zero(),
                        'missing_canonical_values' => 0,
                    ];
                    $groups[$key]['rows']++;
                    $groups[$key]['expected'] = $groups[$key]['expected']->plus((string) $row->target_value);
                    if ($row->actual_value === null) {
                        $groups[$key]['missing_canonical_values']++;
                    } else {
                        $groups[$key]['actual'] = $groups[$key]['actual']->plus((string) $row->actual_value);
                    }
                }
            }
        }

        $result = [];
        $mismatches = 0;
        foreach ($groups as $key => $group) {
            $matches = $group['missing_canonical_values'] === 0 && $group['expected']->compareTo($group['actual']) === 0;
            if (! $matches) {
                $mismatches++;
            }
            $result[$key] = [
                'rows' => $group['rows'],
                'expected_total' => (string) $group['expected'],
                'actual_total' => (string) $group['actual'],
                'matches' => $matches,
            ];
        }

        return [
            'status' => $mismatches === 0 ? 'passed' : 'mismatch',
            'groups' => $result,
            'mismatches' => $mismatches,
            'unresolved_currency_or_classification_rows' => count($unresolvedRecords),
        ];
    }

    /** @param array<int, array<string, mixed>> $decisions */
    private function recordOutcomeForAudit(array $decisions): string
    {
        $hasRejected = collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'rejected');
        if ($hasRejected) {
            return 'rejected';
        }
        if (collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'quarantined')) {
            return 'quarantined';
        }

        return collect($decisions)->contains(fn (array $decision): bool => $decision['outcome'] === 'changed') ? 'changed' : 'unchanged';
    }

    /** @return array{field: string, target: string, outcome: string, source_value: mixed, target_value: ?string, reason: ?string} */
    private function decision(string $field, string $target, string $outcome, mixed $sourceValue, ?string $targetValue, ?string $reason): array
    {
        return ['field' => $field, 'target' => $target, 'outcome' => $outcome, 'source_value' => $sourceValue, 'target_value' => $targetValue, 'reason' => $reason];
    }

    private function formatAmount(string $value, string $unit): string
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0');
        $digits = $digits === '' ? '0' : $digits;

        if ($unit === 'major') {
            $formatted = $digits.'.00';
        } else {
            $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
            $formatted = substr($digits, 0, -2).'.'.substr($digits, -2);
        }

        return $negative && trim(str_replace('.', '', $formatted), '0') !== '' ? '-'.$formatted : $formatted;
    }

    private function sameValue(string $left, string $right, bool $decimal): bool
    {
        if (! $decimal) {
            return $left === $right;
        }

        return $this->normalizeDecimal($left) === $this->normalizeDecimal($right);
    }

    private function normalizeDecimal(string $value): string
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($value), $matches)) {
            return trim($value);
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $rawFraction = $matches[3] ?? '';
        if (strlen($rawFraction) > 2) {
            return trim($value);
        }
        $fraction = str_pad($rawFraction, 2, '0');
        $sign = ($matches[1]) === '-' && ($whole !== '0' || $fraction !== '00') ? '-' : '';

        return $sign.$whole.'.'.$fraction;
    }

    /** @param array<string, array<string, int>> $counts */
    private function increment(array &$counts, string $key, string $outcome): void
    {
        $counts[$key] ??= ['changed' => 0, 'unchanged' => 0, 'rejected' => 0, 'quarantined' => 0];
        $counts[$key][$outcome]++;
    }
}
