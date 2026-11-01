<?php

namespace App\Data\MarketIntelligence;

use App\Exceptions\MarketIntelligence\MalformedMarketDataException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

final readonly class NormalizedMarketDataRecord
{
    /** @param array<string, mixed> $sourceMetadata @param array<string, mixed> $metadata */
    private function __construct(
        public string $idempotencyKey,
        public string $sourceReference,
        public string $metricKey,
        public string $name,
        public string $category,
        public string $market,
        public ?string $segment,
        public string $unit,
        public string $value,
        public string $periodStart,
        public string $periodEnd,
        public CarbonImmutable $observedAt,
        public ?string $sourceUrl = null,
        public ?string $sourceRevision = null,
        public ?string $methodology = null,
        public ?float $confidence = null,
        public array $sourceMetadata = [],
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $required = ['idempotency_key', 'source_reference', 'metric_key', 'name', 'category', 'market', 'unit', 'value', 'period_start', 'period_end', 'observed_at'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload) || (is_string($payload[$field]) && trim($payload[$field]) === '')) {
                throw new MalformedMarketDataException("Market data record is missing required field: {$field}.");
            }
            if ($field !== 'value' && ! is_string($payload[$field])) {
                throw new MalformedMarketDataException("Market data record field {$field} must be a string.");
            }
        }
        foreach (['idempotency_key' => 160, 'source_reference' => 255, 'metric_key' => 100, 'name' => 255, 'category' => 100, 'market' => 100, 'segment' => 100, 'unit' => 50, 'source_revision' => 100] as $field => $max) {
            if (isset($payload[$field]) && (! is_string($payload[$field]) || mb_strlen($payload[$field]) > $max)) {
                throw new MalformedMarketDataException("Market data record field {$field} is invalid or too long.");
            }
        }
        if (! is_numeric($payload['value']) || ! is_finite((float) $payload['value']) || abs((float) $payload['value']) > 99999999999999.999999) {
            throw new MalformedMarketDataException('Market data record value must be a finite number.');
        }
        if (isset($payload['confidence']) && (! is_numeric($payload['confidence']) || (float) $payload['confidence'] < 0 || (float) $payload['confidence'] > 1)) {
            throw new MalformedMarketDataException('Market data confidence must be between 0 and 1.');
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', (string) $payload['period_start']);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', (string) $payload['period_end']);
            $observedAt = CarbonImmutable::parse((string) $payload['observed_at']);
        } catch (\Throwable $exception) {
            throw new MalformedMarketDataException('Market data record contains an invalid date or timestamp.', previous: $exception);
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $payload['period_start'] || $end->format('Y-m-d') !== $payload['period_end'] || $start->gt($end)) {
            throw new MalformedMarketDataException('Market data period must use valid YYYY-MM-DD dates in ascending order.');
        }
        if (isset($payload['source_url']) && (! is_string($payload['source_url']) || mb_strlen($payload['source_url']) > 2048 || filter_var($payload['source_url'], FILTER_VALIDATE_URL) === false)) {
            throw new MalformedMarketDataException('Market data source URL must be a valid URL.');
        }
        foreach (['source_metadata', 'metadata'] as $field) {
            if (isset($payload[$field]) && ! is_array($payload[$field])) {
                throw new MalformedMarketDataException("Market data {$field} must be an object/array.");
            }
            self::assertSafeMetadata($payload[$field] ?? [], $field);
        }

        if (isset($payload['methodology']) && (! is_string($payload['methodology']) || mb_strlen($payload['methodology']) > 10000)) {
            throw new MalformedMarketDataException('Market data methodology must be a string of at most 10000 characters.');
        }

        return new self(
            idempotencyKey: (string) $payload['idempotency_key'],
            sourceReference: (string) $payload['source_reference'],
            metricKey: (string) $payload['metric_key'],
            name: (string) $payload['name'],
            category: (string) $payload['category'],
            market: (string) $payload['market'],
            segment: isset($payload['segment']) ? (string) $payload['segment'] : null,
            unit: (string) $payload['unit'],
            value: (string) $payload['value'],
            periodStart: (string) $payload['period_start'],
            periodEnd: (string) $payload['period_end'],
            observedAt: $observedAt,
            sourceUrl: Arr::get($payload, 'source_url'),
            sourceRevision: Arr::get($payload, 'source_revision'),
            methodology: Arr::get($payload, 'methodology'),
            confidence: isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            sourceMetadata: $payload['source_metadata'] ?? [],
            metadata: $payload['metadata'] ?? [],
        );
    }

    /** @param array<string|int, mixed> $metadata */
    private static function assertSafeMetadata(array $metadata, string $field): void
    {
        foreach ($metadata as $key => $value) {
            if (is_string($key) && preg_match('/secret|token|password|credential|api[_-]?key|authorization/i', $key) === 1) {
                throw new MalformedMarketDataException("Market data {$field} cannot contain credential-like fields.");
            }
            if (is_array($value)) {
                self::assertSafeMetadata($value, $field);
            }
        }
    }
}
